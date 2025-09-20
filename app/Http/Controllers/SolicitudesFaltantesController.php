<?php

namespace App\Http\Controllers;

use App\Models\SolicitudFaltante;
use Illuminate\Http\Request;

class SolicitudesFaltantesController extends Controller
{
    // GET /api/solicitudes_faltantes
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $query = SolicitudFaltante::query()
            ->when($request->filled('hospital_id'), fn($q) => $q->where('hospital_id', $request->hospital_id))
            ->when($request->filled('almacen_tipo'), fn($q) => $q->where('almacen_tipo', $request->almacen_tipo))
            ->when($request->filled('almacen_id'), fn($q) => $q->where('almacen_id', $request->almacen_id))
            ->when($request->filled('insumo_id'), fn($q) => $q->where('insumo_id', $request->insumo_id))
            ->when($request->filled('estado'), fn($q) => $q->where('estado', $request->estado))
            ->latest('created_at');
        $items = $query->paginate($perPage);
        return response()->json([
            'status' => true,
            'mensaje' => $items->total() ? 'Listado de solicitudes.' : 'No hay solicitudes.',
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/solicitudes_faltantes
    public function store(Request $request)
    {
        $data = $request->validate([
            'hospital_id' => ['required','exists:hospitales,id'],
            'almacen_tipo' => ['required','string','max:100'],
            'almacen_id' => ['required','integer','min:1'],
            'insumo_id' => ['required','exists:insumos,id'],
            'cantidad_sugerida' => ['nullable','integer','min:1'],
            'prioridad' => ['nullable','in:baja,media,alta'],
            'comentario' => ['nullable','string'],
        ]);
        $data['user_id'] = (int) $request->user()->id;
        $data['prioridad'] = $data['prioridad'] ?? 'media';
        $data['estado'] = 'pendiente';
        $item = SolicitudFaltante::create($data);
        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitud creada.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PATCH /api/solicitudes_faltantes/{solicitud}
    public function update(Request $request, SolicitudFaltante $solicitud)
    {
        $data = $request->validate([
            'estado' => ['required','in:pendiente,atendida,cancelada'],
            'comentario' => ['nullable','string'],
        ]);
        $solicitud->update($data);
        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitud actualizada.',
            'data' => $solicitud,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
