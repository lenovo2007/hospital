<?php

namespace App\Http\Controllers;

use App\Models\AlmacenServiciosAtenciones;
use Illuminate\Http\Request;

class AlmacenServiciosAtencionesController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) { $status = 'activo'; }
        $query = AlmacenServiciosAtenciones::query();
        if ($status !== 'all') { $query->where('status', $status); }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de almacenes servicios de atenciones.' : 'almacenes_servicios_atenciones no encontrado';
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
        $item = AlmacenServiciosAtenciones::create($data);
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén servicios de atenciones creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $item = AlmacenServiciosAtenciones::find($id);
        if (!$item) {
            return response()->json([
                'status' => true,
                'mensaje' => 'almacenes_servicios_atenciones no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de almacén servicios de atenciones.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, AlmacenServiciosAtenciones $almacenes_servicios_atencione)
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
        $almacenes_servicios_atencione->update($data);
        $almacenes_servicios_atencione->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén servicios de atenciones actualizado.',
            'data' => $almacenes_servicios_atencione,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(AlmacenServiciosAtenciones $almacenes_servicios_atencione)
    {
        $almacenes_servicios_atencione->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén servicios de atenciones eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
