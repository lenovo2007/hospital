<?php

namespace App\Http\Controllers;

use App\Models\DespachoPaciente;
use App\Models\MovimientoStock;
use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EstadisticasController extends Controller
{
    /**
     * Estadísticas generales de insumos con comparación mensual
     * GET /api/estadisticas/insumos
     */
    public function insumos(Request $request)
    {
        $sedeId = $request->get('sede_id');
        $mesActual = Carbon::now();
        $mesAnterior = Carbon::now()->subMonth();

        if ($sedeId) {
            // Para sede específica: contar insumos únicos disponibles en el almacén de esa sede
            $sede = DB::table('sedes')->where('id', $sedeId)->first();
            $tablaAlmacen = $this->obtenerTablaAlmacen($sede->tipo_almacen);
            
            // Total de insumos únicos disponibles en el almacén de la sede
            $totalGeneral = DB::table($tablaAlmacen)
                ->join('lotes', $tablaAlmacen . '.lote_id', '=', 'lotes.id')
                ->join('insumos', 'lotes.id_insumo', '=', 'insumos.id')
                ->where($tablaAlmacen . '.sede_id', $sedeId)
                ->where($tablaAlmacen . '.status', true)
                ->where('insumos.status', true)
                ->distinct('insumos.id')
                ->count();

            // Insumos agregados este mes al almacén
            $totalActual = DB::table($tablaAlmacen)
                ->join('lotes', $tablaAlmacen . '.lote_id', '=', 'lotes.id')
                ->join('insumos', 'lotes.id_insumo', '=', 'insumos.id')
                ->where($tablaAlmacen . '.sede_id', $sedeId)
                ->where($tablaAlmacen . '.status', true)
                ->where('insumos.status', true)
                ->whereMonth($tablaAlmacen . '.created_at', $mesActual->month)
                ->whereYear($tablaAlmacen . '.created_at', $mesActual->year)
                ->distinct('insumos.id')
                ->count();

            // Insumos agregados mes anterior al almacén
            $totalAnterior = DB::table($tablaAlmacen)
                ->join('lotes', $tablaAlmacen . '.lote_id', '=', 'lotes.id')
                ->join('insumos', 'lotes.id_insumo', '=', 'insumos.id')
                ->where($tablaAlmacen . '.sede_id', $sedeId)
                ->where($tablaAlmacen . '.status', true)
                ->where('insumos.status', true)
                ->whereMonth($tablaAlmacen . '.created_at', $mesAnterior->month)
                ->whereYear($tablaAlmacen . '.created_at', $mesAnterior->year)
                ->distinct('insumos.id')
                ->count();
        } else {
            // Query base para insumos generales
            $queryBase = Insumo::where('status', true);

            // Total de insumos activos este mes
            $totalActual = (clone $queryBase)
                ->whereMonth('created_at', $mesActual->month)
                ->whereYear('created_at', $mesActual->year)
                ->count();

            // Total de insumos activos mes anterior
            $totalAnterior = (clone $queryBase)
                ->whereMonth('created_at', $mesAnterior->month)
                ->whereYear('created_at', $mesAnterior->year)
                ->count();

            // Total general de insumos
            $totalGeneral = (clone $queryBase)->count();
        }

        // Calcular porcentaje de cambio
        $porcentajeCambio = 0;
        $tendencia = 'igual';
        
        if ($totalAnterior > 0) {
            $porcentajeCambio = round((($totalActual - $totalAnterior) / $totalAnterior) * 100, 1);
            $tendencia = $porcentajeCambio > 0 ? 'aumento' : ($porcentajeCambio < 0 ? 'disminucion' : 'igual');
        } elseif ($totalActual > 0) {
            $porcentajeCambio = 100;
            $tendencia = 'aumento';
        }

        // Información de la sede si se especifica
        $sedeInfo = null;
        if ($sedeId) {
            $sedeInfo = DB::table('sedes')
                ->join('hospitales', 'sedes.hospital_id', '=', 'hospitales.id')
                ->where('sedes.id', $sedeId)
                ->select('sedes.nombre as sede_nombre', 'hospitales.nombre as hospital_nombre')
                ->first();
        }

        return response()->json([
            'status' => true,
            'data' => [
                'sede_info' => $sedeInfo,
                'total_general' => $totalGeneral,
                'nuevos_este_mes' => $totalActual,
                'nuevos_mes_anterior' => $totalAnterior,
                'porcentaje_cambio' => abs($porcentajeCambio),
                'tendencia' => $tendencia,
                'mensaje_comparacion' => $this->generarMensajeComparacion($porcentajeCambio, $tendencia),
            ]
        ]);
    }

    /**
     * Estadísticas de estados de movimientos de stock
     * GET /api/estadisticas/movimientos-estados
     */
    public function movimientosEstados(Request $request)
    {
        // Filtros opcionales
        $sedeId = $request->get('sede_id');
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');

        if ($sedeId) {
            // Consultas separadas para origen y destino
            $queryOrigen = MovimientoStock::where('origen_sede_id', $sedeId);
            $queryDestino = MovimientoStock::where('destino_sede_id', $sedeId);

            if ($fechaDesde) {
                $queryOrigen->whereDate('created_at', '>=', $fechaDesde);
                $queryDestino->whereDate('created_at', '>=', $fechaDesde);
            }

            if ($fechaHasta) {
                $queryOrigen->whereDate('created_at', '<=', $fechaHasta);
                $queryDestino->whereDate('created_at', '<=', $fechaHasta);
            }

            // Estadísticas como origen
            $estadisticasOrigen = $queryOrigen->select('estado', DB::raw('COUNT(*) as total'))
                ->groupBy('estado')
                ->get()
                ->keyBy('estado');

            // Estadísticas como destino
            $estadisticasDestino = $queryDestino->select('estado', DB::raw('COUNT(*) as total'))
                ->groupBy('estado')
                ->get()
                ->keyBy('estado');

            // Asegurar que todos los estados estén presentes
            $estados = ['pendiente', 'despachado', 'entregado', 'recibido', 'cancelado'];
            
            $resultadoOrigen = [];
            $resultadoDestino = [];
            $resultado = [];

            foreach ($estados as $estado) {
                $origenCount = $estadisticasOrigen->get($estado)?->total ?? 0;
                $destinoCount = $estadisticasDestino->get($estado)?->total ?? 0;
                
                $resultadoOrigen[$estado] = $origenCount;
                $resultadoDestino[$estado] = $destinoCount;
                $resultado[$estado] = $origenCount + $destinoCount;
            }

            // Totales
            $totalOrigen = array_sum($resultadoOrigen);
            $totalDestino = array_sum($resultadoDestino);
            $total = $totalOrigen + $totalDestino;

            // Calcular porcentajes
            $porcentajes = [];
            foreach ($resultado as $estado => $cantidad) {
                $porcentajes[$estado] = $total > 0 ? round(($cantidad / $total) * 100, 1) : 0;
            }

        } else {
            // Lógica original para consulta general
            $query = MovimientoStock::query();

            if ($fechaDesde) {
                $query->whereDate('created_at', '>=', $fechaDesde);
            }

            if ($fechaHasta) {
                $query->whereDate('created_at', '<=', $fechaHasta);
            }

            // Contar por estados
            $estadisticas = $query->select('estado', DB::raw('COUNT(*) as total'))
                ->groupBy('estado')
                ->get()
                ->keyBy('estado');

            // Asegurar que todos los estados estén presentes
            $estados = ['pendiente', 'despachado', 'entregado', 'recibido', 'cancelado'];
            $resultado = [];

            foreach ($estados as $estado) {
                $resultado[$estado] = $estadisticas->get($estado)?->total ?? 0;
            }

            // Total de movimientos
            $total = array_sum($resultado);

            // Calcular porcentajes
            $porcentajes = [];
            foreach ($resultado as $estado => $cantidad) {
                $porcentajes[$estado] = $total > 0 ? round(($cantidad / $total) * 100, 1) : 0;
            }

            // Para consulta general, no hay división origen/destino
            $totalOrigen = null;
            $totalDestino = null;
            $resultadoOrigen = null;
            $resultadoDestino = null;
        }

        // Información de la sede si se especifica
        $sedeInfo = null;
        if ($sedeId) {
            $sedeInfo = DB::table('sedes')
                ->join('hospitales', 'sedes.hospital_id', '=', 'hospitales.id')
                ->where('sedes.id', $sedeId)
                ->select('sedes.nombre as sede_nombre', 'hospitales.nombre as hospital_nombre')
                ->first();
        }

        $response = [
            'status' => true,
            'data' => [
                'sede_info' => $sedeInfo,
                'total_movimientos' => $total,
                'por_estado' => $resultado,
                'porcentajes' => $porcentajes,
                'periodo' => [
                    'desde' => $fechaDesde ?? 'inicio',
                    'hasta' => $fechaHasta ?? 'hoy'
                ]
            ]
        ];

        // Agregar desglose origen/destino solo para consultas por sede
        if ($sedeId) {
            $response['data']['desglose_sede'] = [
                'como_origen' => [
                    'total' => $totalOrigen,
                    'por_estado' => $resultadoOrigen,
                    'descripcion' => 'Movimientos que salen de esta sede'
                ],
                'como_destino' => [
                    'total' => $totalDestino,
                    'por_estado' => $resultadoDestino,
                    'descripcion' => 'Movimientos que llegan a esta sede'
                ]
            ];
        }

        return response()->json($response);
    }

    /**
     * Insumos en falta en almacén por sede
     * GET /api/estadisticas/insumos-faltantes
     */
    public function insumosFaltantes(Request $request)
    {
        $sedeId = $request->get('sede_id');
        
        // Determinar el tipo de almacén según la sede
        $tipoAlmacen = 'almacenes_centrales'; // Por defecto
        $tablaAlmacen = 'almacenes_centrales';
        
        if ($sedeId) {
            $sede = DB::table('sedes')->where('id', $sedeId)->first();
            if ($sede) {
                $tipoAlmacen = $sede->tipo_almacen;
                $tablaAlmacen = $this->obtenerTablaAlmacen($tipoAlmacen);
            }
        }

        // Query base para insumos
        $queryBaseInsumos = DB::table('insumos')->where('insumos.status', true);
        if ($sedeId) {
            $queryBaseInsumos->where('insumos.sede_id', $sedeId);
        }

        // Insumos que existen pero no tienen stock en el almacén correspondiente
        $insumosSinStock = (clone $queryBaseInsumos)
            ->leftJoin('lotes', 'insumos.id', '=', 'lotes.id_insumo')
            ->leftJoin($tablaAlmacen, function($join) use ($tablaAlmacen, $sedeId) {
                $join->on('lotes.id', '=', $tablaAlmacen . '.lote_id')
                     ->where($tablaAlmacen . '.status', true)
                     ->where($tablaAlmacen . '.cantidad', '>', 0);
                if ($sedeId) {
                    $join->where($tablaAlmacen . '.sede_id', $sedeId);
                }
            })
            ->whereNull($tablaAlmacen . '.id')
            ->select(
                'insumos.id',
                'insumos.nombre',
                'insumos.codigo',
                'insumos.codigo_alterno',
                'insumos.presentacion'
            )
            ->distinct()
            ->get();

        // Insumos con stock bajo (menos de 10 unidades)
        $insumosStockBajo = (clone $queryBaseInsumos)
            ->join('lotes', 'insumos.id', '=', 'lotes.id_insumo')
            ->join($tablaAlmacen, 'lotes.id', '=', $tablaAlmacen . '.lote_id')
            ->where($tablaAlmacen . '.status', true)
            ->where($tablaAlmacen . '.cantidad', '>', 0)
            ->where($tablaAlmacen . '.cantidad', '<=', 10);
            
        if ($sedeId) {
            $insumosStockBajo->where($tablaAlmacen . '.sede_id', $sedeId);
        }
            
        $insumosStockBajo = $insumosStockBajo->select(
                'insumos.id',
                'insumos.nombre',
                'insumos.codigo',
                'insumos.codigo_alterno',
                'insumos.presentacion',
                DB::raw('SUM(' . $tablaAlmacen . '.cantidad) as stock_actual')
            )
            ->groupBy('insumos.id', 'insumos.nombre', 'insumos.codigo', 'insumos.codigo_alterno', 'insumos.presentacion')
            ->get();

        // Información de la sede si se especifica
        $sedeInfo = null;
        if ($sedeId) {
            $sedeInfo = DB::table('sedes')
                ->join('hospitales', 'sedes.hospital_id', '=', 'hospitales.id')
                ->where('sedes.id', $sedeId)
                ->select('sedes.nombre as sede_nombre', 'hospitales.nombre as hospital_nombre', 'sedes.tipo_almacen')
                ->first();
        }

        return response()->json([
            'status' => true,
            'data' => [
                'sede_info' => $sedeInfo,
                'tipo_almacen' => $tipoAlmacen,
                'insumos_sin_stock' => [
                    'total' => $insumosSinStock->count(),
                    'listado' => $insumosSinStock
                ],
                'insumos_stock_bajo' => [
                    'total' => $insumosStockBajo->count(),
                    'listado' => $insumosStockBajo
                ],
                'resumen' => [
                    'total_problemas' => $insumosSinStock->count() + $insumosStockBajo->count(),
                    'sin_stock' => $insumosSinStock->count(),
                    'stock_bajo' => $insumosStockBajo->count()
                ]
            ]
        ]);
    }

    /**
     * Estadísticas de estados de despachos a pacientes
     * GET /api/estadisticas/pacientes-estados
     */
    public function pacientesEstados(Request $request)
    {
        // Filtros opcionales
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $hospitalId = $request->get('hospital_id');
        $sedeId = $request->get('sede_id');

        $query = DespachoPaciente::where('status', true);

        if ($fechaDesde) {
            $query->whereDate('fecha_despacho', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->whereDate('fecha_despacho', '<=', $fechaHasta);
        }

        if ($hospitalId) {
            $query->where('hospital_id', $hospitalId);
        }

        if ($sedeId) {
            $query->where('sede_id', $sedeId);
        }

        // Contar por estados
        $estadisticas = $query->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        // Asegurar que todos los estados estén presentes
        $estados = ['pendiente', 'despachado', 'entregado', 'cancelado'];
        $resultado = [];

        foreach ($estados as $estado) {
            $resultado[$estado] = $estadisticas->get($estado)?->total ?? 0;
        }

        // Total de despachos
        $total = array_sum($resultado);

        // Calcular porcentajes
        $porcentajes = [];
        foreach ($resultado as $estado => $cantidad) {
            $porcentajes[$estado] = $total > 0 ? round(($cantidad / $total) * 100, 1) : 0;
        }

        // Estadísticas adicionales
        $totalPacientesUnicos = $query->distinct('paciente_cedula')->count('paciente_cedula');
        $totalItemsDespachados = $query->sum('cantidad_total_items');

        return response()->json([
            'status' => true,
            'data' => [
                'total_despachos' => $total,
                'total_pacientes_unicos' => $totalPacientesUnicos,
                'total_items_despachados' => $totalItemsDespachados,
                'por_estado' => $resultado,
                'porcentajes' => $porcentajes,
                'filtros_aplicados' => [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                    'hospital_id' => $hospitalId,
                    'sede_id' => $sedeId
                ]
            ]
        ]);
    }

    /**
     * Dashboard general con resumen de todas las estadísticas
     * GET /api/estadisticas/dashboard
     */
    public function dashboard(Request $request)
    {
        $sedeId = $request->get('sede_id');
        
        // Obtener estadísticas básicas de cada endpoint
        $insumos = $this->insumos($request)->getData()->data;
        $movimientos = $this->movimientosEstados($request)->getData()->data;
        $faltantes = $this->insumosFaltantes($request)->getData()->data;
        $pacientes = $this->pacientesEstados($request)->getData()->data;

        // Información de la sede si se especifica
        $sedeInfo = null;
        if ($sedeId) {
            $sedeInfo = DB::table('sedes')
                ->join('hospitales', 'sedes.hospital_id', '=', 'hospitales.id')
                ->where('sedes.id', $sedeId)
                ->select('sedes.nombre as sede_nombre', 'hospitales.nombre as hospital_nombre', 'sedes.tipo_almacen')
                ->first();
        }

        return response()->json([
            'status' => true,
            'data' => [
                'sede_info' => $sedeInfo,
                'resumen_insumos' => [
                    'total' => $insumos->total_general,
                    'tendencia' => $insumos->tendencia,
                    'mensaje' => $insumos->mensaje_comparacion
                ],
                'resumen_movimientos' => [
                    'total' => $movimientos->total_movimientos,
                    'recibidos' => $movimientos->por_estado->recibido ?? 0,
                    'pendientes' => $movimientos->por_estado->pendiente ?? 0,
                    'como_origen' => $movimientos->desglose_sede->como_origen->total ?? null,
                    'como_destino' => $movimientos->desglose_sede->como_destino->total ?? null
                ],
                'resumen_faltantes' => [
                    'total_problemas' => $faltantes->resumen->total_problemas,
                    'sin_stock' => $faltantes->resumen->sin_stock,
                    'stock_bajo' => $faltantes->resumen->stock_bajo
                ],
                'resumen_pacientes' => [
                    'total_despachos' => $pacientes->total_despachos,
                    'pacientes_unicos' => $pacientes->total_pacientes_unicos,
                    'entregados' => $pacientes->por_estado->entregado ?? 0
                ],
                'fecha_actualizacion' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Generar mensaje de comparación mensual
     */
    private function generarMensajeComparacion($porcentaje, $tendencia): string
    {
        $porcentajeAbs = abs($porcentaje);
        
        switch ($tendencia) {
            case 'aumento':
                return "{$porcentajeAbs}% más que el mes pasado";
            case 'disminucion':
                return "{$porcentajeAbs}% menos que el mes pasado";
            default:
                return "Sin cambios respecto al mes pasado";
        }
    }

    /**
     * Dashboard específico por sede
     * GET /api/estadisticas/dashboard/sede/{sede_id}
     */
    public function dashboardPorSede(Request $request, $sede_id)
    {
        // Agregar sede_id al request para reutilizar la lógica existente
        $request->merge(['sede_id' => $sede_id]);
        return $this->dashboard($request);
    }

    /**
     * Estadísticas de insumos específicas por sede
     * GET /api/estadisticas/insumos/sede/{sede_id}
     */
    public function insumosPorSede(Request $request, $sede_id)
    {
        $request->merge(['sede_id' => $sede_id]);
        return $this->insumos($request);
    }

    /**
     * Estadísticas de movimientos específicas por sede
     * GET /api/estadisticas/movimientos-estados/sede/{sede_id}
     */
    public function movimientosEstadosPorSede(Request $request, $sede_id)
    {
        $request->merge(['sede_id' => $sede_id]);
        return $this->movimientosEstados($request);
    }

    /**
     * Estadísticas de insumos faltantes específicas por sede
     * GET /api/estadisticas/insumos-faltantes/sede/{sede_id}
     */
    public function insumosFaltantesPorSede(Request $request, $sede_id)
    {
        $request->merge(['sede_id' => $sede_id]);
        return $this->insumosFaltantes($request);
    }

    /**
     * Estadísticas de pacientes específicas por sede
     * GET /api/estadisticas/pacientes-estados/sede/{sede_id}
     */
    public function pacientesEstadosPorSede(Request $request, $sede_id)
    {
        $request->merge(['sede_id' => $sede_id]);
        return $this->pacientesEstados($request);
    }

    /**
     * Estadísticas de flujo de inventario por sede
     * GET /api/estadisticas/flujo-inventario
     */
    public function flujoInventario(Request $request)
    {
        $sedeId = $request->get('sede_id');
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        
        // Fechas por defecto: mes actual
        $mesActual = Carbon::now();
        $mesAnterior = Carbon::now()->subMonth();
        
        if (!$fechaDesde) {
            $fechaDesde = $mesActual->startOfMonth()->toDateString();
        }
        if (!$fechaHasta) {
            $fechaHasta = $mesActual->endOfMonth()->toDateString();
        }

        // Información de la sede
        $sedeInfo = null;
        if ($sedeId) {
            $sedeInfo = DB::table('sedes')
                ->join('hospitales', 'sedes.hospital_id', '=', 'hospitales.id')
                ->where('sedes.id', $sedeId)
                ->select(
                    'sedes.nombre as sede_nombre', 
                    'hospitales.nombre as hospital_nombre',
                    'sedes.tipo_almacen'
                )
                ->first();
        }

        // 1. ENTRADAS
        $entradas = $this->calcularEntradas($sedeId, $fechaDesde, $fechaHasta);
        $entradasMesAnterior = $this->calcularEntradas($sedeId, 
            $mesAnterior->startOfMonth()->toDateString(), 
            $mesAnterior->endOfMonth()->toDateString()
        );

        // 2. SALIDAS (Transferencias + Despachos)
        $salidas = $this->calcularSalidas($sedeId, $fechaDesde, $fechaHasta);
        $salidasMesAnterior = $this->calcularSalidas($sedeId,
            $mesAnterior->startOfMonth()->toDateString(), 
            $mesAnterior->endOfMonth()->toDateString()
        );

        // 3. TRANSFERENCIAS (solo salidas hacia otras sedes)
        $transferencias = $this->calcularTransferencias($sedeId, $fechaDesde, $fechaHasta);
        $transferenciasMesAnterior = $this->calcularTransferencias($sedeId,
            $mesAnterior->startOfMonth()->toDateString(), 
            $mesAnterior->endOfMonth()->toDateString()
        );

        // 4. DESPACHOS A PACIENTES
        $despachosPacientes = $this->calcularDespachosPacientes($sedeId, $fechaDesde, $fechaHasta);
        $despachosPacientesMesAnterior = $this->calcularDespachosPacientes($sedeId,
            $mesAnterior->startOfMonth()->toDateString(), 
            $mesAnterior->endOfMonth()->toDateString()
        );

        return response()->json([
            'status' => true,
            'data' => [
                'sede_info' => $sedeInfo,
                'periodo' => [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta
                ],
                'entradas' => [
                    'total' => $entradas['total'],
                    'por_movimientos' => $entradas['por_movimientos'],
                    'por_ingresos_directos' => $entradas['por_ingresos_directos'],
                    'variacion_mes_anterior' => $this->calcularVariacion($entradas['total'], $entradasMesAnterior['total']),
                    'detalle_variacion' => [
                        'mes_actual' => $entradas['total'],
                        'mes_anterior' => $entradasMesAnterior['total'],
                        'diferencia' => $entradas['total'] - $entradasMesAnterior['total']
                    ]
                ],
                'salidas' => [
                    'total' => $salidas['total'],
                    'variacion_mes_anterior' => $this->calcularVariacion($salidas['total'], $salidasMesAnterior['total']),
                    'detalle_variacion' => [
                        'mes_actual' => $salidas['total'],
                        'mes_anterior' => $salidasMesAnterior['total'],
                        'diferencia' => $salidas['total'] - $salidasMesAnterior['total']
                    ]
                ],
                'transferencias' => [
                    'total' => $transferencias['total'],
                    'por_estado' => $transferencias['por_estado'],
                    'variacion_mes_anterior' => $this->calcularVariacion($transferencias['total'], $transferenciasMesAnterior['total']),
                    'detalle_variacion' => [
                        'mes_actual' => $transferencias['total'],
                        'mes_anterior' => $transferenciasMesAnterior['total'],
                        'diferencia' => $transferencias['total'] - $transferenciasMesAnterior['total']
                    ]
                ],
                'despachos_pacientes' => [
                    'total' => $despachosPacientes['total'],
                    'por_estado' => $despachosPacientes['por_estado'],
                    'variacion_mes_anterior' => $this->calcularVariacion($despachosPacientes['total'], $despachosPacientesMesAnterior['total']),
                    'detalle_variacion' => [
                        'mes_actual' => $despachosPacientes['total'],
                        'mes_anterior' => $despachosPacientesMesAnterior['total'],
                        'diferencia' => $despachosPacientes['total'] - $despachosPacientesMesAnterior['total']
                    ]
                ],
                'resumen' => [
                    'balance_neto' => $entradas['total'] - $salidas['total'],
                    'rotacion_inventario' => $entradas['total'] > 0 ? round(($salidas['total'] / $entradas['total']) * 100, 1) : 0,
                    'actividad_total' => $entradas['total'] + $salidas['total']
                ],
                'fecha_actualizacion' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Estadísticas de flujo específicas por sede
     * GET /api/estadisticas/flujo-inventario/sede/{sede_id}
     */
    public function flujoInventarioPorSede(Request $request, $sede_id)
    {
        $request->merge(['sede_id' => $sede_id]);
        return $this->flujoInventario($request);
    }

    /**
     * Calcular entradas (movimientos + ingresos directos)
     */
    private function calcularEntradas($sedeId, $fechaDesde, $fechaHasta)
    {
        // Entradas por movimientos (donde la sede es destino)
        $queryMovimientos = DB::table('movimientos_stock')
            ->where('estado', 'recibido')
            ->whereDate('created_at', '>=', $fechaDesde)
            ->whereDate('created_at', '<=', $fechaHasta);

        if ($sedeId) {
            $queryMovimientos->where('destino_sede_id', $sedeId);
        }

        $entradasMovimientos = $queryMovimientos->count();

        // Entradas por ingresos directos
        $queryIngresos = DB::table('ingresos_directos')
            ->where('estado', 'procesado')
            ->whereDate('fecha_ingreso', '>=', $fechaDesde)
            ->whereDate('fecha_ingreso', '<=', $fechaHasta);

        if ($sedeId) {
            $queryIngresos->where('sede_id', $sedeId);
        }

        $entradasIngresos = $queryIngresos->count();

        return [
            'total' => $entradasMovimientos + $entradasIngresos,
            'por_movimientos' => $entradasMovimientos,
            'por_ingresos_directos' => $entradasIngresos
        ];
    }

    /**
     * Calcular salidas (transferencias + despachos pacientes)
     */
    private function calcularSalidas($sedeId, $fechaDesde, $fechaHasta)
    {
        // Salidas por transferencias (donde la sede es origen)
        $queryTransferencias = DB::table('movimientos_stock')
            ->whereIn('estado', ['despachado', 'entregado', 'recibido'])
            ->whereDate('created_at', '>=', $fechaDesde)
            ->whereDate('created_at', '<=', $fechaHasta);

        if ($sedeId) {
            $queryTransferencias->where('origen_sede_id', $sedeId);
        }

        $salidasTransferencias = $queryTransferencias->count();

        // Salidas por despachos a pacientes
        $queryDespachos = DB::table('despachos_pacientes')
            ->whereIn('estado', ['despachado', 'entregado'])
            ->whereDate('fecha_despacho', '>=', $fechaDesde)
            ->whereDate('fecha_despacho', '<=', $fechaHasta);

        if ($sedeId) {
            $queryDespachos->where('sede_id', $sedeId);
        }

        $salidasDespachos = $queryDespachos->count();

        return [
            'total' => $salidasTransferencias + $salidasDespachos
        ];
    }

    /**
     * Calcular transferencias específicamente
     */
    private function calcularTransferencias($sedeId, $fechaDesde, $fechaHasta)
    {
        $query = DB::table('movimientos_stock')
            ->whereDate('created_at', '>=', $fechaDesde)
            ->whereDate('created_at', '<=', $fechaHasta);

        if ($sedeId) {
            $query->where('origen_sede_id', $sedeId);
        }

        $transferencias = $query->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        $estados = ['pendiente', 'despachado', 'entregado', 'recibido', 'cancelado'];
        $porEstado = [];
        $total = 0;

        foreach ($estados as $estado) {
            $cantidad = $transferencias->get($estado)?->total ?? 0;
            $porEstado[$estado] = $cantidad;
            $total += $cantidad;
        }

        return [
            'total' => $total,
            'por_estado' => $porEstado
        ];
    }

    /**
     * Calcular despachos a pacientes específicamente
     */
    private function calcularDespachosPacientes($sedeId, $fechaDesde, $fechaHasta)
    {
        $query = DB::table('despachos_pacientes')
            ->whereDate('fecha_despacho', '>=', $fechaDesde)
            ->whereDate('fecha_despacho', '<=', $fechaHasta);

        if ($sedeId) {
            $query->where('sede_id', $sedeId);
        }

        $despachos = $query->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        $estados = ['pendiente', 'despachado', 'entregado', 'cancelado'];
        $porEstado = [];
        $total = 0;

        foreach ($estados as $estado) {
            $cantidad = $despachos->get($estado)?->total ?? 0;
            $porEstado[$estado] = $cantidad;
            $total += $cantidad;
        }

        return [
            'total' => $total,
            'por_estado' => $porEstado
        ];
    }

    /**
     * Calcular variación porcentual
     */
    private function calcularVariacion($actual, $anterior)
    {
        if ($anterior == 0) {
            return $actual > 0 ? [
                'porcentaje' => 100,
                'tendencia' => 'aumento',
                'mensaje' => 'Nuevo registro (100% más que el mes anterior)'
            ] : [
                'porcentaje' => 0,
                'tendencia' => 'igual',
                'mensaje' => 'Sin actividad en ambos períodos'
            ];
        }

        $porcentaje = round((($actual - $anterior) / $anterior) * 100, 1);
        
        return [
            'porcentaje' => $porcentaje,
            'tendencia' => $porcentaje > 0 ? 'aumento' : ($porcentaje < 0 ? 'disminucion' : 'igual'),
            'mensaje' => $this->generarMensajeVariacion($porcentaje)
        ];
    }

    /**
     * Generar mensaje descriptivo de variación
     */
    private function generarMensajeVariacion($porcentaje)
    {
        if ($porcentaje > 0) {
            return "Aumento del {$porcentaje}% respecto al mes anterior";
        } elseif ($porcentaje < 0) {
            return "Disminución del " . abs($porcentaje) . "% respecto al mes anterior";
        } else {
            return "Sin cambios respecto al mes anterior";
        }
    }

    /**
     * Estadísticas de insumos recientes por tipo de movimiento
     * GET /api/estadisticas/insumos-recientes
     */
    public function insumosRecientes(Request $request)
    {
        $sedeId = $request->get('sede_id');
        $limite = $request->get('limite', 3); // Por defecto últimos 3
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        
        // Fechas por defecto: últimos 30 días
        if (!$fechaDesde) {
            $fechaDesde = Carbon::now()->subDays(30)->toDateString();
        }
        if (!$fechaHasta) {
            $fechaHasta = Carbon::now()->toDateString();
        }

        // Información de la sede
        $sedeInfo = null;
        if ($sedeId) {
            $sedeInfo = DB::table('sedes')
                ->join('hospitales', 'sedes.hospital_id', '=', 'hospitales.id')
                ->where('sedes.id', $sedeId)
                ->select(
                    'sedes.nombre as sede_nombre', 
                    'hospitales.nombre as hospital_nombre',
                    'sedes.tipo_almacen'
                )
                ->first();
        }

        // 1. ENTRADAS - Insumos recientes
        $entradasMovimientos = $this->obtenerInsumosRecientesEntradas($sedeId, $fechaDesde, $fechaHasta, $limite, 'movimientos');
        $entradasIngresos = $this->obtenerInsumosRecientesEntradas($sedeId, $fechaDesde, $fechaHasta, $limite, 'ingresos');
        
        // Combinar y obtener totales únicos
        $entradasTotales = $this->combinarInsumosRecientes([$entradasMovimientos, $entradasIngresos], $limite);

        // 2. SALIDAS - Insumos recientes
        $salidasTransferencias = $this->obtenerInsumosRecientesSalidas($sedeId, $fechaDesde, $fechaHasta, $limite, 'transferencias');
        $salidasDespachos = $this->obtenerInsumosRecientesSalidas($sedeId, $fechaDesde, $fechaHasta, $limite, 'despachos');

        // 3. TRANSFERENCIAS específicas
        $transferenciasDetalle = $this->obtenerInsumosRecientesTransferencias($sedeId, $fechaDesde, $fechaHasta, $limite);

        // 4. DESPACHOS A PACIENTES específicos
        $despachosPacientesDetalle = $this->obtenerInsumosRecientesDespachosPacientes($sedeId, $fechaDesde, $fechaHasta, $limite);

        return response()->json([
            'status' => true,
            'data' => [
                'sede_info' => $sedeInfo,
                'periodo' => [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta
                ],
                'limite_resultados' => $limite,
                'entradas' => [
                    'totales' => $entradasTotales,
                    'por_movimientos' => $entradasMovimientos,
                    'por_ingresos_directos' => $entradasIngresos,
                    'resumen' => [
                        'total_insumos_diferentes' => count($entradasTotales),
                        'cantidad_total' => array_sum(array_column($entradasTotales, 'cantidad_total'))
                    ]
                ],
                'salidas' => [
                    'totales' => $this->combinarInsumosRecientes([$salidasTransferencias, $salidasDespachos], $limite),
                    'resumen' => [
                        'total_insumos_diferentes' => count($this->combinarInsumosRecientes([$salidasTransferencias, $salidasDespachos], $limite)),
                        'cantidad_total' => array_sum(array_column($this->combinarInsumosRecientes([$salidasTransferencias, $salidasDespachos], $limite), 'cantidad_total'))
                    ]
                ],
                'transferencias' => [
                    'insumos_recientes' => $transferenciasDetalle,
                    'resumen' => [
                        'total_insumos_diferentes' => count($transferenciasDetalle),
                        'cantidad_total' => array_sum(array_column($transferenciasDetalle, 'cantidad_total'))
                    ]
                ],
                'despachos_pacientes' => [
                    'insumos_recientes' => $despachosPacientesDetalle,
                    'resumen' => [
                        'total_insumos_diferentes' => count($despachosPacientesDetalle),
                        'cantidad_total' => array_sum(array_column($despachosPacientesDetalle, 'cantidad_total'))
                    ]
                ],
                'fecha_actualizacion' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Estadísticas de insumos recientes específicas por sede
     * GET /api/estadisticas/insumos-recientes/sede/{sede_id}
     */
    public function insumosRecientesPorSede(Request $request, $sede_id)
    {
        $request->merge(['sede_id' => $sede_id]);
        return $this->insumosRecientes($request);
    }

    /**
     * Obtener insumos recientes de entradas (movimientos o ingresos directos)
     */
    private function obtenerInsumosRecientesEntradas($sedeId, $fechaDesde, $fechaHasta, $limite, $tipo)
    {
        if ($tipo === 'movimientos') {
            // Entradas por movimientos (donde la sede es destino)
            $query = DB::table('movimientos_stock as ms')
                ->join('lotes_grupos as lg', 'ms.codigo_grupo', '=', 'lg.codigo')
                ->join('lotes as l', 'lg.lote_id', '=', 'l.id')
                ->join('insumos as i', 'l.id_insumo', '=', 'i.id')
                ->where('ms.estado', 'recibido')
                ->whereDate('ms.created_at', '>=', $fechaDesde)
                ->whereDate('ms.created_at', '<=', $fechaHasta);

            if ($sedeId) {
                $query->where('ms.destino_sede_id', $sedeId);
            }

        } else { // ingresos directos
            $query = DB::table('ingresos_directos as id')
                ->join('lotes_grupos as lg', 'id.codigo_lotes_grupo', '=', 'lg.codigo')
                ->join('lotes as l', 'lg.lote_id', '=', 'l.id')
                ->join('insumos as i', 'l.id_insumo', '=', 'i.id')
                ->where('id.estado', 'procesado')
                ->whereDate('id.fecha_ingreso', '>=', $fechaDesde)
                ->whereDate('id.fecha_ingreso', '<=', $fechaHasta);

            if ($sedeId) {
                $query->where('id.sede_id', $sedeId);
            }
        }

        return $query->select(
                'i.id as insumo_id',
                'i.nombre as insumo_nombre',
                'i.codigo as insumo_codigo',
                'i.presentacion as insumo_presentacion',
                DB::raw('SUM(lg.cantidad_entrada) as cantidad_total'),
                DB::raw('COUNT(DISTINCT l.id) as total_lotes'),
                DB::raw('MAX(' . ($tipo === 'movimientos' ? 'ms.created_at' : 'id.fecha_ingreso') . ') as fecha_reciente')
            )
            ->groupBy('i.id', 'i.nombre', 'i.codigo', 'i.presentacion')
            ->orderBy('fecha_reciente', 'desc')
            ->limit($limite)
            ->get()
            ->toArray();
    }

    /**
     * Obtener insumos recientes de salidas
     */
    private function obtenerInsumosRecientesSalidas($sedeId, $fechaDesde, $fechaHasta, $limite, $tipo)
    {
        if ($tipo === 'transferencias') {
            // Salidas por transferencias (donde la sede es origen)
            $query = DB::table('movimientos_stock as ms')
                ->join('lotes_grupos as lg', 'ms.codigo_grupo', '=', 'lg.codigo')
                ->join('lotes as l', 'lg.lote_id', '=', 'l.id')
                ->join('insumos as i', 'l.id_insumo', '=', 'i.id')
                ->whereIn('ms.estado', ['despachado', 'entregado', 'recibido'])
                ->whereDate('ms.created_at', '>=', $fechaDesde)
                ->whereDate('ms.created_at', '<=', $fechaHasta);

            if ($sedeId) {
                $query->where('ms.origen_sede_id', $sedeId);
            }

            $cantidadField = 'lg.cantidad_salida';
            $fechaField = 'ms.created_at';

        } else { // despachos
            $query = DB::table('despachos_pacientes as dp')
                ->join('lotes_grupos as lg', 'dp.codigo_despacho', '=', 'lg.codigo')
                ->join('lotes as l', 'lg.lote_id', '=', 'l.id')
                ->join('insumos as i', 'l.id_insumo', '=', 'i.id')
                ->whereIn('dp.estado', ['despachado', 'entregado'])
                ->whereDate('dp.fecha_despacho', '>=', $fechaDesde)
                ->whereDate('dp.fecha_despacho', '<=', $fechaHasta);

            if ($sedeId) {
                $query->where('dp.sede_id', $sedeId);
            }

            $cantidadField = 'lg.cantidad_salida';
            $fechaField = 'dp.fecha_despacho';
        }

        return $query->select(
                'i.id as insumo_id',
                'i.nombre as insumo_nombre',
                'i.codigo as insumo_codigo',
                'i.presentacion as insumo_presentacion',
                DB::raw("SUM($cantidadField) as cantidad_total"),
                DB::raw('COUNT(DISTINCT l.id) as total_lotes'),
                DB::raw("MAX($fechaField) as fecha_reciente")
            )
            ->groupBy('i.id', 'i.nombre', 'i.codigo', 'i.presentacion')
            ->orderBy('fecha_reciente', 'desc')
            ->limit($limite)
            ->get()
            ->toArray();
    }

    /**
     * Obtener insumos recientes específicos de transferencias
     */
    private function obtenerInsumosRecientesTransferencias($sedeId, $fechaDesde, $fechaHasta, $limite)
    {
        return $this->obtenerInsumosRecientesSalidas($sedeId, $fechaDesde, $fechaHasta, $limite, 'transferencias');
    }

    /**
     * Obtener insumos recientes específicos de despachos a pacientes
     */
    private function obtenerInsumosRecientesDespachosPacientes($sedeId, $fechaDesde, $fechaHasta, $limite)
    {
        return $this->obtenerInsumosRecientesSalidas($sedeId, $fechaDesde, $fechaHasta, $limite, 'despachos');
    }

    /**
     * Combinar arrays de insumos recientes y eliminar duplicados
     */
    private function combinarInsumosRecientes($arrays, $limite)
    {
        $combinados = [];
        $insumosVistos = [];

        foreach ($arrays as $array) {
            foreach ($array as $insumo) {
                $insumoId = $insumo->insumo_id;
                
                if (!isset($insumosVistos[$insumoId])) {
                    $insumosVistos[$insumoId] = true;
                    $combinados[] = $insumo;
                } else {
                    // Si ya existe, sumar las cantidades
                    foreach ($combinados as &$existente) {
                        if ($existente->insumo_id == $insumoId) {
                            $existente->cantidad_total += $insumo->cantidad_total;
                            $existente->total_lotes += $insumo->total_lotes;
                            // Mantener la fecha más reciente
                            if ($insumo->fecha_reciente > $existente->fecha_reciente) {
                                $existente->fecha_reciente = $insumo->fecha_reciente;
                            }
                            break;
                        }
                    }
                }
            }
        }

        // Ordenar por fecha más reciente y limitar
        usort($combinados, function($a, $b) {
            return strtotime($b->fecha_reciente) - strtotime($a->fecha_reciente);
        });

        return array_slice($combinados, 0, $limite);
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
            default => 'almacenes_centrales',
        };
    }
}
