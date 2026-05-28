<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de los datos de entrada al login.
 *
 * Aísla la validación del controlador para mantenerlo delgado y reutilizable.
 * Si la validación falla, Laravel responde automáticamente con HTTP 422 y
 * el detalle de los errores en JSON (al ser una petición a /api/*).
 */
class LoginRequest extends FormRequest
{
    /**
     * El login es público: cualquiera puede intentar autenticarse.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Login por NOMBRE y CONTRASEÑA.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
