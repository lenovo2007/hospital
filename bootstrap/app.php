<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errores de validación (422)
        $exceptions->render(function (ValidationException $e, $request) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Errores de validación.',
                'data' => $e->errors(),
            ], 200, [], JSON_UNESCAPED_UNICODE);
        });
        // Respuesta JSON uniforme cuando no está autenticado
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No autenticado. Token inválido o ausente.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        });

        // Falta de permisos
        $exceptions->render(function (AuthorizationException $e, $request) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No autorizado para realizar esta acción.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        });

        // Recurso no encontrado (modelo o ruta)
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException|RouteNotFoundException $e, $request) {
            // Caso especial: el middleware intenta redirigir a una ruta 'login' inexistente
            if ($e instanceof RouteNotFoundException && str_contains($e->getMessage(), 'login')) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No autenticado. Token inválido o ausente.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            return response()->json([
                'status' => false,
                'mensaje' => 'Recurso no encontrado.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        });
    })->create();
