<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * La password actual que se está utilizando en la fábrica.
     */
    protected static ?string $password;

    /**
     * Define el modelo por defecto para la fábrica.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 'name' es la credencial de login y es UNIQUE en BD → garantizamos unicidad.
            'name' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indica que el usuario generado no tiene el email verificado (email_verified_at = null).
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
