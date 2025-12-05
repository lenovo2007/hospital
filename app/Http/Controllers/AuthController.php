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
        try {
            // Validate request
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            // Find user with email
            $user = User::where('email', $credentials['email'])->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Credenciales inválidas.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            // Create API token
            $token = $user->createToken('api')->plainTextToken;

            // Load relationships if not already loaded
            if (!$user->relationLoaded('hospital')) {
                $user->load('hospital');
            }
            if (!$user->relationLoaded('sede')) {
                $user->load('sede');
            }

            // Prepare user data with hospital and sede included
            $userData = $user->withoutRelations()->makeHidden(['password', 'remember_token'])->toArray();
            $userData['hospital'] = $user->hospital_data;
            $userData['sede'] = $user->sede_data;

            // Prepare response data
            $response = [
                'status' => true,
                'mensaje' => 'Login exitoso.',
                'data' => [
                    'token' => $token,
                    'user' => $userData,
                ],
                'autenticacion' => 1
            ];

            return response()->json($response, 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error de validación.',
                'errores' => $e->errors(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'mensaje' => 'Error en el servidor al procesar la autenticación.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null,
            ], 500, [], JSON_UNESCAPED_UNICODE);
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
