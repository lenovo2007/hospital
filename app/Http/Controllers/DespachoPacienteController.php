<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Models\LoteGrupo;
use App\Models\MovimientoStock;
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
            'origen_hospital_id' => ['required', 'integer', 'min:1'],
            'origen_sede_id' => ['required', 'integer', 'min:1'],
            'tipo_movimiento' => ['required', 'string', 'in:salida_paciente'],
            'fecha_despacho' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'paciente.nombres' => ['required', 'string', 'max:100'],
            'paciente.apellidos' => ['required', 'string', 'max:100'],
            'paciente.cedula' => ['required', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.lote_id' => ['required', 'integer', 'min:1'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) $request->user()->id;
        $codigoGrupo = null;

        try {
            DB::transaction(function () use ($data, $userId, &$codigoGrupo) {
                // Obtener información de la sede para determinar el tipo de almacén
                $sede = DB::table('sedes')->where('id', $data['origen_sede_id'])->first();
                if (!$sede) {
                    throw new InvalidArgumentException('Sede no encontrada.');
                }

                // Determinar la tabla de almacén según el tipo
                $tipoAlmacen = $sede->tipo_almacen;
                $tablaAlmacen = $this->obtenerTablaAlmacen($tipoAlmacen);

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
                        (int) $data['origen_hospital_id'],
                        (int) $data['origen_sede_id'],
                        $loteId,
                        $cantidad
                    );
                    $totalCantidad += $cantidad;
                }

                // Crear el movimiento de stock para despacho a paciente
                MovimientoStock::create([
                    'tipo' => 'salida',
                    'tipo_movimiento' => $data['tipo_movimiento'],
                    'origen_hospital_id' => (int) $data['origen_hospital_id'],
                    'origen_sede_id' => (int) $data['origen_sede_id'],
                    'destino_hospital_id' => null, // No hay destino para paciente
                    'destino_sede_id' => null,
                    'origen_almacen_tipo' => $tipoAlmacen,
                    'origen_almacen_id' => null,
                    'destino_almacen_tipo' => null,
                    'destino_almacen_id' => null,
                    'cantidad_salida_total' => $totalCantidad,
                    'cantidad_entrada_total' => 0,
                    'discrepancia_total' => false,
                    'fecha_despacho' => $data['fecha_despacho'],
                    'observaciones' => $data['observaciones'] ?? null,
                    'estado' => 'recibido', // Despacho directo, se considera completado
                    'codigo_grupo' => $codigoGrupo,
                    'user_id' => $userId,
                    // Datos del paciente en JSON
                    'datos_paciente' => json_encode($data['paciente']),
                ]);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Despacho a paciente registrado exitosamente.',
                'data' => [
                    'codigo_grupo' => $codigoGrupo,
                    'paciente' => $data['paciente'],
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
