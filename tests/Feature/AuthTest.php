<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests Feature de la práctica "Login por Tokens".
 *
 * Cada test cubre uno (o más) de los 5 apartados del enunciado:
 *
 *   - Apartado 1 (login): login_ok, login_credenciales_invalidas, login_ya_autenticado.
 *   - Apartado 2 (middleware propio): ruta_protegida_sin_token_devuelve_401,
 *     ruta_protegida_con_token_invalido_devuelve_401.
 *   - Apartado 3 (/me protegido): me_devuelve_datos_del_usuario.
 *   - Apartado 4 (logout): logout_invalida_el_token.
 *   - Apartado 5 (rutas públicas): login_es_publico, register_es_publico.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- Apartado 1
    public function test_login_devuelve_token_con_credenciales_validas(): void
    {
        $user = User::factory()->create([
            'name' => 'pepito',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'name' => 'pepito',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'token', 'token_type', 'user' => ['id', 'name']])
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_falla_con_credenciales_invalidas(): void
    {
        User::factory()->create([
            'name' => 'pepito',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/login', [
            'name' => 'pepito',
            'password' => 'mal',
        ])->assertUnauthorized();
    }

    public function test_login_indica_diferente_respuesta_si_ya_autenticado(): void
    {
        $user = User::factory()->create([
            'name' => 'pepito',
            'password' => Hash::make('secret123'),
        ]);

        // Primer login: obtenemos token.
        $token = $this->postJson('/api/login', [
            'name' => 'pepito',
            'password' => 'secret123',
        ])->json('token');

        // Segundo login: enviamos el token Bearer → debe responder "ya autenticado".
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/login', [
                'name' => 'pepito',
                'password' => 'secret123',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'El usuario ya se encuentra autenticado.')
            ->assertJsonMissing(['token']);

        // No se ha emitido un segundo token.
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    // ---------------------------------------------------------------- Apartado 2
    public function test_ruta_protegida_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_ruta_protegida_con_token_invalido_devuelve_401(): void
    {
        $this->withHeader('Authorization', 'Bearer 1|tokenfalso')
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_ruta_protegida_con_header_mal_formado_devuelve_401(): void
    {
        $this->withHeader('Authorization', 'NoBearer xxx')
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------- Apartado 3
    public function test_me_devuelve_los_datos_del_usuario_autenticado(): void
    {
        $user = User::factory()->create(['name' => 'pepito']);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'pepito');
    }

    // ---------------------------------------------------------------- Apartado 4
    public function test_logout_invalida_el_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Tras el logout, el mismo token ya no sirve para entrar a /me.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------- Apartado 5
    public function test_register_es_publico_y_devuelve_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'nuevoUser',
            'password' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'token', 'token_type', 'user' => ['id', 'name']]);

        $this->assertDatabaseHas('users', ['name' => 'nuevoUser']);
    }

    public function test_login_es_publico(): void
    {
        // Aunque las credenciales no existan, la ruta debe ser accesible sin token
        // (no devuelve 401 por falta de token, sino por credenciales malas).
        $this->postJson('/api/login', [
            'name' => 'noexiste',
            'password' => 'lo-que-sea',
        ])->assertUnauthorized(); // 401 por credenciales, no por middleware.
    }
}
