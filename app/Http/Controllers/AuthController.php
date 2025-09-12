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

        $user = User::with(['hospital', 'sede'])
            ->where('email', $credentials['email'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Credenciales inválidas.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        // Create token with device name (optional)
        $token = $user->createToken('api')->plainTextToken;

        // Get user data with relationships
        $response = [
            'status' => true,
            'mensaje' => 'Login exitoso.',
            'data' => [
                'token' => $token,
                'user' => $user->toArray(),
            ],
        ];

        // Add hospital data if relationship exists
        if ($user->hospital) {
            $response['data']['hospital'] = [
                'id' => $user->hospital->id,
                'nombre' => $user->hospital->nombre,
                'rif' => $user->hospital->rif,
                'direccion' => $user->hospital->direccion,
                'telefono' => $user->hospital->telefono,
                'email' => $user->hospital->email
            ];
        }

        // Add sede data if relationship exists
        if ($user->sede) {
            $response['data']['sede'] = [
                'id' => $user->sede->id,
                'nombre' => $user->sede->nombre,
                'tipo_almacen' => $user->sede->tipo_almacen,
                'hospital_id' => $user->sede->hospital_id,
                'status' => $user->sede->status
            ];
        }

        return response()->json($response, 200, [], JSON_UNESCAPED_UNICODE);
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
