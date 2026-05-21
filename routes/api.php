<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Estas rutas se cargan con prefijo /api/ y el middleware 'api' (stateless,
| sin cookies de sesión), tal y como obliga el enfoque REST.
|
| Apartado 5 del enunciado:
|   - Públicas (no requieren token): /login y /register.
|   - Protegidas (requieren token): el resto, agrupadas bajo nuestro
|     middleware propio 'auth.token'.
*/

// ---- Rutas públicas ---------------------------------------------------------
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// ---- Rutas protegidas por nuestro middleware propio (NO auth:sanctum) -------
Route::middleware('auth.token')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
