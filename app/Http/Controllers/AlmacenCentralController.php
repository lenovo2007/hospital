<?php

namespace App\Http\Controllers;

use App\Models\AlmacenCentral;
use Illuminate\Http\Request;

class AlmacenCentralController extends Controller
{
    public function index()
    {
        $items = AlmacenCentral::latest()->paginate(15);
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
            'nombre' => ['required','string','max:255'],
        ]);
        $item = AlmacenCentral::create($data);
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
            'nombre' => ['sometimes','required','string','max:255'],
        ]);
        $almacenes_centrale->update($data);
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
