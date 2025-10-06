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

        $query = MovimientoStock::query();

        if ($sedeId) {
            $query->where(function($q) use ($sedeId) {
                $q->where('origen_sede_id', $sedeId)
                  ->orWhere('destino_sede_id', $sedeId);
            });
        }

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
                'total_movimientos' => $total,
                'por_estado' => $resultado,
                'porcentajes' => $porcentajes,
                'periodo' => [
                    'desde' => $fechaDesde ?? 'inicio',
                    'hasta' => $fechaHasta ?? 'hoy'
                ]
            ]
        ]);
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
                    'pendientes' => $movimientos->por_estado->pendiente ?? 0
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
