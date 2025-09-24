<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\LoteAlmacen;
use App\Models\AlmacenCentral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            'fecha_ingreso' => 'nullable|date_format:Y-m-d',
            // Nuevos tipos de almacén según requerimiento
            'almacen_tipo' => 'required|string|in:almacenCent,almacenPrin,almacenFarm,almacenPar,almacenServAtenciones,almacenServApoyo',
            // Se deja de requerir almacen_id explícito; se usará sede_id como identificador físico
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
                'fecha_ingreso' => $validated['fecha_ingreso'] ?? now(),
                'hospital_id' => $validated['hospital_id']
            ]);

            // 2. Registrar en lotes_almacenes
            // Detectar el nombre correcto de columna para el tipo de almacén según el esquema
            $tipoCol = Schema::hasColumn('lotes_almacenes', 'almacen_tipo')
                ? 'almacen_tipo'
                : (Schema::hasColumn('lotes_almacenes', 'tipo_almacen') ? 'tipo_almacen' : null);

            if ($tipoCol === null) {
                throw new \RuntimeException('No se encontró columna de tipo de almacén (almacen_tipo/tipo_almacen) en lotes_almacenes');
            }

            $payload = [
                'lote_id' => $lote->id,
                // Usar sede_id como identificador físico (reemplaza a almacen_id)
                'sede_id' => $validated['sede_id'],
                'cantidad' => $validated['cantidad'],
                'hospital_id' => $validated['hospital_id'],
            ];
            $payload[$tipoCol] = $validated['almacen_tipo'];

            // Compatibilidad con esquemas donde 'almacen_id' es NOT NULL
            if (Schema::hasColumn('lotes_almacenes', 'almacen_id')) {
                $payload['almacen_id'] = $validated['sede_id'];
            }

            $loteAlmacen = LoteAlmacen::create($payload);

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

    /**
     * Obtiene el inventario agrupado por insumo_id para una sede específica.
     * 
     * @urlParam sede_id int required ID de la sede. Example: 1
     * 
     * @response 200 {
     *     "status": true,
     *     "data": [
     *         {
     *             "insumo_id": 1,
     *             "codigo": "INS-001",
     *             "nombre": "Insumo Ejemplo",
     *             "cantidad_total": 150,
     *             "lotes": [
     *                 {
     *                     "lote_id": 1,
     *                     "numero_lote": "LOT-2024-001",
     *                     "fecha_vencimiento": "2024-12-31",
     *                     "cantidad": 100
     *                 },
     *                 {
     *                     "lote_id": 2,
     *                     "numero_lote": "LOT-2024-002",
     *                     "fecha_vencimiento": "2024-11-30",
     *                     "cantidad": 50
     *                 }
     *             ]
     *         }
     *     ]
     * }
     */
    public function listarPorSede($sedeId)
    {
        try {
            // Detectar nombres de columna según el esquema real
            $tabla = 'almacenes_centrales';
            if (!Schema::hasTable($tabla)) {
                throw new \RuntimeException("No se encontró la tabla $tabla");
            }
            $insumoCol = Schema::hasColumn($tabla, 'insumos')
                ? 'insumos'
                : (Schema::hasColumn($tabla, 'insumo_id') ? 'insumo_id' : null);
            $sedeCol = Schema::hasColumn($tabla, 'sede_id')
                ? 'sede_id'
                : (Schema::hasColumn($tabla, 'sede') ? 'sede' : null);
            $loteCol = Schema::hasColumn($tabla, 'lote_id') ? 'lote_id' : null;

            if ($insumoCol === null || $sedeCol === null || $loteCol === null) {
                $faltantes = [];
                if ($insumoCol === null) { $faltantes[] = 'insumo (insumos/insumo_id)'; }
                if ($sedeCol === null) { $faltantes[] = 'sede (sede_id/sede)'; }
                if ($loteCol === null) { $faltantes[] = 'lote (lote_id)'; }
                throw new \RuntimeException('No se encontraron columnas requeridas en ' . $tabla . ': ' . implode(', ', $faltantes));
            }

            // 1) Totales por insumo en la sede
            $insumos = DB::table($tabla)
                ->join('insumos', "$tabla.$insumoCol", '=', 'insumos.id')
                ->where("$tabla.$sedeCol", $sedeId)
                ->where("$tabla.status", true)
                ->select(
                    'insumos.id as insumo_id',
                    'insumos.codigo',
                    'insumos.nombre',
                    DB::raw("SUM($tabla.cantidad) as cantidad_total")
                )
                ->groupBy('insumos.id', 'insumos.codigo', 'insumos.nombre')
                ->get();

            if ($insumos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No hay registros de inventario para la sede especificada.',
                    'data' => [],
                ], 200);
            }

            // 2) Lotes por insumo en la sede
            $insumoIds = $insumos->pluck('insumo_id')->all();

            $lotesPorInsumo = DB::table($tabla)
                ->join('lotes', "$tabla.$loteCol", '=', 'lotes.id')
                ->where("$tabla.$sedeCol", $sedeId)
                ->where("$tabla.status", true)
                ->whereIn("$tabla.$insumoCol", $insumoIds)
                ->select(
                    DB::raw("$tabla.$insumoCol as insumo_id"),
                    'lotes.id as lote_id',
                    'lotes.numero_lote',
                    'lotes.fecha_vencimiento',
                    DB::raw("$tabla.cantidad as cantidad")
                )
                ->get()
                ->groupBy('insumo_id');

            // 3) Armar respuesta
            $data = $insumos->map(function ($row) use ($lotesPorInsumo) {
                $row->lotes = array_values(optional($lotesPorInsumo->get($row->insumo_id))->toArray() ?? []);
                return $row;
            });

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (Throwable $e) {
            Log::error('Error en listarPorSede', [
                'exception' => $e,
                'sedeId' => $sedeId,
            ]);
            // En entorno local, incluir mensaje de error para diagnóstico
            $mensaje = 'Error al listar inventario por sede';
            if (app()->environment('local')) {
                $mensaje .= ': ' . $e->getMessage();
            }
            return response()->json([
                'status' => false,
                'mensaje' => $mensaje,
                'data' => null,
            ], 200);
        }
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
