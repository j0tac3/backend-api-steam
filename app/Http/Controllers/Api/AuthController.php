<?php

namespace App\Http\Controllers\Api; // <-- CRÍTICO: Debe ser 'Api', no 'API'

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password), // Hash es obligatorio o dará 500
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            // Si algo explota (BD, sintaxis), te lo dirá en la consola en vez de un 500 vacío
            return response()->json([
                'message' => 'Error fatal en el servidor al registrar',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        // Revoca (elimina) el token actual con el que se hizo la petición
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    // 🚀 NUEVA FUNCIÓN: Guardar preferencias
    // 🚀 NUEVA FUNCIÓN: Guardar preferencias
    public function updatePreferences(Request $request)
    {
        $user = $request->user();
        
        $data = $request->validate([
            'is_public' => 'boolean',
            'vista_biblioteca' => 'string|in:cuadricula,tablero'
        ]);

        // 🚀 Quitamos el DB::raw. Como ya pusimos el $casts en User.php, 
        // Laravel sabe enviar 'false' a PostgreSQL y '0' a MySQL automáticamente.
        if (isset($data['is_public'])) {
            $user->is_public = $data['is_public'];
        }

        if (isset($data['vista_biblioteca'])) {
            $user->settings = array_merge($user->settings ?? [], [
                'vista_biblioteca' => $data['vista_biblioteca']
            ]);
        }

        $user->save();

        return response()->json([
            'message' => 'Preferencias actualizadas',
            'user' => $user
        ]);
    }
}