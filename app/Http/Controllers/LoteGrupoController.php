<?php

namespace App\Http\Controllers;

use App\Models\LoteGrupo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LoteGrupoController extends Controller
{
    /**
     * Listar grupos de lote con filtros y paginación
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoteGrupo::with('lote');

        // Filtros
        if ($request->has('codigo')) {
            $query->where('codigo', 'like', '%' . $request->codigo . '%');
        }

        if ($request->has('lote_id')) {
            $query->where('lote_id', $request->lote_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortBy, ['codigo', 'cantidad', 'status', 'created_at'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $grupos = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'mensaje' => 'Grupos de lote obtenidos exitosamente',
            'data' => $grupos,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ver detalle de un grupo específico
     */
    public function show(string $codigo): JsonResponse
    {
        $grupo = LoteGrupo::where('codigo', $codigo)
            ->with('lote')
            ->get();

        if ($grupo->isEmpty()) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Grupo de lote no encontrado',
                'data' => null,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'status' => true,
            'mensaje' => 'Grupo de lote obtenido exitosamente',
            'data' => [
                'codigo' => $codigo,
                'items' => $grupo->toArray(),
                'total_items' => $grupo->count(),
                'cantidad_total' => $grupo->sum('cantidad'),
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Crear un nuevo grupo de lote
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lote_id' => 'required|integer|exists:lotes,id',
            'cantidad' => 'required|integer|min:1',
            'codigo' => 'nullable|string|max:20|unique:lotes_grupos,codigo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            $codigo = $request->codigo ?? LoteGrupo::generarCodigo();

            $loteGrupo = LoteGrupo::create([
                'codigo' => $codigo,
                'lote_id' => $request->lote_id,
                'cantidad' => $request->cantidad,
                'status' => 'activo',
            ]);

            return response()->json([
                'status' => true,
                'mensaje' => 'Grupo de lote creado exitosamente',
                'data' => $loteGrupo->load('lote'),
            ], 201, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al crear el grupo de lote',
                'error' => $e->getMessage(),
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Crear grupo desde items de movimiento
     */
    public function crearDesdeMovimiento(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.lote_id' => 'required|integer|exists:lotes,id',
            'items.*.cantidad' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            DB::transaction(function () use ($request, &$codigo, &$grupoItems) {
                [$codigo, $grupoItems] = LoteGrupo::crearGrupo($request->items);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Grupo de lote creado exitosamente desde movimiento',
                'data' => [
                    'codigo' => $codigo,
                    'items' => $grupoItems,
                    'total_items' => count($grupoItems),
                ],
            ], 201, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al crear el grupo de lote',
                'error' => $e->getMessage(),
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Actualizar un grupo de lote
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $loteGrupo = LoteGrupo::find($id);

        if (!$loteGrupo) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Grupo de lote no encontrado',
                'data' => null,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $validator = Validator::make($request->all(), [
            'cantidad' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:activo,inactivo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            $loteGrupo->update($request->only(['cantidad', 'status']));

            return response()->json([
                'status' => true,
                'mensaje' => 'Grupo de lote actualizado exitosamente',
                'data' => $loteGrupo->load('lote'),
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al actualizar el grupo de lote',
                'error' => $e->getMessage(),
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Eliminar un grupo de lote (cambiar status a inactivo)
     */
    public function destroy(int $id): JsonResponse
    {
        $loteGrupo = LoteGrupo::find($id);

        if (!$loteGrupo) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Grupo de lote no encontrado',
                'data' => null,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            $loteGrupo->update(['status' => 'inactivo']);

            return response()->json([
                'status' => true,
                'mensaje' => 'Grupo de lote eliminado exitosamente',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al eliminar el grupo de lote',
                'error' => $e->getMessage(),
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Obtener estadísticas de grupos
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $query = LoteGrupo::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $estadisticas = [
            'total_grupos' => (clone $query)->distinct('codigo')->count('codigo'),
            'total_items' => (clone $query)->count(),
            'cantidad_total' => (clone $query)->sum('cantidad'),
            'grupos_activos' => (clone $query)->where('status', 'activo')->distinct('codigo')->count('codigo'),
            'grupos_inactivos' => (clone $query)->where('status', 'inactivo')->distinct('codigo')->count('codigo'),
        ];

        return response()->json([
            'status' => true,
            'mensaje' => 'Estadísticas obtenidas exitosamente',
            'data' => $estadisticas,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
