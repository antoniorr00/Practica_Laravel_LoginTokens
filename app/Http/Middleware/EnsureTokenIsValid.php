<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware propio de autenticación por token.
 *
 * Cumple con el apartado 2 del enunciado:
 *   - NO usa el middleware integrado en Sanctum (auth:sanctum).
 *   - NO usa el guard 'sanctum' de Laravel.
 *   - NO usa el helper PersonalAccessToken::findToken() de Sanctum.
 *
 * Lo único que tomamos prestado de Sanctum es su TABLA (personal_access_tokens)
 * porque el enunciado obliga a usar Sanctum para emitir tokens. La lógica de
 * autenticación —parseo del token, hashing y consulta— está hecha a mano.
 *
 * Formato del token Sanctum: "<id>|<plainText>"
 *   - <id>        → id de la fila en personal_access_tokens
 *   - <plainText> → cadena aleatoria; en BD se guarda su SHA-256
 *
 * Flujo:
 *   1. Leer header Authorization.
 *   2. Extraer "Bearer <token>".
 *   3. Separar id y parte aleatoria.
 *   4. Buscar la fila por id.
 *   5. Comparar SHA-256(plainText) con la columna `token`.
 *   6. Inyectar el usuario autenticado en la request (para usarlo en el controlador).
 *   7. Si algo falla en cualquier paso → 401 inmediato (no se ejecuta el controlador).
 */
class EnsureTokenIsValid
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $this->extractBearerToken($request);

        if ($plainTextToken === null) {
            return $this->unauthenticated('Token no proporcionado.');
        }

        $tokenRecord = $this->findValidTokenRecord($plainTextToken);

        if ($tokenRecord === null) {
            return $this->unauthenticated('Token inválido o expirado.');
        }

        $user = DB::table('users')->where('id', $tokenRecord->tokenable_id)->first();

        if ($user === null) {
            return $this->unauthenticated('El usuario asociado al token ya no existe.');
        }

        // Inyectamos el usuario y el id del token en la petición para que
        // el controlador pueda usarlos sin volver a buscarlos.
        $request->attributes->set('auth_user_id', (int) $user->id);
        $request->attributes->set('auth_token_id', (int) $tokenRecord->id);

        return $next($request);
    }

    /**
     * Devuelve el token en texto plano del header Authorization, o null si no viene
     * o no tiene el formato "Bearer <token>".
     */
    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || $header === '') {
            return null;
        }

        // Aceptamos exactamente el esquema "Bearer " (case-insensitive en el prefijo).
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * Busca la fila correspondiente en personal_access_tokens y verifica el hash.
     *
     * Sanctum guarda el token como SHA-256 del texto plano. Reproducimos ese
     * cálculo y comparamos con la columna `token` para validar.
     */
    private function findValidTokenRecord(string $plainTextToken): ?object
    {
        // El token tiene formato "<id>|<plainText>". Si no respeta ese formato,
        // no puede ser un token Sanctum válido.
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

        // Si el token tiene fecha de expiración y ya pasó, lo damos por inválido.
        if ($record->expires_at !== null && strtotime((string) $record->expires_at) < time()) {
            return null;
        }

        return $record;
    }

    /**
     * Respuesta 401 estándar en JSON, siguiendo los principios REST.
     */
    private function unauthenticated(string $message): JsonResponse
    {
        return response()->json([
            'message' => 'No autenticado.',
            'error' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
