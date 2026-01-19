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

class DistribucionCentralController extends Controller
{
    // POST /api/movimiento/almacen/salida
    // Movimiento genérico entre cualquier tipo de almacén
    // {
    //   "origen_hospital_id": 1,
    //   "origen_sede_id": 1,
    //   "destino_hospital_id": 2,
    //   "destino_sede_id": 2,
    //   "origen_almacen_tipo": "almacenPrin",  // Puede ser cualquier tipo
    //   "destino_almacen_tipo": "almacenFarm", // Puede ser cualquier tipo
    //   "tipo_movimiento": "despacho",
    //   "fecha_despacho": "2025-10-04",
    //   "observaciones": "Movimiento entre almacenes",
    //   "items": [ { "lote_id": 1, "cantidad": 20 }, { "lote_id": 2, "cantidad": 20 } ]
    // }
    public function salida(Request $request)
    {
        $data = $request->validate([
            'origen_hospital_id' => ['required','integer','min:1'],
            'origen_sede_id' => ['required','integer','min:1'],
            'destino_hospital_id' => ['required','integer','min:1'],
            'destino_sede_id' => ['required','integer','min:1'],
            'origen_almacen_tipo' => ['required','string','max:100'],
            'destino_almacen_tipo' => ['required','string','max:100'],
            'tipo_movimiento' => ['required','string','max:50'],
            'fecha_despacho' => ['required','date'],
            'observaciones' => ['nullable','string','max:500'],
            'items' => ['required','array','min:1'],
            'items.*.lote_id' => ['required','integer','min:1'],
            'items.*.cantidad' => ['required','integer','min:1'],
        ]);

        $userId = (int) $request->user()->id;

        $codigoGrupo = null;

        try {
            DB::transaction(function () use ($data, $userId, &$codigoGrupo) {
                // Crear grupo de lote para los items del movimiento
                [$codigoGrupo, $grupoItems] = LoteGrupo::crearGrupo($data['items']);

                // Calcular la suma total de cantidades y descontar del almacén origen
                $totalCantidad = 0;
                foreach ($data['items'] as $item) {
                    $loteId = (int) $item['lote_id'];
                    $cantidad = (int) $item['cantidad'];
                    
                    // Descontar del almacén origen
                    $this->descontarDelAlmacen(
                        $data['origen_almacen_tipo'],
                        (int) $data['origen_hospital_id'],
                        (int) $data['origen_sede_id'],
                        $loteId,
                        $cantidad
                    );
                    $totalCantidad += $cantidad;
                }
                // Crear el movimiento de stock
                MovimientoStock::create([
                    'tipo' => 'transferencia',
                    'tipo_movimiento' => $data['tipo_movimiento'],
                    'origen_hospital_id' => (int) $data['origen_hospital_id'],
                    'origen_sede_id' => (int) $data['origen_sede_id'],
                    'destino_hospital_id' => (int) $data['destino_hospital_id'],
                    'destino_sede_id' => (int) $data['destino_sede_id'],
                    'origen_almacen_tipo' => $data['origen_almacen_tipo'],
                    'origen_almacen_id' => null,
                    'destino_almacen_tipo' => $data['destino_almacen_tipo'],
                    'destino_almacen_id' => null,
                    'cantidad_salida_total' => $totalCantidad,
                    'cantidad_entrada_total' => 0,
                    'discrepancia_total' => false,
                    'fecha_despacho' => $data['fecha_despacho'],
                    'observaciones' => $data['observaciones'] ?? null,
                    'estado' => ((int)$data['origen_hospital_id'] === (int)$data['destino_hospital_id']) ? 'despachado' : 'pendiente',
                    'codigo_grupo' => $codigoGrupo,
                    'user_id' => $userId,
                ]);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimiento desde central aplicado.',
                'data' => [
                    'codigo_grupo' => $codigoGrupo,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (StockException $e) {
            Log::warning('Movimiento central falló por StockException', [
                'mensaje' => $e->getMessage(),
                'payload' => $data,
            ]);
            return response()->json([
                'status' => false,
                'mensaje' => $e->getMessage(),
                'data' => null,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al registrar el movimiento.',
                'data' => null,
            ], 200);
        }
    }

    // POST /api/movimiento/almacen/cancelar
    // {
    //   "movimiento_stock_id": 123,
    //   "observaciones": "Motivo de cancelación" // opcional
    // }
    public function cancelar(Request $request)
    {
        $data = $request->validate([
            'movimiento_stock_id' => ['required', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $movimientoId = (int) $data['movimiento_stock_id'];

        try {
            DB::transaction(function () use ($movimientoId, $data) {
                $movimiento = MovimientoStock::query()
                    ->where('id', $movimientoId)
                    ->lockForUpdate()
                    ->first();

                if (!$movimiento) {
                    throw new InvalidArgumentException('No existe un movimiento con el ID indicado.');
                }

                if ($movimiento->estado === 'cancelado') {
                    throw new InvalidArgumentException('El movimiento ya está cancelado.');
                }

                if ($movimiento->estado === 'recibido') {
                    throw new InvalidArgumentException('No se puede cancelar un movimiento ya recibido.');
                }

                if (!in_array($movimiento->estado, ['pendiente', 'despachado', 'en_camino', 'entregado'], true)) {
                    throw new InvalidArgumentException('El movimiento no está en un estado cancelable. Estado actual: ' . (string) $movimiento->estado);
                }

                if (!$movimiento->codigo_grupo) {
                    throw new InvalidArgumentException('El movimiento no tiene codigo_grupo para revertir stock.');
                }

                $items = LoteGrupo::query()
                    ->where('codigo', (string) $movimiento->codigo_grupo)
                    ->where('status', 'activo')
                    ->lockForUpdate()
                    ->get(['id', 'lote_id', 'cantidad_salida']);

                if ($items->isEmpty()) {
                    throw new InvalidArgumentException('No hay items activos asociados al codigo_grupo para revertir.');
                }

                foreach ($items as $it) {
                    $loteId = (int) $it->lote_id;
                    $cantidad = (int) $it->cantidad_salida;
                    if ($loteId <= 0 || $cantidad <= 0) {
                        continue;
                    }

                    $this->sumarAlAlmacen(
                        (string) $movimiento->origen_almacen_tipo,
                        (int) $movimiento->origen_hospital_id,
                        (int) $movimiento->origen_sede_id,
                        $loteId,
                        $cantidad
                    );
                }

                LoteGrupo::query()
                    ->where('codigo', (string) $movimiento->codigo_grupo)
                    ->update([
                        'status' => 'inactivo',
                        'updated_at' => now(),
                    ]);

                $observaciones = (string) ($movimiento->observaciones ?? '');
                $extra = isset($data['observaciones']) ? trim((string) $data['observaciones']) : '';
                $nuevoTexto = trim($observaciones . (trim($observaciones) !== '' ? ' | ' : '') . 'Cancelado: ' . ($extra !== '' ? $extra : 'sin observaciones'));

                $movimiento->update([
                    'estado' => 'cancelado',
                    'observaciones' => $nuevoTexto,
                ]);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimiento cancelado y stock revertido al almacén origen.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'mensaje' => $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al cancelar el movimiento: ' . $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Descuenta la cantidad especificada del almacén origen según su tipo
     */
    private function descontarDelAlmacen(string $tipoAlmacen, int $hospitalId, int $sedeId, int $loteId, int $cantidad): void
    {
        // Determinar la tabla según el tipo de almacén
        $tabla = match ($tipoAlmacen) {
            'almacenCent' => 'almacenes_centrales',
            'almacenPrin' => 'almacenes_principales',
            'almacenFarm' => 'almacenes_farmacia',
            'almacenAus' => 'almacenes_aus',
            'almacenPar' => 'almacenes_paralelo',
            'almacenServApoyo' => 'almacenes_servicios_apoyo',
            'almacenServAtenciones' => 'almacenes_servicios_atenciones',
            default => throw new StockException("Tipo de almacén no soportado: {$tipoAlmacen}"),
        };

        // Buscar el registro en la tabla correspondiente
        $registro = DB::table($tabla)
            ->where('hospital_id', $hospitalId)
            ->where('sede_id', $sedeId)
            ->where('lote_id', $loteId)
            ->where('status', true)
            ->lockForUpdate()
            ->first();

        if (!$registro) {
            throw new StockException("No se encontró el lote {$loteId} en el almacén {$tipoAlmacen} para el hospital {$hospitalId} y sede {$sedeId}.");
        }

        if ((int) $registro->cantidad < $cantidad) {
            throw new StockException("Stock insuficiente en el almacén {$tipoAlmacen} para el lote {$loteId}. Disponible: {$registro->cantidad}, Solicitado: {$cantidad}");
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

    private function sumarAlAlmacen(string $tipoAlmacen, int $hospitalId, int $sedeId, int $loteId, int $cantidad): void
    {
        $tabla = match ($tipoAlmacen) {
            'almacenCent' => 'almacenes_centrales',
            'almacenPrin' => 'almacenes_principales',
            'almacenFarm' => 'almacenes_farmacia',
            'almacenAus' => 'almacenes_aus',
            'almacenPar' => 'almacenes_paralelo',
            'almacenServApoyo' => 'almacenes_servicios_apoyo',
            'almacenServAtenciones' => 'almacenes_servicios_atenciones',
            default => throw new StockException("Tipo de almacén no soportado: {$tipoAlmacen}"),
        };

        $registro = DB::table($tabla)
            ->where('hospital_id', $hospitalId)
            ->where('sede_id', $sedeId)
            ->where('lote_id', $loteId)
            ->lockForUpdate()
            ->first();

        if ($registro) {
            $nuevaCantidad = (int) $registro->cantidad + $cantidad;
            DB::table($tabla)
                ->where('id', $registro->id)
                ->update([
                    'cantidad' => $nuevaCantidad,
                    'status' => $nuevaCantidad > 0,
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table($tabla)->insert([
            'hospital_id' => $hospitalId,
            'sede_id' => $sedeId,
            'lote_id' => $loteId,
            'cantidad' => $cantidad,
            'status' => $cantidad > 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

}
