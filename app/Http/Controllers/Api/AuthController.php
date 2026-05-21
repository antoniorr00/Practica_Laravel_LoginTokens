<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador de autenticación de la API.
 *
 * Cuatro responsabilidades, una por método:
 *   - login    → autentica por nombre + contraseña y emite un token Sanctum.
 *   - register → da de alta un nuevo usuario (ruta pública).
 *   - me       → devuelve los datos del usuario autenticado (ruta protegida).
 *   - logout   → invalida el token actual (ruta protegida).
 *
 * El AuthController NO sabe NADA de cómo se valida el token en las peticiones
 * entrantes: de eso se encarga el middleware EnsureTokenIsValid.
 */
class AuthController extends Controller
{
    /**
     * Apartado 1 del enunciado.
     *
     * Autentica al usuario por nombre y contraseña. Si la petición YA viene con
     * un token Bearer válido, devolvemos una respuesta DISTINTA ("ya autenticado")
     * sin emitir un token nuevo, tal y como pide el enunciado.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // ¿Llega ya con sesión activa? Comprobamos el header Authorization a mano
        // (sin pasar por el middleware, porque /login es ruta pública).
        $alreadyLogged = $this->resolveUserFromBearer($request);

        if ($alreadyLogged !== null) {
            return response()->json([
                'message' => 'El usuario ya se encuentra autenticado.',
                'user' => $alreadyLogged->only(['id', 'name', 'email']),
            ], Response::HTTP_OK);
        }

        // Validamos credenciales: buscamos por nombre y comprobamos el hash.
        /** @var User|null $user */
        $user = User::query()->where('name', $request->string('name'))->first();

        if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Emitimos un nuevo token. Sanctum se encarga de hashear y guardarlo.
        $plainTextToken = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login correcto.',
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email']),
        ], Response::HTTP_OK);
    }

    /**
     * Ruta pública adicional (apartado 5): registro de usuarios.
     *
     * Devuelve también un token para que el usuario pueda usar la API
     * inmediatamente después de registrarse.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            // El cast 'hashed' del modelo se encarga del hashing al guardar.
            'password' => (string) $request->input('password'),
        ]);

        $plainTextToken = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado correctamente.',
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Apartado 3: muestra los datos del usuario autenticado.
     *
     * El middleware EnsureTokenIsValid ya ha resuelto y validado al usuario,
     * por lo que aquí basta con leer el atributo que él ha inyectado en la request.
     */
    public function me(Request $request): JsonResponse
    {
        $userId = (int) $request->attributes->get('auth_user_id');
        $user = User::findOrFail($userId);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'created_at', 'updated_at']),
        ], Response::HTTP_OK);
    }

    /**
     * Apartado 4: cierra sesión invalidando el token actual.
     *
     * Borramos físicamente la fila de personal_access_tokens correspondiente
     * al token con el que se ha hecho esta petición. A partir de aquí, ese token
     * deja de ser válido (el middleware ya no lo encontrará).
     */
    public function logout(Request $request): JsonResponse
    {
        $tokenId = (int) $request->attributes->get('auth_token_id');

        DB::table('personal_access_tokens')->where('id', $tokenId)->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ], Response::HTTP_OK);
    }

    /**
     * Helper privado usado SOLO en /login para detectar si la petición ya viene
     * autenticada. Reaprovecha la misma lógica del middleware pero devuelve el
     * User (o null) en vez de cortar la petición. Mantenerlo aquí permite que
     * /login sea pública en routing y siga detectando "ya logueado".
     */
    private function resolveUserFromBearer(Request $request): ?User
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || ! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $plainTextToken = trim($matches[1]);

        if (! str_contains($plainTextToken, '|')) {
            return null;
        }

        [$tokenId, $randomPart] = explode('|', $plainTextToken, 2);

        if (! ctype_digit($tokenId) || $randomPart === '') {
            return null;
        }

        $hashedToken = hash('sha256', $randomPart);

        $record = DB::table('personal_access_tokens')
            ->where('id', (int) $tokenId)
            ->where('token', $hashedToken)
            ->first();

        if ($record === null) {
            return null;
        }

        if ($record->expires_at !== null && strtotime((string) $record->expires_at) < time()) {
            return null;
        }

        return User::find($record->tokenable_id);
    }
}
