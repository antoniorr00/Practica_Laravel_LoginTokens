<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del registro de usuarios.
 *
 * Pedimos sólo lo mínimo que exige el modelo de autenticación de la práctica:
 * nombre (único) y contraseña. El email queda opcional.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // 'name' es la credencial de acceso → debe ser único.
            'name' => ['required', 'string', 'min:3', 'max:255', 'unique:users,name'],
            // Mínimo 8 caracteres es una buena práctica de seguridad mínima.
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
