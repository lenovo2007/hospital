<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\JsonResponse;

class AppendAuthStatus
{
    public function handle(Request $request, Closure $next)
    {
        $authStatus = $this->resolveAuthStatus($request);

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            if (!is_array($data)) {
                $data = [
                    'status' => true,
                    'mensaje' => '',
                    'data' => $data,
                ];
            }

            $data['autenticacion'] = $authStatus;

            $response->setData($data);
        }

        return $response;
    }

    protected function resolveAuthStatus(Request $request): int
    {
        // Usuario autenticado por el guard (auth:sanctum)
        if ($request->user()) {
            return 0; // autenticado / token válido
        }

        $tokenString = $request->bearerToken();
        if (!$tokenString) {
            return 1; // token ausente
        }

        $token = PersonalAccessToken::findToken($tokenString);
        if (!$token) {
            return 1; // token inválido
        }

        $expiration = config('sanctum.expiration'); // minutos o null
        if (is_int($expiration) && $expiration > 0) {
            $createdAt = $token->created_at ?? now();
            if ($createdAt->lt(now()->subMinutes($expiration))) {
                return 2; // token expirado
            }
        }

        return 0; // token válido
    }
}
