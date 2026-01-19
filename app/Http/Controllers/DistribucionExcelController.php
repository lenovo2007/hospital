<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Lote;
use App\Models\TipoHospitalDistribucion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class DistribucionExcelController extends Controller
{
    /**
     * Importar y distribuir insumos desde Excel según porcentajes por tipo de hospital
     * 
     * POST /api/distribucion/import
     * 
     * Formato del archivo Excel:
     * - Columna A: ITEM (número)
     * - Columna B: DESCRIPCION (MATERIAL MMQ) - nombre del insumo
     * - Columna C: PARA DESPACHAR - cantidad total a distribuir
     * 
     * Proceso:
     * 1. Consulta hospitales del estado Falcón
     * 2. Obtiene porcentajes de distribución por tipo de hospital
     * 3. Distribuye cantidades según porcentajes (trunca decimales)
     * 4. Crea movimientos de despacho desde almacén central a principal de cada hospital
     */
    public function importarYDistribuir(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:10240'],
        ]);

        $file = $request->file('file');
        $userId = (int) $request->user()->id;

        try {
            // 1. Obtener todos los hospitales activos
            $hospitales = Hospital::where('status', true)->get();

            if ($hospitales->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No se encontraron hospitales activos.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            // Agrupar hospitales por tipo
            $hospitalesPorTipo = $hospitales->groupBy('tipo');

            // 2. Obtener porcentajes de distribución
            $porcentajes = TipoHospitalDistribucion::first();
            if (!$porcentajes) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No se encontró configuración de porcentajes de distribución.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            // 3. Leer archivo Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            $insumosDistribuidos = [];
            $errores = [];
            $omitidos = [];
            
            // Estructura para agrupar lotes por hospital
            // $lotesPorHospital[hospital_id][sede_id][] = ['lote_id' => X, 'cantidad' => Y]
            $lotesPorHospital = [];

            // Procesar cada fila del Excel (empezar desde fila 2 para saltar encabezados)
            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    $item = $sheet->getCell("A{$row}")->getValue();
                    $descripcion = trim((string) $sheet->getCell("B{$row}")->getValue());
                    $paraDespachar = $sheet->getCell("C{$row}")->getValue();

                    // Validar datos básicos
                    if (empty($descripcion) || empty($paraDespachar) || $paraDespachar <= 0) {
                        $omitidos[] = [
                            'fila' => $row,
                            'motivo' => 'Descripción vacía o cantidad inválida',
                        ];
                        continue;
                    }

                    $cantidadTotal = (int) $paraDespachar;

                    // Buscar insumo por nombre
                    $insumo = Insumo::where('nombre', $descripcion)->first();
                    if (!$insumo) {
                        $errores[] = [
                            'fila' => $row,
                            'descripcion' => $descripcion,
                            'error' => 'Insumo no encontrado en la base de datos',
                        ];
                        continue;
                    }

                    // 4. Calcular distribución por tipo de hospital
                    $distribucion = $this->calcularDistribucion(
                        $cantidadTotal,
                        $porcentajes,
                        $hospitalesPorTipo
                    );

                    // Calcular cantidad real necesaria después de aplicar porcentajes
                    $cantidadRealNecesaria = 0;
                    foreach ($distribucion as $tipoHospital => $hospitalesConCantidad) {
                        foreach ($hospitalesConCantidad as $hospitalData) {
                            $cantidadRealNecesaria += $hospitalData['cantidad'];
                        }
                    }

                    // Buscar TODOS los lotes disponibles ordenados por fecha de vencimiento (FIFO)
                    $lotesDisponibles = DB::table('almacenes_centrales')
                        ->join('lotes', 'almacenes_centrales.lote_id', '=', 'lotes.id')
                        ->where('lotes.id_insumo', $insumo->id)
                        ->where('almacenes_centrales.hospital_id', 1)
                        ->where('almacenes_centrales.sede_id', 1)
                        ->where('almacenes_centrales.status', true)
                        ->where('almacenes_centrales.cantidad', '>', 0)
                        ->select('lotes.id as lote_id', 'almacenes_centrales.cantidad', 'lotes.fecha_vencimiento')
                        ->orderBy('lotes.fecha_vencimiento', 'asc')
                        ->get();

                    // Verificar si hay stock total suficiente
                    $stockTotalDisponible = $lotesDisponibles->sum('cantidad');
                    if ($stockTotalDisponible < $cantidadRealNecesaria) {
                        $errores[] = [
                            'fila' => $row,
                            'descripcion' => $descripcion,
                            'error' => "Stock total insuficiente en almacén central. Disponible: {$stockTotalDisponible}, Requerido después de porcentajes: {$cantidadRealNecesaria}",
                        ];
                        continue;
                    }

                    // 5. Distribuir por hospital usando múltiples lotes (FIFO)
                    foreach ($distribucion as $tipoHospital => $hospitalesConCantidad) {
                        foreach ($hospitalesConCantidad as $hospitalData) {
                            if ($hospitalData['cantidad'] <= 0) {
                                continue;
                            }

                            // Obtener sede principal del hospital destino
                            $sedeDestino = DB::table('sedes')
                                ->where('hospital_id', $hospitalData['hospital_id'])
                                ->where('tipo_almacen', 'almacenPrin')
                                ->first();

                            if (!$sedeDestino) {
                                $errores[] = [
                                    'fila' => $row,
                                    'descripcion' => $descripcion,
                                    'error' => "Hospital {$hospitalData['hospital_nombre']} no tiene sede principal configurada",
                                ];
                                continue;
                            }

                            $hospitalId = $hospitalData['hospital_id'];
                            $sedeId = $sedeDestino->id;
                            $cantidadNecesaria = $hospitalData['cantidad'];

                            // Inicializar estructura si no existe
                            if (!isset($lotesPorHospital[$hospitalId])) {
                                $lotesPorHospital[$hospitalId] = [
                                    'sede_id' => $sedeId,
                                    'hospital_nombre' => $hospitalData['hospital_nombre'],
                                    'lotes' => [],
                                ];
                            }

                            // Asignar lotes usando FIFO (primero los que vencen primero)
                            $cantidadRestante = $cantidadNecesaria;
                            foreach ($lotesDisponibles as $loteDisponible) {
                                if ($cantidadRestante <= 0) {
                                    break;
                                }

                                $cantidadDisponibleLote = (int) $loteDisponible->cantidad;
                                if ($cantidadDisponibleLote <= 0) {
                                    continue;
                                }

                                // Tomar lo que se pueda de este lote
                                $cantidadATomar = min($cantidadRestante, $cantidadDisponibleLote);

                                // Agregar este lote a la lista del hospital
                                $lotesPorHospital[$hospitalId]['lotes'][] = [
                                    'lote_id' => $loteDisponible->lote_id,
                                    'cantidad' => $cantidadATomar,
                                    'insumo' => $descripcion,
                                    'fecha_vencimiento' => $loteDisponible->fecha_vencimiento,
                                ];

                                // Actualizar cantidad disponible del lote (en memoria)
                                $loteDisponible->cantidad = $cantidadDisponibleLote - $cantidadATomar;
                                $cantidadRestante -= $cantidadATomar;
                            }
                        }
                    }

                    $insumosDistribuidos[] = [
                        'fila' => $row,
                        'insumo' => $descripcion,
                        'cantidad_total' => $cantidadTotal,
                        'cantidad_distribuida' => $cantidadRealNecesaria,
                    ];

                } catch (Throwable $e) {
                    $errores[] = [
                        'fila' => $row,
                        'descripcion' => $descripcion ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Error procesando fila de distribución', [
                        'fila' => $row,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 6. Crear un movimiento por hospital con todos sus lotes
            $movimientosCreados = 0;
            foreach ($lotesPorHospital as $hospitalId => $hospitalData) {
                try {
                    $this->crearMovimientoAgrupado(
                        $hospitalData['lotes'],
                        $hospitalId,
                        $hospitalData['sede_id'],
                        $userId,
                        "Distribución automática a {$hospitalData['hospital_nombre']}"
                    );
                    $movimientosCreados++;
                } catch (Throwable $e) {
                    $errores[] = [
                        'fila' => 'N/A',
                        'descripcion' => "Movimiento para hospital {$hospitalData['hospital_nombre']}",
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Error creando movimiento agrupado', [
                        'hospital_id' => $hospitalId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'status' => true,
                'mensaje' => 'Distribución procesada exitosamente.',
                'data' => [
                    'insumos_distribuidos' => count($insumosDistribuidos),
                    'movimientos_creados' => $movimientosCreados,
                    'hospitales_totales' => $hospitales->count(),
                    'hospitales_con_movimientos' => count($lotesPorHospital),
                    'omitidos' => count($omitidos),
                    'errores' => count($errores),
                    'detalle_insumos' => $insumosDistribuidos,
                    'detalle_omitidos' => $omitidos,
                    'detalle_errores' => $errores,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (Throwable $e) {
            Log::error('Error en importación de distribución', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al procesar el archivo de distribución: ' . $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Calcula la distribución de cantidades según porcentajes por tipo de hospital
     * Trunca decimales (no redondea)
     */
    private function calcularDistribucion(int $cantidadTotal, TipoHospitalDistribucion $porcentajes, $hospitalesPorTipo): array
    {
        $distribucion = [];

        $tiposMap = [
            'hospital_tipo1' => 'tipo1',
            'hospital_tipo2' => 'tipo2',
            'hospital_tipo3' => 'tipo3',
            'hospital_tipo4' => 'tipo4',
        ];

        foreach ($tiposMap as $tipoHospital => $campoPortcentaje) {
            if (!isset($hospitalesPorTipo[$tipoHospital])) {
                continue;
            }

            $hospitales = $hospitalesPorTipo[$tipoHospital];
            $porcentajeTipo = (float) $porcentajes->$campoPortcentaje;
            $cantidadHospitales = $hospitales->count();

            if ($cantidadHospitales === 0 || $porcentajeTipo <= 0) {
                continue;
            }

            // Nueva lógica: Dividir el porcentaje del tipo entre el total de hospitales de ese tipo
            // Ejemplo: tipo1 = 15% / 138 hospitales = 0.10869%
            $porcentajePorHospital = $porcentajeTipo / $cantidadHospitales;
            
            // Redondear a 2 decimales: si el tercer decimal es >= 5, redondear hacia arriba
            $porcentajePorHospital = round($porcentajePorHospital, 2);

            $distribucion[$tipoHospital] = [];

            foreach ($hospitales as $hospital) {
                // Calcular cantidad para este hospital usando su porcentaje individual
                $cantidadPorHospital = ($cantidadTotal * $porcentajePorHospital) / 100;
                
                // Truncar decimal (no redondear la cantidad final)
                $cantidadPorHospitalEntero = (int) $cantidadPorHospital;

                $distribucion[$tipoHospital][] = [
                    'hospital_id' => $hospital->id,
                    'hospital_nombre' => $hospital->nombre,
                    'cantidad' => $cantidadPorHospitalEntero,
                    'porcentaje_individual' => $porcentajePorHospital, // Para debugging
                ];
            }
        }

        return $distribucion;
    }

    /**
     * Crea un movimiento agrupado con múltiples lotes para un hospital
     * 
     * @param array $lotes Array de lotes: [['lote_id' => X, 'cantidad' => Y, 'insumo' => 'nombre'], ...]
     * @param int $destinoHospitalId
     * @param int $destinoSedeId
     * @param int $userId
     * @param string $observaciones
     */
    private function crearMovimientoAgrupado(
        array $lotes,
        int $destinoHospitalId,
        int $destinoSedeId,
        int $userId,
        string $observaciones
    ): void {
        DB::transaction(function () use ($lotes, $destinoHospitalId, $destinoSedeId, $userId, $observaciones) {
            // Generar código de grupo único para este movimiento
            $codigoGrupo = 'LG-' . strtoupper(uniqid());
            $cantidadTotalSalida = 0;

            // Procesar cada lote: descontar stock y crear registro en lotes_grupos
            foreach ($lotes as $loteData) {
                $loteId = $loteData['lote_id'];
                $cantidad = $loteData['cantidad'];

                // Descontar del almacén central
                $registro = DB::table('almacenes_centrales')
                    ->where('hospital_id', 1)
                    ->where('sede_id', 1)
                    ->where('lote_id', $loteId)
                    ->where('status', true)
                    ->lockForUpdate()
                    ->first();

                if (!$registro) {
                    throw new StockException("No se encontró el lote {$loteId} en almacén central.");
                }

                if ((int) $registro->cantidad < $cantidad) {
                    throw new StockException("Stock insuficiente para lote {$loteId}. Disponible: {$registro->cantidad}, Solicitado: {$cantidad}");
                }

                $nuevaCantidad = (int) $registro->cantidad - $cantidad;

                DB::table('almacenes_centrales')
                    ->where('id', $registro->id)
                    ->update([
                        'cantidad' => $nuevaCantidad,
                        'status' => $nuevaCantidad > 0,
                        'updated_at' => now(),
                    ]);

                // Crear registro en lotes_grupos con el mismo codigo_grupo
                DB::table('lotes_grupos')->insert([
                    'codigo' => $codigoGrupo,
                    'lote_id' => $loteId,
                    'cantidad_salida' => $cantidad,
                    'cantidad_entrada' => 0,
                    'discrepancia' => false,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $cantidadTotalSalida += $cantidad;
            }

            // Crear UN SOLO movimiento de stock con todos los lotes agrupados
            DB::table('movimientos_stock')->insert([
                'tipo' => 'transferencia',
                'tipo_movimiento' => 'despacho',
                'origen_hospital_id' => 1,
                'origen_sede_id' => 1,
                'destino_hospital_id' => $destinoHospitalId,
                'destino_sede_id' => $destinoSedeId,
                'origen_almacen_tipo' => 'almacenCent',
                'destino_almacen_tipo' => 'almacenPrin',
                'cantidad_salida_total' => $cantidadTotalSalida,
                'cantidad_entrada_total' => 0,
                'discrepancia_total' => false,
                'fecha_despacho' => now()->toDateString(),
                'observaciones' => $observaciones,
                'estado' => 'pendiente',
                'codigo_grupo' => $codigoGrupo,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
