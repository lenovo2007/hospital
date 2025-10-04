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
                    ->whereIn('estado', ['pendiente', 'en_transito'])
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
                    'despachado' => 'en_transito',
                    'en_camino' => 'en_transito', 
                    'entregado' => 'entregado',
                };

                $movimiento->update(['estado' => $estadoMovimiento]);

                // Si está entregado, actualizar el estado de los lotes grupos a 'entregado'
                if ($data['estado'] === 'entregado') {
                    DB::table('lotes_grupos')
                        ->where('codigo', $movimiento->codigo_grupo)
                        ->where('status', 'activo')
                        ->update(['estado' => 'entregado']);
                }
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
            ->whereIn('estado', ['pendiente', 'en_transito', 'entregado'])
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
}
