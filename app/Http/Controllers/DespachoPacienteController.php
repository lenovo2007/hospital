<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Models\LoteGrupo;
use App\Models\DespachoPaciente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class DespachoPacienteController extends Controller
{
    /**
     * Despacha insumos directamente a un paciente desde el almacén de la sede
     * 
     * POST /api/despacho/paciente
     * {
     *   "origen_hospital_id": 1,
     *   "origen_sede_id": 2,
     *   "tipo_movimiento": "salida_paciente",
     *   "fecha_despacho": "2025-10-04",
     *   "observaciones": "Medicamentos para tratamiento",
     *   "paciente": {
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez González",
     *     "cedula": "12345678"
     *   },
     *   "items": [
     *     {"lote_id": 1, "cantidad": 200},
     *     {"lote_id": 2, "cantidad": 100}
     *   ]
     * }
     */
    public function despachar(Request $request)
    {
        $data = $request->validate([
            'hospital_id' => ['required', 'integer', 'min:1'],
            'sede_id' => ['required', 'integer', 'min:1'],
            'fecha_despacho' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'paciente_nombres' => ['required', 'string', 'max:100'],
            'paciente_apellidos' => ['required', 'string', 'max:100'],
            'paciente_cedula' => ['required', 'string', 'max:20'],
            'paciente_telefono' => ['nullable', 'string', 'max:20'],
            'paciente_direccion' => ['nullable', 'string', 'max:500'],
            'medico_tratante' => ['nullable', 'string', 'max:150'],
            'diagnostico' => ['nullable', 'string', 'max:200'],
            'indicaciones_medicas' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.lote_id' => ['required', 'integer', 'min:1'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) $request->user()->id;
        $codigoGrupo = null;

        try {
            DB::transaction(function () use ($data, $userId, &$codigoGrupo) {
                // Obtener información de la sede para determinar el tipo de almacén
                $sede = DB::table('sedes')->where('id', $data['sede_id'])->first();
                if (!$sede) {
                    throw new InvalidArgumentException('Sede no encontrada.');
                }

                // Determinar la tabla de almacén según el tipo
                $tipoAlmacen = $sede->tipo_almacen;
                $tablaAlmacen = $this->obtenerTablaAlmacen($tipoAlmacen);

                // Generar código único de despacho
                $codigoDespacho = DespachoPaciente::generarCodigoDespacho();

                // Crear grupo de lote para los items del movimiento
                [$codigoGrupo, $grupoItems] = LoteGrupo::crearGrupo($data['items']);

                // Calcular la suma total de cantidades y descontar del almacén
                $totalCantidad = 0;
                foreach ($data['items'] as $item) {
                    $loteId = (int) $item['lote_id'];
                    $cantidad = (int) $item['cantidad'];
                    
                    // Descontar del almacén de la sede
                    $this->descontarDelAlmacen(
                        $tablaAlmacen,
                        (int) $data['hospital_id'],
                        (int) $data['sede_id'],
                        $loteId,
                        $cantidad
                    );
                    $totalCantidad += $cantidad;
                }

                // Crear el despacho a paciente
                $despacho = DespachoPaciente::create([
                    'hospital_id' => (int) $data['hospital_id'],
                    'sede_id' => (int) $data['sede_id'],
                    'almacen_tipo' => $tipoAlmacen,
                    'fecha_despacho' => $data['fecha_despacho'],
                    'observaciones' => $data['observaciones'] ?? null,
                    'paciente_nombres' => $data['paciente_nombres'],
                    'paciente_apellidos' => $data['paciente_apellidos'],
                    'paciente_cedula' => $data['paciente_cedula'],
                    'paciente_telefono' => $data['paciente_telefono'] ?? null,
                    'paciente_direccion' => $data['paciente_direccion'] ?? null,
                    'medico_tratante' => $data['medico_tratante'] ?? null,
                    'diagnostico' => $data['diagnostico'] ?? null,
                    'indicaciones_medicas' => $data['indicaciones_medicas'] ?? null,
                    'codigo_despacho' => $codigoDespacho,
                    'cantidad_total_items' => $totalCantidad,
                    'estado' => 'despachado',
                    'user_id' => $userId,
                ]);

                $codigoGrupo = $codigoDespacho; // Para la respuesta
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Despacho a paciente registrado exitosamente.',
                'data' => [
                    'codigo_despacho' => $codigoGrupo,
                    'paciente' => [
                        'nombres' => $data['paciente_nombres'],
                        'apellidos' => $data['paciente_apellidos'],
                        'cedula' => $data['paciente_cedula'],
                    ],
                    'total_items' => count($data['items']),
                ],
            ], 200);

        } catch (StockException $e) {
            Log::warning('Despacho a paciente falló por StockException', [
                'mensaje' => $e->getMessage(),
                'payload' => $data,
            ]);
            return response()->json([
                'status' => false,
                'mensaje' => $e->getMessage(),
                'data' => null,
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'mensaje' => $e->getMessage(),
                'data' => null,
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error en despacho a paciente', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al registrar el despacho.',
                'data' => null,
            ], 200);
        }
    }

    /**
     * Listar todos los despachos con filtros opcionales
     */
    public function index(Request $request)
    {
        $query = DespachoPaciente::with(['hospital', 'sede', 'usuario'])
            ->where('status', true);

        // Filtros opcionales
        if ($request->filled('hospital_id')) {
            $query->where('hospital_id', $request->hospital_id);
        }

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('paciente_cedula')) {
            $query->where('paciente_cedula', 'like', '%' . $request->paciente_cedula . '%');
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_despacho', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_despacho', '<=', $request->fecha_hasta);
        }

        $despachos = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'data' => $despachos,
        ]);
    }

    /**
     * Mostrar un despacho específico
     */
    public function show($id)
    {
        $despacho = DespachoPaciente::with(['hospital', 'sede', 'usuario', 'usuarioEntrega', 'lotes'])
            ->where('status', true)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $despacho,
        ]);
    }

    /**
     * Actualizar un despacho (solo ciertos campos)
     */
    public function update(Request $request, $id)
    {
        $despacho = DespachoPaciente::where('status', true)->findOrFail($id);

        $data = $request->validate([
            'observaciones' => ['nullable', 'string', 'max:500'],
            'paciente_telefono' => ['nullable', 'string', 'max:20'],
            'paciente_direccion' => ['nullable', 'string', 'max:500'],
            'medico_tratante' => ['nullable', 'string', 'max:150'],
            'diagnostico' => ['nullable', 'string', 'max:200'],
            'indicaciones_medicas' => ['nullable', 'string', 'max:1000'],
        ]);

        $despacho->update($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Despacho actualizado exitosamente.',
            'data' => $despacho->fresh(),
        ]);
    }

    /**
     * Confirmar entrega del despacho
     */
    public function confirmarEntrega(Request $request, $id)
    {
        $despacho = DespachoPaciente::where('status', true)
            ->where('estado', 'despachado')
            ->findOrFail($id);

        $data = $request->validate([
            'observaciones_entrega' => ['nullable', 'string', 'max:500'],
        ]);

        $despacho->update([
            'estado' => 'entregado',
            'fecha_entrega' => now(),
            'user_id_entrega' => $request->user()->id,
            'observaciones' => $despacho->observaciones . 
                ($data['observaciones_entrega'] ? "\n\nEntrega: " . $data['observaciones_entrega'] : ''),
        ]);

        return response()->json([
            'status' => true,
            'mensaje' => 'Entrega confirmada exitosamente.',
            'data' => $despacho->fresh(),
        ]);
    }

    /**
     * Cancelar un despacho
     */
    public function cancelar(Request $request, $id)
    {
        $despacho = DespachoPaciente::where('status', true)
            ->whereIn('estado', ['pendiente', 'despachado'])
            ->findOrFail($id);

        $data = $request->validate([
            'motivo_cancelacion' => ['required', 'string', 'max:500'],
        ]);

        $despacho->update([
            'estado' => 'cancelado',
            'observaciones' => $despacho->observaciones . "\n\nCancelado: " . $data['motivo_cancelacion'],
        ]);

        return response()->json([
            'status' => true,
            'mensaje' => 'Despacho cancelado exitosamente.',
            'data' => $despacho->fresh(),
        ]);
    }

    /**
     * Eliminar (soft delete) un despacho
     */
    public function destroy($id)
    {
        $despacho = DespachoPaciente::where('status', true)->findOrFail($id);
        
        $despacho->update(['status' => false]);

        return response()->json([
            'status' => true,
            'mensaje' => 'Despacho eliminado exitosamente.',
        ]);
    }

    /**
     * Obtiene el nombre de la tabla según el tipo de almacén
     */
    private function obtenerTablaAlmacen(string $tipoAlmacen): string
    {
        return match ($tipoAlmacen) {
            'almacenCent' => 'almacenes_centrales',
            'almacenPrin' => 'almacenes_principales',
            'almacenFarm' => 'almacenes_farmacia',
            'almacenPar' => 'almacenes_paralelo',
            'almacenServApoyo' => 'almacenes_servicios_apoyo',
            'almacenServAtenciones' => 'almacenes_servicios_atenciones',
            default => throw new InvalidArgumentException("Tipo de almacén no soportado: {$tipoAlmacen}"),
        };
    }

    /**
     * Descuenta la cantidad especificada del almacén
     */
    private function descontarDelAlmacen(string $tabla, int $hospitalId, int $sedeId, int $loteId, int $cantidad): void
    {
        // Buscar el registro en la tabla correspondiente
        $registro = DB::table($tabla)
            ->where('hospital_id', $hospitalId)
            ->where('sede_id', $sedeId)
            ->where('lote_id', $loteId)
            ->where('status', true)
            ->lockForUpdate()
            ->first();

        if (!$registro) {
            throw new StockException("No se encontró el lote {$loteId} en el almacén para el hospital {$hospitalId} y sede {$sedeId}.");
        }

        if ((int) $registro->cantidad < $cantidad) {
            throw new StockException("Stock insuficiente para el lote {$loteId}. Disponible: {$registro->cantidad}, Solicitado: {$cantidad}");
        }

        $nuevaCantidad = (int) $registro->cantidad - $cantidad;

        // Actualizar la cantidad y el status si es necesario
        DB::table($tabla)
            ->where('id', $registro->id)
            ->update([
                'cantidad' => $nuevaCantidad,
                'status' => $nuevaCantidad > 0,
                'updated_at' => now(),
            ]);
    }
}
