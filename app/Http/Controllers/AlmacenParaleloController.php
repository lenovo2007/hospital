<?php

namespace App\Http\Controllers;

use App\Models\AlmacenParalelo;
use Illuminate\Http\Request;

class AlmacenParaleloController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) { $status = 'activo'; }
        $query = AlmacenParalelo::query();
        if ($status !== 'all') { $query->where('status', $status); }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de almacenes paralelo.' : 'almacenes_paralelo no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'insumos' => ['required','string','max:255'],
            'codigo' => ['required','string','max:100'],
            'numero_lote' => ['required','string','max:100'],
            'fecha_vencimiento' => ['required','date'],
            'fecha_ingreso' => ['required','date'],
            'cantidad' => ['required','integer','min:0'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);
        if (!isset($data['status'])) { $data['status'] = 'activo'; }
        $item = AlmacenParalelo::create($data);
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén paralelo creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $item = AlmacenParalelo::find($id);
        if (!$item) {
            return response()->json([
                'status' => true,
                'mensaje' => 'almacenes_paralelo no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de almacén paralelo.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, AlmacenParalelo $almacenes_paralelo)
    {
        $data = $request->validate([
            'insumos' => ['sometimes','required','string','max:255'],
            'codigo' => ['sometimes','required','string','max:100'],
            'numero_lote' => ['sometimes','required','string','max:100'],
            'fecha_vencimiento' => ['sometimes','required','date'],
            'fecha_ingreso' => ['sometimes','required','date'],
            'cantidad' => ['sometimes','required','integer','min:0'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);
        $almacenes_paralelo->update($data);
        $almacenes_paralelo->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén paralelo actualizado.',
            'data' => $almacenes_paralelo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(AlmacenParalelo $almacenes_paralelo)
    {
        $almacenes_paralelo->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén paralelo eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
