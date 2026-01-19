<?php

namespace App\Http\Controllers;

use App\Models\AlmacenServiciosAtenciones;
use Illuminate\Http\Request;

class AlmacenServiciosAtencionesController extends Controller
{
    public function index(Request $request)
    {
        $statusParam = $request->query('status', 'true');
        $query = AlmacenServiciosAtenciones::query();
        if ($statusParam !== 'all' && $statusParam !== 'todos') {
            $statusBool = in_array(strtolower((string)$statusParam), ['true','1','activo'], true);
            $query->where('status', $statusBool);
        }
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
            'cantidad' => ['required','integer','min:0'],
            'sede_id' => ['required','integer','exists:sedes,id'],
            'lote_id' => ['required','integer','exists:lotes,id'],
            'hospital_id' => ['required','integer','exists:hospitales,id'],
            'status' => ['nullable','boolean'],
        ]);

        $item = new AlmacenServiciosAtenciones();
        $item->cantidad = $data['cantidad'];
        $item->sede_id = $data['sede_id'];
        $item->lote_id = $data['lote_id'];
        $item->hospital_id = $data['hospital_id'];
        $item->status = array_key_exists('status', $data) ? (bool)$data['status'] : true;
        $item->save();
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
            'cantidad' => ['sometimes','required','integer','min:0'],
            'sede_id' => ['sometimes','required','integer','exists:sedes,id'],
            'lote_id' => ['sometimes','required','integer','exists:lotes,id'],
            'hospital_id' => ['sometimes','required','integer','exists:hospitales,id'],
            'status' => ['nullable','boolean'],
        ]);
        if (array_key_exists('cantidad', $data)) { $almacenes_servicios_atencione->cantidad = $data['cantidad']; }
        if (array_key_exists('sede_id', $data)) { $almacenes_servicios_atencione->sede_id = $data['sede_id']; }
        if (array_key_exists('lote_id', $data)) { $almacenes_servicios_atencione->lote_id = $data['lote_id']; }
        if (array_key_exists('hospital_id', $data)) { $almacenes_servicios_atencione->hospital_id = $data['hospital_id']; }
        if (array_key_exists('status', $data)) { $almacenes_servicios_atencione->status = (bool)$data['status']; }
        $almacenes_servicios_atencione->save();
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
