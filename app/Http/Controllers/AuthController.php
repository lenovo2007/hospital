<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Credenciales inválidas.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        // Opcional: limitar dispositivos o nombrar el token
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'status' => true,
            'mensaje' => 'Login exitoso.',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/logout (autenticado)
    public function logout(Request $request)
    {
        // Revocar el token actual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'mensaje' => 'Sesión cerrada.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/me (autenticado)
    public function me(Request $request)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'OK',
            'data' => $request->user(),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
