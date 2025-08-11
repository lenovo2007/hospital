<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckCrudPermissions
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No autenticado. Token inválido o ausente.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $method = strtoupper($request->method());
        $routeAction = optional($request->route())->getActionMethod(); // index, store, show, update, destroy

        $permission = null;
        // Priorizar por método HTTP
        switch ($method) {
            case 'GET':
                $permission = 'can_view';
                break;
            case 'POST':
                $permission = 'can_create';
                break;
            case 'PUT':
            case 'PATCH':
                $permission = 'can_update';
                break;
            case 'DELETE':
                $permission = 'can_delete';
                break;
            default:
                $permission = 'can_view';
        }

        // Refuerzo por nombre de acción de controlador
        if ($routeAction === 'index' || $routeAction === 'show') {
            $permission = 'can_view';
        } elseif ($routeAction === 'store') {
            $permission = 'can_create';
        } elseif ($routeAction === 'update') {
            $permission = 'can_update';
        } elseif ($routeAction === 'destroy') {
            $permission = 'can_delete';
        }

        if ($permission && !$user->{$permission}) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No autorizado para realizar esta acción.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        return $next($request);
    }
}
