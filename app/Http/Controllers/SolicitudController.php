<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Illuminate\Http\Request;

class SolicitudController extends Controller
{
    /**
     * Display a listing of all solicitudes.
     */
    public function index()
    {
        $solicitudes = Solicitud::with('hospital', 'sede')->latest()->paginate(15);
        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de solicitudes.',
            'data' => $solicitudes,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Store a newly created solicitud.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo_solicitud' => ['required', 'in:insumo,servicio,mantenimiento,otro'],
            'descripcion' => ['required', 'string'],
            'prioridad' => ['nullable', 'in:baja,media,alta,urgente'],
            'fecha' => ['required', 'date'],
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'hospital_id' => ['required', 'integer', 'exists:hospitales,id'],
            'status' => ['nullable', 'in:pendiente,en_proceso,completada,cancelada'],
        ]);

        if (!isset($data['prioridad'])) {
            $data['prioridad'] = 'media';
        }
        if (!isset($data['status'])) {
            $data['status'] = 'pendiente';
        }

        $solicitud = Solicitud::create($data);
        $solicitud->load('hospital', 'sede');

        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitud creada exitosamente.',
            'data' => $solicitud,
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Display the specified solicitud.
     */
    public function show(string $id)
    {
        $solicitud = Solicitud::with('hospital', 'sede')->find($id);
        if (!$solicitud) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Solicitud no encontrada.',
                'data' => null,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de solicitud.',
            'data' => $solicitud,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Update the specified solicitud.
     */
    public function update(Request $request, string $id)
    {
        $solicitud = Solicitud::find($id);
        if (!$solicitud) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Solicitud no encontrada.',
                'data' => null,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            'tipo_solicitud' => ['sometimes', 'in:insumo,servicio,mantenimiento,otro'],
            'descripcion' => ['sometimes', 'string'],
            'prioridad' => ['sometimes', 'in:baja,media,alta,urgente'],
            'fecha' => ['sometimes', 'date'],
            'sede_id' => ['sometimes', 'integer', 'exists:sedes,id'],
            'hospital_id' => ['sometimes', 'integer', 'exists:hospitales,id'],
            'status' => ['sometimes', 'in:pendiente,en_proceso,completada,cancelada'],
        ]);

        $solicitud->update($data);
        $solicitud->load('hospital', 'sede');

        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitud actualizada exitosamente.',
            'data' => $solicitud,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Remove the specified solicitud.
     */
    public function destroy(string $id)
    {
        $solicitud = Solicitud::find($id);
        if (!$solicitud) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Solicitud no encontrada.',
                'data' => null,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $solicitud->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitud eliminada exitosamente.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get solicitudes by sede_id.
     */
    public function bySede(string $sede_id)
    {
        $solicitudes = Solicitud::with('hospital', 'sede')
            ->where('sede_id', $sede_id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitudes por sede.',
            'data' => $solicitudes,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get solicitudes by hospital_id.
     */
    public function byHospital(string $hospital_id)
    {
        $solicitudes = Solicitud::with('hospital', 'sede')
            ->where('hospital_id', $hospital_id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitudes por hospital.',
            'data' => $solicitudes,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
