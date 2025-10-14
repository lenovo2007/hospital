<?php

namespace App\Http\Controllers;

use App\Models\MovimientoStock;
use App\Models\Seguimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SeguimientoRepartidorController extends Controller
{
    /**
     * Actualizar el estado del seguimiento del repartidor
     */
    public function actualizarSeguimiento(Request $request)
    {
        $data = $request->validate([
            'movimiento_stock_id' => ['required', 'integer', 'min:1'],
            'despachador_id' => ['required', 'integer', 'min:1'],
            'estado' => ['required', 'string', 'in:despachado,en_camino,entregado'],
            'ubicacion' => ['nullable', 'array'],
            'ubicacion.lat' => ['required_with:ubicacion', 'numeric'],
            'ubicacion.lng' => ['required_with:ubicacion', 'numeric'],
            'ubicacion.direccion' => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = (int) $request->user()->id;

        try {
            DB::transaction(function () use ($data, $userId) {
                // Buscar el movimiento
                $movimiento = MovimientoStock::where('id', $data['movimiento_stock_id'])
                    ->whereIn('estado', ['pendiente', 'despachado'])
                    ->lockForUpdate()
                    ->first();

                if (!$movimiento) {
                    throw new InvalidArgumentException('No existe un movimiento válido con el ID indicado o ya fue completado.');
                }

                // Crear registro de seguimiento
                Seguimiento::crearSeguimiento(
                    $data['movimiento_stock_id'],
                    $data['estado'],
                    $userId,
                    $data['ubicacion'] ?? null,
                    $data['observaciones'] ?? null,
                    $data['despachador_id'] ?? null
                );

                // Verificar si 'en_camino' está disponible en el enum
                $enumCheck = DB::select("SHOW COLUMNS FROM movimientos_stock WHERE Field = 'estado'");
                $hasEnCamino = !empty($enumCheck) && strpos($enumCheck[0]->Type, 'en_camino') !== false;
                
                // Actualizar estado del movimiento según el estado del seguimiento
                $estadoMovimiento = match($data['estado']) {
                    'despachado' => 'despachado',
                    'en_camino' => $hasEnCamino ? 'en_camino' : 'despachado',
                    'entregado' => 'entregado',
                };

                $movimiento->update(['estado' => $estadoMovimiento]);

                // Nota: Los lotes_grupos mantienen su status 'activo' durante todo el proceso
            });

            $mensaje = match($data['estado']) {
                'despachado' => 'Movimiento marcado como despachado.',
                'en_camino' => 'Ubicación actualizada - En camino.',
                'entregado' => 'Movimiento marcado como entregado.',
            };

            return response()->json([
                'status' => true,
                'mensaje' => $mensaje,
                'data' => null,
            ], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'mensaje' => $e->getMessage(),
                'data' => null,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al actualizar el seguimiento: ' . $e->getMessage(),
                'data' => null,
            ], 200);
        }
    }

    /**
     * Obtener el historial de seguimiento de un movimiento
     */
    public function obtenerSeguimiento(int $movimientoStockId)
    {
        try {
            $seguimientos = Seguimiento::where('movimiento_stock_id', $movimientoStockId)
                ->with('repartidor:id,nombre,apellido')
                ->orderBy('created_at', 'asc')
                ->get();

            if ($seguimientos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No se encontró seguimiento para este movimiento.',
                    'data' => null,
                ], 200);
            }

            return response()->json([
                'status' => true,
                'mensaje' => 'Historial de seguimiento obtenido.',
                'data' => $seguimientos,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al obtener el seguimiento.',
                'data' => null,
            ], 200);
        }
    }

    /**
     * Obtener todos los movimientos asignados a un repartidor
     */
    public function movimientosRepartidor(Request $request)
    {
        $userId = (int) $request->user()->id;

        try {
            $movimientos = MovimientoStock::whereHas('seguimientos', function ($query) use ($userId) {
                $query->where('user_id_repartidor', $userId);
            })
            ->with([
                'origenHospital:id,nombre',
                'origenSede:id,nombre',
                'destinoHospital:id,nombre',
                'destinoSede:id,nombre',
                'seguimientos' => function ($query) use ($userId) {
                    $query->where('user_id_repartidor', $userId)
                          ->with([
                              'despachador:id,nombre,email',
                              'repartidor:id,nombre,email'
                          ])
                          ->orderByDesc('created_at');
                }
            ])
            ->orderByDesc('created_at')
            ->get();

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimientos del repartidor obtenidos.',
                'data' => $movimientos,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al obtener los movimientos.',
                'data' => null,
            ], 200);
        }
    }

    /**
     * Obtener todos los movimientos pendientes para repartidores
     * GET /api/repartidor/movimientos-pendientes
     */
    public function movimientosPendientes(Request $request)
    {
        try {
            // Validar parámetros opcionales
            $request->validate([
                'origen_sede_id' => ['nullable', 'integer', 'min:1'],
                'destino_sede_id' => ['nullable', 'integer', 'min:1'],
                'sede_id' => ['nullable', 'integer', 'min:1'],
                'fecha_desde' => ['nullable', 'date'],
                'fecha_hasta' => ['nullable', 'date'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $query = MovimientoStock::query()
                ->with([
                    'origenHospital:id,nombre',
                    'origenSede:id,nombre', 
                    'destinoHospital:id,nombre',
                    'destinoSede:id,nombre',
                    'seguimientos'
                ])
                ->where('estado', 'pendiente');

            // Aplicar filtros opcionales
            if ($request->filled('sede_id')) {
                // Filtro general: mostrar movimientos donde la sede sea origen O destino
                $query->where(function($q) use ($request) {
                    $q->where('origen_sede_id', $request->sede_id)
                      ->orWhere('destino_sede_id', $request->sede_id);
                });
            } else {
                // Filtros específicos (solo si no se usa el filtro general)
                if ($request->filled('origen_sede_id')) {
                    $query->where('origen_sede_id', $request->origen_sede_id);
                }

                if ($request->filled('destino_sede_id')) {
                    $query->where('destino_sede_id', $request->destino_sede_id);
                }
            }

            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            // Ordenar por fecha de creación más reciente
            $query->orderByDesc('created_at');

            // Paginación
            $perPage = $request->get('per_page', 15);
            $movimientos = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimientos pendientes obtenidos correctamente.',
                'data' => $movimientos->items(),
                'pagination' => [
                    'current_page' => $movimientos->currentPage(),
                    'per_page' => $movimientos->perPage(),
                    'total' => $movimientos->total(),
                    'last_page' => $movimientos->lastPage(),
                    'from' => $movimientos->firstItem(),
                    'to' => $movimientos->lastItem(),
                ],
                'filtros_aplicados' => [
                    'sede_id' => $request->sede_id,
                    'origen_sede_id' => $request->origen_sede_id,
                    'destino_sede_id' => $request->destino_sede_id,
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                ]
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al obtener los movimientos pendientes: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Obtener movimientos en camino por sede
     * GET /api/repartidor/movimientos-en-camino/{sede_id}
     */
    public function movimientosEnCamino(Request $request, $sedeId)
    {
        try {
            $movimientos = MovimientoStock::whereHas('seguimientos')
                ->with([
                    'origenHospital:id,nombre',
                    'origenSede:id,nombre',
                    'destinoHospital:id,nombre',
                    'destinoSede:id,nombre',
                    'seguimientos' => function ($query) {
                        $query->with([
                            'despachador:id,nombre,email',
                            'repartidor:id,nombre,email'
                        ])
                        ->orderByDesc('created_at');
                    }
                ])
                ->where('estado', 'en_camino')
                ->where('destino_sede_id', $sedeId)
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimientos en camino obtenidos correctamente.',
                'data' => $movimientos,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al obtener los movimientos en camino: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Obtener movimientos entregados/recibidos por sede
     * GET /api/repartidor/movimientos-entregados/{sede_id}
     */
    public function movimientosEntregados(Request $request, $sedeId)
    {
        try {
            $movimientos = MovimientoStock::whereHas('seguimientos')
                ->with([
                    'origenHospital:id,nombre',
                    'origenSede:id,nombre',
                    'destinoHospital:id,nombre',
                    'destinoSede:id,nombre',
                    'seguimientos' => function ($query) {
                        $query->with([
                            'despachador:id,nombre,email',
                            'repartidor:id,nombre,email'
                        ])
                        ->orderByDesc('created_at');
                    }
                ])
                ->whereIn('estado', ['entregado', 'recibido'])
                ->where(function($query) use ($sedeId) {
                    $query->where('origen_sede_id', $sedeId)
                          ->orWhere('destino_sede_id', $sedeId);
                })
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimientos entregados obtenidos correctamente.',
                'data' => $movimientos,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al obtener los movimientos entregados: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}
