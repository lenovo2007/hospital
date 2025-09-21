<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\LoteAlmacen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    /**
     * Registra un nuevo lote y lo asigna a un almacén específico.
     * 
     * @bodyParam insumo_id int required ID del insumo. Example: 1
     * @bodyParam lote_cod string required Código de lote. Example: "LOT-2024-001"
     * @bodyParam fecha_vencimiento string required Fecha de vencimiento (YYYY-MM-DD). Example: "2024-12-31"
     * @bodyParam almacen_tipo string required Tipo de almacén (ej: 'farmacia', 'principal'). Example: "farmacia"
     * @bodyParam almacen_id int required ID del almacén específico. Example: 1
     * @bodyParam cantidad int required Cantidad a registrar. Example: 100
     * @bodyParam hospital_id int required ID del hospital. Example: 1
     * @bodyParam sede_id int required ID de la sede. Example: 1
     * 
     * @response 200 {
     *     "status": true,
     *     "mensaje": "Inventario registrado exitosamente",
     *     "data": {
     *         "lote_id": 1,
     *         "lote_almacen_id": 1
     *     }
     * }
     */
    public function registrar(Request $request)
    {
        $validated = $request->validate([
            'insumo_id' => 'required|exists:insumos,id',
            'lote_cod' => 'required|string|max:100',
            'fecha_vencimiento' => 'required|date_format:Y-m-d',
            'almacen_tipo' => 'required|string|in:farmacia,principal,central,servicios_apoyo,servicios_atenciones',
            'almacen_id' => 'required|integer|min:1',
            'cantidad' => 'required|integer|min:1',
            'hospital_id' => 'required|exists:hospitales,id',
            'sede_id' => 'required|exists:sedes,id',
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Registrar el lote
            $lote = Lote::create([
                'id_insumo' => $validated['insumo_id'],
                'numero_lote' => $validated['lote_cod'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'],
                'fecha_ingreso' => now(),
                'hospital_id' => $validated['hospital_id']
            ]);

            // 2. Registrar en lotes_almacenes
            $loteAlmacen = LoteAlmacen::create([
                'lote_id' => $lote->id,
                'almacen_tipo' => $validated['almacen_tipo'],
                'almacen_id' => $validated['almacen_id'],
                'cantidad' => $validated['cantidad'],
                'hospital_id' => $validated['hospital_id'],
                'sede_id' => $validated['sede_id'],
            ]);

            // 3. Registrar en tabla de almacén específica (opcional, si es necesario)
            // $this->registrarEnAlmacenEspecifico($validated, $lote->id);

            return response()->json([
                'status' => true,
                'mensaje' => 'Inventario registrado exitosamente',
                'data' => [
                    'lote_id' => $lote->id,
                    'lote_almacen_id' => $loteAlmacen->id
                ]
            ], 201);
        });
    }

    // Método opcional para registrar en tablas específicas de almacén
    protected function registrarEnAlmacenEspecifico($data, $loteId)
    {
        $tabla = match($data['almacen_tipo']) {
            'farmacia' => 'almacenes_farmacia',
            'principal' => 'almacenes_principales',
            'central' => 'almacenes_centrales',
            'servicios_apoyo' => 'almacenes_servicios_apoyo',
            'servicios_atenciones' => 'almacenes_servicios_atenciones',
            default => null,
        };

        if ($tabla) {
            DB::table($tabla)->insert([
                'lote_id' => $loteId,
                'cantidad' => $data['cantidad'],
                'hospital_id' => $data['hospital_id'],
                'sede_id' => $data['sede_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
