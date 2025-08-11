<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Http\Request;

class HospitalController extends Controller
{
    // GET /api/hospitals
    public function index()
    {
        $items = Hospital::latest()->paginate(15);
        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de hospitales.',
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/hospitals
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required','string','max:255'],
            'rif' => ['required','string','max:255'],
            'lat' => ['required','numeric','between:-90,90'],
            'lon' => ['required','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'tipo' => ['required','string','max:255'],
        ]);

        $item = Hospital::create($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/hospitals/{hospital}
    public function show(Hospital $hospital)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de hospital.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/hospitals/{hospital}
    public function update(Request $request, Hospital $hospital)
    {
        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'rif' => ['sometimes','required','string','max:255'],
            'lat' => ['sometimes','required','numeric','between:-90,90'],
            'lon' => ['sometimes','required','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'tipo' => ['sometimes','required','string','max:255'],
        ]);

        $hospital->update($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital actualizado.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/hospitals/{hospital}
    public function destroy(Hospital $hospital)
    {
        $hospital->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
