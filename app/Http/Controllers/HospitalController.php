<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Http\Request;

class HospitalController extends Controller
{
    // GET /api/hospitales
    public function index()
    {
        $items = Hospital::latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de hospitales.' : 'hospitales no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/hospitales/buscar_por_rif?rif=J-12345678-9
    public function buscarPorRif(Request $request)
    {
        $data = $request->validate([
            'rif' => ['required','string','max:255'],
        ]);

        $rif = $data['rif'];

        $hospital = Hospital::where('rif', $rif)->first();

        if (!$hospital) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Hospital no encontrado por ese RIF.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital encontrado.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/hospitales/actualizar_por_rif?rif=J-12345678-9
    public function actualizarPorRif(Request $request)
    {
        $validated = $request->validate([
            'rif' => ['required','string','max:255'],
        ]);

        $hospital = Hospital::where('rif', $validated['rif'])->first();

        if (!$hospital) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Hospital no encontrado por ese RIF.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'tipo' => ['sometimes','required','string','max:255'],
        ]);

        // Evitar que cambien el RIF desde este endpoint
        unset($data['rif']);

        $hospital->update($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital actualizado por RIF.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/hospitales
    public function store(Request $request)
    {
        \Log::info('HospitalController@store: request received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'user_id' => optional($request->user())->id,
        ]);
        $data = $request->validate([
            'nombre' => ['required','string','max:255'],
            'rif' => ['required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'tipo' => ['required','string','max:255'],
        ]);

        \Log::info('HospitalController@store: data validated', $data);
        $item = Hospital::create($data);

        \Log::info('HospitalController@store: hospital created', ['id' => $item->id]);
        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/hospitales/{id}
    public function show($id)
    {
        $hospital = Hospital::find($id);
        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'hospitales no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de hospital.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/hospitales/{hospital}
    public function update(Request $request, Hospital $hospital)
    {
        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'rif' => ['sometimes','required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
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
