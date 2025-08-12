<?php

namespace App\Http\Controllers;

use App\Models\Farmacia;
use Illuminate\Http\Request;

class FarmaciaController extends Controller
{
    public function index()
    {
        $items = Farmacia::latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de farmacias.' : 'farmacias no encontrado';
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
            'status' => ['nullable','in:activo,inactivo'],
        ]);
        if (!isset($data['status'])) { $data['status'] = 'activo'; }
        $item = Farmacia::create($data);
        return response()->json([
            'status' => true,
            'mensaje' => 'Farmacia creada.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $farmacia = Farmacia::find($id);
        if (!$farmacia) {
            return response()->json([
                'status' => true,
                'mensaje' => 'farmacias no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de farmacia.',
            'data' => $farmacia,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, Farmacia $farmacia)
    {
        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);
        $farmacia->update($data);
        $farmacia->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Farmacia actualizada.',
            'data' => $farmacia,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Farmacia $farmacia)
    {
        $farmacia->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Farmacia eliminada.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
