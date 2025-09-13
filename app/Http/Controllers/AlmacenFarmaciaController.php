<?php

namespace App\Http\Controllers;

use App\Models\AlmacenFarmacia;
use Illuminate\Http\Request;

class AlmacenFarmaciaController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) { $status = 'activo'; }
        $query = AlmacenFarmacia::query();
        if ($status !== 'all') { $query->where('status', $status); }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de almacenes farmacia.' : 'almacenes_farmacia no encontrado';
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
        $item = AlmacenFarmacia::create($data);
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén farmacia creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $item = AlmacenFarmacia::find($id);
        if (!$item) {
            return response()->json([
                'status' => true,
                'mensaje' => 'almacenes_farmacia no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de almacén farmacia.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, AlmacenFarmacia $almacenes_farmacium)
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
        $almacenes_farmacium->update($data);
        $almacenes_farmacium->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén farmacia actualizado.',
            'data' => $almacenes_farmacium,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(AlmacenFarmacia $almacenes_farmacium)
    {
        $almacenes_farmacium->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén farmacia eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
