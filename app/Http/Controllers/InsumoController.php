<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InsumoController extends Controller
{
    // GET /api/insumos
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) {
            $status = 'activo';
        }
        $query = Insumo::query();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de insumos.' : 'insumos no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/insumos/{insumo}
    public function show(Insumo $insumo)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de insumo.',
            'data' => $insumo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/insumos
    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required','string','max:100','unique:insumos,codigo'],
            'nombre' => ['required','string','max:255'],
            'tipo' => ['required','string','max:100'],
            'unidad_medida' => ['required','string','max:100'],
            'cantidad_por_paquete' => ['required','integer','min:0'],
            'descripcion' => ['nullable','string'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'codigo.unique' => 'El código de insumo ya ha sido registrado previamente.',
        ]);

        $insumo = Insumo::create($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Insumo creado.',
            'data' => $insumo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/insumos/{insumo}
    public function update(Request $request, Insumo $insumo)
    {
        $data = $request->validate([
            'codigo' => ['sometimes','required','string','max:100', Rule::unique('insumos','codigo')->ignore($insumo->id)],
            'nombre' => ['sometimes','required','string','max:255'],
            'tipo' => ['sometimes','required','string','max:100'],
            'unidad_medida' => ['sometimes','required','string','max:100'],
            'cantidad_por_paquete' => ['sometimes','required','integer','min:0'],
            'descripcion' => ['nullable','string'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'codigo.unique' => 'El código de insumo ya ha sido registrado para otro insumo.',
        ]);

        $insumo->update($data);
        $insumo->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Insumo actualizado.',
            'data' => $insumo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/insumos/{insumo}
    public function destroy(Insumo $insumo)
    {
        $insumo->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Insumo eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
