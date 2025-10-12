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
                    $data['observaciones'] ?? null
                );

                // Actualizar estado del movimiento según el estado del seguimiento
                $estadoMovimiento = match($data['estado']) {
                    'despachado' => 'despachado',
                    'en_camino' => 'despachado', 
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
                          ->orderByDesc('created_at')
                          ->limit(1);
                }
            ])
            ->whereIn('estado', ['pendiente', 'despachado', 'entregado'])
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
                'fecha_desde' => ['nullable', 'date'],
                'fecha_hasta' => ['nullable', 'date'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $query = DB::table('movimientos_stock as ms')
                ->join('lotes_grupos as lg', 'ms.codigo_grupo', '=', 'lg.codigo')
                ->join('lotes as l', 'lg.lote_id', '=', 'l.id')
                ->join('insumos as i', 'l.id_insumo', '=', 'i.id')
                ->leftJoin('hospitales as ho', 'ms.origen_hospital_id', '=', 'ho.id')
                ->leftJoin('sedes as so', 'ms.origen_sede_id', '=', 'so.id')
                ->leftJoin('hospitales as hd', 'ms.destino_hospital_id', '=', 'hd.id')
                ->leftJoin('sedes as sd', 'ms.destino_sede_id', '=', 'sd.id')
                ->where('ms.estado', 'pendiente')
                ->where('lg.status', 'activo');

            // Aplicar filtros opcionales
            if ($request->filled('origen_sede_id')) {
                $query->where('ms.origen_sede_id', $request->origen_sede_id);
            }

            if ($request->filled('destino_sede_id')) {
                $query->where('ms.destino_sede_id', $request->destino_sede_id);
            }

            if ($request->filled('fecha_desde')) {
                $query->whereDate('ms.created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('ms.created_at', '<=', $request->fecha_hasta);
            }

            // Seleccionar campos específicos
            $query->select([
                'ms.id as movimiento_id',
                'ms.tipo',
                'ms.tipo_movimiento',
                'ms.cantidad_salida_total',
                'ms.fecha_despacho',
                'ms.observaciones',
                'ms.created_at as fecha_creacion',
                
                // Datos de origen
                'ho.nombre as origen_hospital_nombre',
                'so.nombre as origen_sede_nombre',
                'so.id as origen_sede_id',
                'ms.origen_almacen_tipo',
                
                // Datos de destino
                'hd.nombre as destino_hospital_nombre',
                'sd.nombre as destino_sede_nombre',
                'sd.id as destino_sede_id',
                'ms.destino_almacen_tipo',
                
                // Datos del insumo
                'i.id as insumo_id',
                'i.nombre as insumo_nombre',
                'i.codigo as insumo_codigo',
                'i.presentacion as insumo_presentacion',
                'i.categoria',
                
                // Datos del lote
                'l.lote',
                'l.fecha_vencimiento',
                'lg.cantidad_salida',
                'lg.codigo as lote_grupo_codigo'
            ]);

            // Ordenar por fecha de creación más reciente
            $query->orderByDesc('ms.created_at');

            // Paginación
            $perPage = $request->get('per_page', 15);
            $movimientos = $query->paginate($perPage);

            // Agrupar por movimiento para mejor estructura
            $movimientosAgrupados = [];
            foreach ($movimientos->items() as $item) {
                $movimientoId = $item->movimiento_id;
                
                if (!isset($movimientosAgrupados[$movimientoId])) {
                    $movimientosAgrupados[$movimientoId] = [
                        'movimiento_id' => $item->movimiento_id,
                        'tipo' => $item->tipo,
                        'tipo_movimiento' => $item->tipo_movimiento,
                        'cantidad_total' => $item->cantidad_salida_total,
                        'fecha_despacho' => $item->fecha_despacho,
                        'fecha_creacion' => $item->fecha_creacion,
                        'observaciones' => $item->observaciones,
                        'origen' => [
                            'hospital_nombre' => $item->origen_hospital_nombre,
                            'sede_nombre' => $item->origen_sede_nombre,
                            'sede_id' => $item->origen_sede_id,
                            'almacen_tipo' => $item->origen_almacen_tipo,
                        ],
                        'destino' => [
                            'hospital_nombre' => $item->destino_hospital_nombre,
                            'sede_nombre' => $item->destino_sede_nombre,
                            'sede_id' => $item->destino_sede_id,
                            'almacen_tipo' => $item->destino_almacen_tipo,
                        ],
                        'insumos' => []
                    ];
                }
                
                // Agregar insumo al movimiento
                $movimientosAgrupados[$movimientoId]['insumos'][] = [
                    'insumo_id' => $item->insumo_id,
                    'nombre' => $item->insumo_nombre,
                    'codigo' => $item->insumo_codigo,
                    'presentacion' => $item->insumo_presentacion,
                    'categoria' => $item->categoria,
                    'lote' => $item->lote,
                    'fecha_vencimiento' => $item->fecha_vencimiento,
                    'cantidad' => $item->cantidad_salida,
                    'lote_grupo_codigo' => $item->lote_grupo_codigo,
                ];
            }

            // Convertir a array indexado
            $movimientosFinales = array_values($movimientosAgrupados);

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimientos pendientes obtenidos correctamente.',
                'data' => [
                    'movimientos' => $movimientosFinales,
                    'pagination' => [
                        'current_page' => $movimientos->currentPage(),
                        'per_page' => $movimientos->perPage(),
                        'total' => $movimientos->total(),
                        'last_page' => $movimientos->lastPage(),
                        'from' => $movimientos->firstItem(),
                        'to' => $movimientos->lastItem(),
                    ],
                    'filtros_aplicados' => [
                        'origen_sede_id' => $request->origen_sede_id,
                        'destino_sede_id' => $request->destino_sede_id,
                        'fecha_desde' => $request->fecha_desde,
                        'fecha_hasta' => $request->fecha_hasta,
                    ],
                    'total_movimientos_pendientes' => count($movimientosFinales)
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
}
