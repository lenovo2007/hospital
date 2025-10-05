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

                // Crear grupo de lote para los items del movimiento (genera su propio código)
                [$codigoGrupo, $grupoItems] = LoteGrupo::crearGrupo($data['items']);
                
                // Usar el mismo código del grupo de lotes para el despacho
                $codigoDespacho = $codigoGrupo;

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

                // $codigoGrupo ya tiene el valor correcto del despacho
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

        // Agregar datos de insumos para cada despacho
        $despachos->getCollection()->transform(function ($despacho) {
            $insumos = $this->obtenerInsumosDespacho($despacho->codigo_despacho);
            $despacho->insumos_despachados = $insumos;
            return $despacho;
        });

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
        $despacho = DespachoPaciente::with(['hospital', 'sede', 'usuario'])
            ->where('status', true)
            ->findOrFail($id);

        // Agregar datos de insumos
        $insumos = $this->obtenerInsumosDespacho($despacho->codigo_despacho);
        $despacho->insumos_despachados = $insumos;

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
     * Listar despachos por sede con datos de insumos
     */
    public function porSede(Request $request, $sede_id)
    {
        try {
            // Primero verificar si la sede existe
            $sedeExists = DB::table('sedes')->where('id', $sede_id)->exists();
            if (!$sedeExists) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Sede no encontrada.',
                    'data' => null,
                ], 404);
            }

            // Consulta básica sin relaciones primero
            $query = DespachoPaciente::where('status', true)
                ->where('sede_id', $sede_id);

            // Filtros opcionales
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

            // Cargar relaciones manualmente para evitar errores
            $despachos->getCollection()->transform(function ($despacho) {
                try {
                    // Cargar relaciones de forma segura
                    $despacho->load(['hospital', 'sede', 'usuario']);
                    
                    // Obtener insumos despachados
                    $insumos = $this->obtenerInsumosDespacho($despacho->codigo_despacho);
                    $despacho->insumos_despachados = $insumos;
                } catch (\Exception $e) {
                    // Si hay error en las relaciones, continuar sin ellas
                    $despacho->insumos_despachados = [];
                    \Log::warning('Error cargando relaciones para despacho: ' . $despacho->id, [
                        'error' => $e->getMessage()
                    ]);
                }
                return $despacho;
            });

            return response()->json([
                'status' => true,
                'data' => $despachos,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en porSede: ' . $e->getMessage(), [
                'sede_id' => $sede_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error interno del servidor.',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Versión simple para diagnosticar el problema
     */
    public function porSedeSimple(Request $request, $sede_id)
    {
        try {
            // Consulta muy básica sin relaciones
            $despachos = DB::table('despachos_pacientes')
                ->where('status', true)
                ->where('sede_id', $sede_id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => true,
                'mensaje' => 'Consulta simple exitosa',
                'data' => $despachos,
                'count' => $despachos->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error en consulta simple',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
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
     * Obtener los insumos despachados para un despacho específico
     */
    private function obtenerInsumosDespacho($codigoDespacho)
    {
        try {
            if (empty($codigoDespacho)) {
                \Log::info('Código de despacho vacío');
                return collect([]);
            }

            \Log::info('Buscando insumos para despacho: ' . $codigoDespacho);

            // Primero verificar si existen registros en lotes_grupos con este código
            $lotesGrupos = DB::table('lotes_grupos')
                ->where('codigo', $codigoDespacho)
                ->get();

            \Log::info('Registros encontrados en lotes_grupos: ' . $lotesGrupos->count(), [
                'codigo' => $codigoDespacho,
                'registros' => $lotesGrupos->toArray()
            ]);

            if ($lotesGrupos->isEmpty()) {
                return collect([]);
            }

            $resultado = DB::table('lotes_grupos')
                ->leftJoin('lotes', 'lotes_grupos.lote_id', '=', 'lotes.id')
                ->leftJoin('insumos', 'lotes.id_insumo', '=', 'insumos.id')
                ->where('lotes_grupos.codigo', $codigoDespacho)
                ->where('lotes_grupos.status', 'activo')
                ->select(
                    'insumos.id as insumo_id',
                    'insumos.nombre as insumo_nombre',
                    'insumos.codigo as insumo_codigo',
                    'insumos.codigo_alterno as insumo_codigo_alterno',
                    'insumos.presentacion as insumo_presentacion',
                    'lotes.id as lote_id',
                    'lotes.numero_lote',
                    'lotes.fecha_vencimiento',
                    'lotes_grupos.cantidad as cantidad_despachada',
                    'lotes_grupos.id as lote_grupo_id'
                )
                ->get();

            \Log::info('Resultado final de insumos: ' . $resultado->count(), [
                'codigo' => $codigoDespacho,
                'insumos' => $resultado->toArray()
            ]);

            return $resultado;
        } catch (\Exception $e) {
            \Log::error('Error obteniendo insumos para despacho: ' . $codigoDespacho, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return collect([]);
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
