<?php

namespace App\Http\Controllers;

use App\Models\Seguimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SeguimientoController extends Controller
{
    /**
     * Listar todos los seguimientos con filtros
     */
    public function index(Request $request)
    {
        try {
            $query = Seguimiento::with(['movimientoStock', 'repartidor:id,nombre,apellido']);

            // Filtros opcionales
            if ($request->has('movimiento_stock_id')) {
                $query->where('movimiento_stock_id', $request->movimiento_stock_id);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('user_id_repartidor')) {
                $query->where('user_id_repartidor', $request->user_id_repartidor);
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $seguimientos = $query->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'status' => true,
                'mensaje' => 'Seguimientos obtenidos exitosamente.',
                'data' => $seguimientos,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al obtener los seguimientos.',
                'data' => null,
            ], 200);
        }
    }

    /**
     * Crear un nuevo seguimiento
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'movimiento_stock_id' => ['required', 'integer', 'exists:movimientos_stock,id'],
            'ubicacion' => ['nullable', 'array'],
            'ubicacion.lat' => ['required_with:ubicacion', 'numeric'],
            'ubicacion.lng' => ['required_with:ubicacion', 'numeric'],
            'ubicacion.direccion' => ['nullable', 'string', 'max:500'],
            'estado' => ['required', 'string', 'in:despachado,en_camino,entregado'],
            'status' => ['nullable', 'string', 'in:activo,completado'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'user_id_repartidor' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $seguimiento = Seguimiento::create([
                'movimiento_stock_id' => $data['movimiento_stock_id'],
                'ubicacion' => $data['ubicacion'] ?? null,
                'estado' => $data['estado'],
                'status' => $data['status'] ?? 'activo',
                'observaciones' => $data['observaciones'] ?? null,
                'user_id_repartidor' => $data['user_id_repartidor'],
            ]);

            return response()->json([
                'status' => true,
                'mensaje' => 'Seguimiento creado exitosamente.',
                'data' => $seguimiento->load(['movimientoStock', 'repartidor:id,nombre,apellido']),
            ], 201);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al crear el seguimiento.',
                'data' => null,
            ], 200);
        }
    }

    /**
     * Mostrar un seguimiento específico
     */
    public function show(int $id)
    {
        try {
            $seguimiento = Seguimiento::with(['movimientoStock', 'repartidor:id,nombre,apellido'])
                ->find($id);

            if (!$seguimiento) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Seguimiento no encontrado.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'status' => true,
                'mensaje' => 'Seguimiento obtenido exitosamente.',
                'data' => $seguimiento,
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
     * Actualizar un seguimiento
     */
    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'ubicacion' => ['nullable', 'array'],
            'ubicacion.lat' => ['required_with:ubicacion', 'numeric'],
            'ubicacion.lng' => ['required_with:ubicacion', 'numeric'],
            'ubicacion.direccion' => ['nullable', 'string', 'max:500'],
            'estado' => ['nullable', 'string', 'in:despachado,en_camino,entregado'],
            'status' => ['nullable', 'string', 'in:activo,completado'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $seguimiento = Seguimiento::find($id);

            if (!$seguimiento) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Seguimiento no encontrado.',
                    'data' => null,
                ], 404);
            }

            $seguimiento->update(array_filter($data, fn($value) => $value !== null));

            return response()->json([
                'status' => true,
                'mensaje' => 'Seguimiento actualizado exitosamente.',
                'data' => $seguimiento->load(['movimientoStock', 'repartidor:id,nombre,apellido']),
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al actualizar el seguimiento.',
                'data' => null,
            ], 200);
        }
    }

    /**
     * Eliminar un seguimiento
     */
    public function destroy(int $id)
    {
        try {
            $seguimiento = Seguimiento::find($id);

            if (!$seguimiento) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Seguimiento no encontrado.',
                    'data' => null,
                ], 404);
            }

            $seguimiento->delete();

            return response()->json([
                'status' => true,
                'mensaje' => 'Seguimiento eliminado exitosamente.',
                'data' => null,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al eliminar el seguimiento.',
                'data' => null,
            ], 200);
        }
    }

    /**
     * Obtener seguimientos por movimiento
     */
    public function porMovimiento(int $movimientoStockId)
    {
        try {
            $seguimientos = Seguimiento::where('movimiento_stock_id', $movimientoStockId)
                ->with('repartidor:id,nombre,apellido')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'status' => true,
                'mensaje' => 'Seguimientos del movimiento obtenidos exitosamente.',
                'data' => $seguimientos,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al obtener los seguimientos.',
                'data' => null,
            ], 200);
        }
    }
}
