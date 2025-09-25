<?php

namespace App\Http\Controllers;

use App\Models\AlmacenCentral;
use Illuminate\Http\Request;

class AlmacenCentralController extends Controller
{
    public function index(Request $request)
    {
        $statusParam = $request->query('status', 'true');
        $query = AlmacenCentral::query();
        if ($statusParam !== 'all' && $statusParam !== 'todos') {
            // Map legacy values ("activo"/"inactivo") and booleans/ints
            $statusBool = in_array(strtolower((string)$statusParam), ['true','1','activo'], true) ? true : false;
            $query->where('status', $statusBool);
        }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de almacenes centrales.' : 'almacenes_centrales no encontrado';
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

        $item = new AlmacenCentral();
        $item->cantidad = $data['cantidad'];
        $item->sede_id = $data['sede_id'];
        $item->lote_id = $data['lote_id'];
        $item->hospital_id = $data['hospital_id'];
        $item->status = array_key_exists('status', $data) ? (bool)$data['status'] : true;
        $item->save();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén central creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $central = AlmacenCentral::find($id);
        if (!$central) {
            return response()->json([
                'status' => true,
                'mensaje' => 'almacenes_centrales no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de almacén central.',
            'data' => $central,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, AlmacenCentral $almacenes_centrale)
    {
        $data = $request->validate([
            'cantidad' => ['sometimes','required','integer','min:0'],
            'sede_id' => ['sometimes','required','integer','exists:sedes,id'],
            'lote_id' => ['sometimes','required','integer','exists:lotes,id'],
            'hospital_id' => ['sometimes','required','integer','exists:hospitales,id'],
            'status' => ['nullable','boolean'],
        ]);
        if (array_key_exists('cantidad', $data)) $almacenes_centrale->cantidad = $data['cantidad'];
        if (array_key_exists('sede_id', $data)) $almacenes_centrale->sede_id = $data['sede_id'];
        if (array_key_exists('lote_id', $data)) $almacenes_centrale->lote_id = $data['lote_id'];
        if (array_key_exists('hospital_id', $data)) $almacenes_centrale->hospital_id = $data['hospital_id'];
        if (array_key_exists('status', $data)) $almacenes_centrale->status = (bool)$data['status'];
        $almacenes_centrale->save();
        $almacenes_centrale->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén central actualizado.',
            'data' => $almacenes_centrale,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(AlmacenCentral $almacenes_centrale)
    {
        $almacenes_centrale->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén central eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
