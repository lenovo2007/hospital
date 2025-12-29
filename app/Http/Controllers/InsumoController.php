<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class InsumoController extends Controller
{
    // GET /api/insumos
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) {
            $status = 'activo';
        }

        $query = Insumo::query();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $items = $query->latest()->get();
        $mensaje = $items->count() > 0 ? 'Listado de insumos.' : 'insumos no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/insumos/sede/{sede_id}
    public function indexBySede(Request $request, int $sede_id)
    {
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) {
            $status = 'activo';
        }

        $query = Insumo::query()->where('sede_id', $sede_id);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de insumos por sede.' : 'insumos no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/insumos/codigo/{codigo}
    public function showByCodigo(Request $request, string $codigo)
    {
        // Buscar por código principal o código alterno
        $insumo = Insumo::where('codigo', $codigo)
            ->orWhere('codigo_alterno', $codigo)
            ->first();
            
        if (!$insumo) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Insumo no encontrado por ese código o código alterno.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        // Si el insumo tiene descripción, intentar extraer información adicional
        $descripcion = $insumo->descripcion;
        if ($descripcion) {
            $parsed = $this->parseDescripcion($descripcion);
            
            // Actualizar el insumo con la información parseada si no está definida
            $updateData = [];
            if (empty($insumo->nombre)) $updateData['nombre'] = $parsed['nombre'];
            if (empty($insumo->tipo)) $updateData['tipo'] = $parsed['tipo'];
            if (empty($insumo->unidad_medida)) $updateData['unidad_medida'] = $parsed['unidad_medida'];
            if (empty($insumo->cantidad_por_paquete)) $updateData['cantidad_por_paquete'] = $parsed['cantidad_por_paquete'];
            if (empty($insumo->presentacion)) $updateData['presentacion'] = $parsed['presentacion'];
            
            if (!empty($updateData)) {
                $insumo->update($updateData);
                $insumo->refresh();
            }
            
            // Si el código está vacío pero hay un código generado, actualizarlo
            if (empty($insumo->codigo) && !empty($parsed['codigo'])) {
                $insumo->codigo = $parsed['codigo'];
                $insumo->save();
                $insumo->refresh();
            }
        }

        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de insumo por código.',
            'data' => $insumo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/insumos/{insumo}
    public function show(Insumo $insumo)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de insumo.',
            'data' => $insumo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/insumos
    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['nullable','string','max:100','unique:insumos,codigo'],
            'codigo_alterno' => ['nullable','string','max:100'],
            'nombre' => ['required','string','max:255'],
            'tipo' => ['required','string','max:100'],
            'unidad_medida' => ['required','string','max:100'],
            'cantidad_por_paquete' => ['required','integer','min:0'],
            'descripcion' => ['nullable','string'],
            'presentacion' => ['nullable','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'codigo.unique' => 'El código de insumo ya ha sido registrado previamente.',
            'presentacion.max' => 'La presentación no debe exceder los 255 caracteres.',
        ]);

        // Nota: No auto-procesar descripcion en solicitudes manuales.

        // Si no se proporcionó un código alterno y existe código principal, copiarlo.
        if (empty($data['codigo_alterno']) && !empty($data['codigo'])) {
            $data['codigo_alterno'] = $data['codigo'];
        }
        // Nunca guardar código como cadena vacía: usar null
        if (isset($data['codigo']) && $data['codigo'] === '') {
            $data['codigo'] = null;
        }

        $insumo = Insumo::create($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Insumo creado.',
            'data' => $insumo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/insumos/{insumo}
    public function update(Request $request, Insumo $insumo)
    {
        $data = $request->validate([
            'codigo' => ['sometimes','nullable','string','max:100', Rule::unique('insumos','codigo')->ignore($insumo->id)],
            'codigo_alterno' => ['nullable','string','max:100'],
            'nombre' => ['sometimes','required','string','max:255'],
            'tipo' => ['sometimes','required','string','max:100'],
            'unidad_medida' => ['sometimes','required','string','max:100'],
            'cantidad_por_paquete' => ['sometimes','required','integer','min:0'],
            'descripcion' => ['nullable','string'],
            'presentacion' => ['nullable','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'codigo.unique' => 'El código de insumo ya ha sido registrado para otro insumo.',
            'presentacion.max' => 'La presentación no debe exceder los 255 caracteres.',
        ]);

        // Nota: No auto-procesar descripcion en solicitudes manuales.

        // Si se está actualizando el código y no se proporciona código alterno,
        // mover el código al alterno para mantener la búsqueda y dejar el principal como null si está vacío
        if (array_key_exists('codigo', $data) && !array_key_exists('codigo_alterno', $data)) {
            if (!empty($data['codigo'])) {
                $data['codigo_alterno'] = $data['codigo'];
            }
        }
        if (array_key_exists('codigo', $data) && $data['codigo'] === '') {
            $data['codigo'] = null;
        }

        $insumo->update($data);
        $insumo->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Insumo actualizado.',
            'data' => $insumo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/insumos/codigo/{codigo}
    public function updateByCodigo(Request $request, string $codigo)
    {
        // Buscar por código principal o código alterno
        $insumo = Insumo::where('codigo', $codigo)
            ->orWhere('codigo_alterno', $codigo)
            ->first();
            
        if (!$insumo) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Insumo no encontrado por ese código o código alterno.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            // No permitir cambiar el código desde este endpoint
            'nombre' => ['sometimes','required','string','max:255'],
            'tipo' => ['sometimes','required','string','max:100'],
            'unidad_medida' => ['sometimes','required','string','max:100'],
            'cantidad_por_paquete' => ['sometimes','required','integer','min:0'],
            'descripcion' => ['nullable','string'],
            'codigo_alterno' => ['nullable','string','max:100'],
            'presentacion' => ['nullable','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);

        // Nota: No auto-procesar descripcion en solicitudes manuales por código.

        $insumo->update($data);
        $insumo->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Insumo actualizado por código.',
            'data' => $insumo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/insumos/{insumo}
    public function destroy(Insumo $insumo)
    {
        $insumo->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Insumo eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/insumos/import
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:xls,xlsx','max:10240']
        ]);

        // Prechecks: required PHP extensions for reading XLSX
        $missingExt = [];
        if (!extension_loaded('zip')) { $missingExt[] = 'zip'; }
        if (!extension_loaded('xml')) { $missingExt[] = 'xml'; }
        if (!extension_loaded('mbstring')) { $missingExt[] = 'mbstring'; }
        if ($missingExt) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Extensiones PHP requeridas no disponibles: ' . implode(', ', $missingExt) . '. Habilítalas en php.ini (por ejemplo, extension=zip).',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Dependencia faltante: phpoffice/phpspreadsheet. Ejecute: composer require phpoffice/phpspreadsheet',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            // Allow some headroom for XLSX parsing (local only; adjust as needed)
            @ini_set('memory_limit', '512M');
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('file');
            $path = $file->getRealPath();

            // Detectar tipo de archivo y crear lector apropiado (Xls o Xlsx)
            $inputType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($path);
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputType);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();

            // Columnas del archivo: A = CODIGO, B = DESCRIPCION
            $descripcionCol = 'B';
            $codigoCol = 'A';

            $created = 0; $updated = 0; $skipped = []; $errors = [];
            $rowCount = (int) $sheet->getHighestRow();
            
            // Detectar columnas existentes
            $hasPresentacionColumn = \Illuminate\Support\Facades\Schema::hasColumn('insumos', 'presentacion');
            $hasCodigoAlternoColumn = \Illuminate\Support\Facades\Schema::hasColumn('insumos', 'codigo_alterno');

            for ($i = 2; $i <= $rowCount; $i++) { // desde la fila 2
                $raw = $sheet->getCell($descripcionCol . $i)->getValue();
                $descripcion = is_string($raw) ? trim($raw) : '';
                if ($descripcion === '') { 
                    $skipped[] = ['fila' => $i, 'motivo' => 'Celda de descripción vacía o con solo espacios'];
                    continue; 
                }

                // Obtener el código (columna A)
                $codigoFromA = '';
                try {
                    $rawCodigo = $sheet->getCell($codigoCol . $i)->getValue();
                    // Aceptar códigos numéricos o string; convertir a string y trim
                    if ($rawCodigo !== null) {
                        $codigoFromA = trim((string) $rawCodigo);
                    }
                } catch (\Exception $e) {
                    // Si no hay columna de códigos o está vacía, continuar
                }

                $parsed = $this->parseDescripcion($descripcion);
                
                // Preparar el payload con el código principal leído de la columna A
                $payload = [
                    'codigo' => ($codigoFromA !== '') ? $codigoFromA : null, // Nunca usar cadena vacía, permitir NULL en columna única
                    'nombre' => $parsed['nombre'],
                    'tipo' => $parsed['tipo'],
                    'unidad_medida' => $parsed['unidad_medida'],
                    'cantidad_por_paquete' => $parsed['cantidad_por_paquete'],
                    'descripcion' => $descripcion,
                    'status' => 'activo',
                ];

                // Asignar un código alterno derivado para facilitar búsquedas secundarias
                $payload['codigo_alterno'] = $parsed['codigo'];
                
                // Agregar presentación si la columna existe
                if ($hasPresentacionColumn) {
                    $payload['presentacion'] = $parsed['presentacion'];
                }

                try {
                    // Buscar por código principal primero (nuevo requerimiento), luego por código alterno
                    $existing = null;
                    if (!empty($payload['codigo'])) {
                        $existing = Insumo::where('codigo', $payload['codigo'])->first();
                    }
                    if (!$existing && $hasCodigoAlternoColumn && !empty($payload['codigo_alterno'])) {
                        $existing = Insumo::where('codigo_alterno', $payload['codigo_alterno'])->first();
                    }

                    if ($existing) {
                        // Si existe, actualizar pero mantener el código alterno original si no se proporciona uno nuevo
                        if (empty($payload['codigo_alterno']) && $hasCodigoAlternoColumn) {
                            unset($payload['codigo_alterno']);
                        }
                        $existing->update($payload);
                        $updated++;
                    } else {
                        // Si no existe, crear nuevo registro
                        $newInsumo = Insumo::create($payload);
                        
                        // Si no se proporcionó código, asignar codigo = id
                        if (empty($payload['codigo'])) {
                            $newInsumo->codigo = (string) $newInsumo->id;
                            $newInsumo->save();
                        }
                        
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $skipped[] = ['fila' => $i, 'motivo' => 'Error al procesar: ' . $e->getMessage()];
                    $errors[] = [ 'row' => $i, 'descripcion' => $descripcion, 'error' => $e->getMessage() ];
                    Log::warning('Import insumos error', ['row' => $i, 'e' => $e->getMessage()]);
                }
            }

            // Liberar memoria del spreadsheet
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                $reader = null;
            }

            return response()->json([
                'status' => true,
                'mensaje' => 'Importación procesada.',
                'data' => [
                    'creados' => $created,
                    'actualizados' => $updated,
                    'omitidos' => count($skipped),
                    'omitidos_detalle' => $skipped,
                    'errores' => $errors,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al procesar el archivo: ' . $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    // POST /api/hospital/insumos/import
    public function importExcelHospitalInsumos(Request $request)
    {
        $request->validate([
            'file' => ['required','file','max:10240']
        ]);

        // Validación simplificada sin dependencia de fileinfo
        $file = $request->file('file');
        
        // Validar extensión del archivo directamente
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xls', 'xlsx'])) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Tipo de archivo no permitido. Solo se aceptan archivos .xls y .xlsx',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        // Prechecks: required PHP extensions for reading XLSX
        $missingExt = [];
        if (!extension_loaded('zip')) { $missingExt[] = 'zip'; }
        if (!extension_loaded('xml')) { $missingExt[] = 'xml'; }
        if (!extension_loaded('mbstring')) { $missingExt[] = 'mbstring'; }
        if ($missingExt) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Extensiones PHP requeridas no disponibles: ' . implode(', ', $missingExt) . '. Habilítalas en php.ini (por ejemplo, extension=zip).',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Dependencia faltante: phpoffice/phpspreadsheet. Ejecute: composer require phpoffice/phpspreadsheet',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            // Obtener hospital del usuario autenticado
            $user = $request->user();
            if (!$user || !$user->hospital_id) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Usuario no autenticado o sin hospital asignado.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            $hospitalId = $user->hospital_id;
            $sedeId = $user->sede_id ?? null;

            // Allow some headroom for XLSX parsing
            @ini_set('memory_limit', '512M');
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('file');
            $path = $file->getRealPath();

            // Detectar tipo de archivo y crear lector apropiado (Xls o Xlsx)
            // Forzar detección basada en extensión debido a falta de fileinfo
            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension === 'xlsx') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            } elseif ($extension === 'xls') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            } else {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Tipo de archivo no soportado. Use .xls o .xlsx',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }
            
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();

            // Columnas esperadas del archivo:
            // A = id_insumo, B = lote, C = fecha_vencimiento, D = fecha_registro, E = tipo_ingreso, F = cantidad
            $idInsumoCol = 'A';
            $codigoCol = 'B';    // Solo referencia, no se guarda
            $nombreCol = 'C';    // Solo referencia, no se guarda
            $loteCol = 'B';
            $fechaVencimientoCol = 'C';
            $fechaRegistroCol = 'D';
            $tipoIngresoCol = 'E';
            $cantidadCol = 'F';

            $created = 0; $skipped = []; $errors = [];
            $rowCount = (int) $sheet->getHighestRow();

            // Validar tipos de ingreso permitidos
            $tiposIngresoPermitidos = ['ministerio', 'donacion', 'almacenado', 'adquirido', 'devolucion', 'otro'];

            for ($i = 2; $i <= $rowCount; $i++) { // desde la fila 2
                try {
                    // Obtener y validar id_insumo
                    $idInsumoRaw = $sheet->getCell($idInsumoCol . $i)->getValue();
                    $idInsumo = is_numeric($idInsumoRaw) ? (int) $idInsumoRaw : null;
                    
                    if (!$idInsumo) {
                        $skipped[] = ['fila' => $i, 'motivo' => 'ID de insumo inválido o vacío'];
                        continue;
                    }

                    // Verificar que el insumo existe
                    $insumo = \App\Models\Insumo::find($idInsumo);
                    if (!$insumo) {
                        $skipped[] = ['fila' => $i, 'motivo' => "Insumo con ID {$idInsumo} no encontrado"];
                        continue;
                    }

                    // Obtener lote
                    $lote = trim($sheet->getCell($loteCol . $i)->getValue() ?? '');
                    if (empty($lote)) {
                        $skipped[] = ['fila' => $i, 'motivo' => 'Lote vacío'];
                        continue;
                    }

                    // Obtener fecha_vencimiento
                    $fechaVencimientoRaw = $sheet->getCell($fechaVencimientoCol . $i)->getValue();
                    $fechaVencimiento = null;
                    if ($fechaVencimientoRaw) {
                        if (is_numeric($fechaVencimientoRaw)) {
                            $fechaVencimiento = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fechaVencimientoRaw)->format('Y-m-d');
                        } else {
                            $fechaVencimientoParsed = \DateTime::createFromFormat('d/m/Y', trim((string) $fechaVencimientoRaw));
                            if ($fechaVencimientoParsed !== false) {
                                $fechaVencimiento = $fechaVencimientoParsed->format('Y-m-d');
                            } else {
                                $fechaVencimiento = date('Y-m-d', strtotime($fechaVencimientoRaw));
                            }
                        }
                    }

                    // Obtener fecha_registro
                    $fechaRegistroRaw = $sheet->getCell($fechaRegistroCol . $i)->getValue();
                    $fechaRegistro = null;
                    if ($fechaRegistroRaw) {
                        if (is_numeric($fechaRegistroRaw)) {
                            $fechaRegistro = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fechaRegistroRaw)->format('Y-m-d');
                        } else {
                            $fechaRegistroParsed = \DateTime::createFromFormat('d/m/Y', trim((string) $fechaRegistroRaw));
                            if ($fechaRegistroParsed !== false) {
                                $fechaRegistro = $fechaRegistroParsed->format('Y-m-d');
                            } else {
                                $fechaRegistro = date('Y-m-d', strtotime($fechaRegistroRaw));
                            }
                        }
                    } else {
                        $fechaRegistro = now()->format('Y-m-d');
                    }

                    // Obtener tipo_ingreso
                    $tipoIngreso = strtolower(trim($sheet->getCell($tipoIngresoCol . $i)->getValue() ?? ''));
                    if (empty($tipoIngreso) || !in_array($tipoIngreso, $tiposIngresoPermitidos)) {
                        $skipped[] = ['fila' => $i, 'motivo' => "Tipo de ingreso inválido. Permitidos: " . implode(', ', $tiposIngresoPermitidos)];
                        continue;
                    }

                    // Obtener cantidad
                    $cantidadRaw = $sheet->getCell($cantidadCol . $i)->getValue();
                    $cantidad = is_numeric($cantidadRaw) ? (float) $cantidadRaw : null;
                    if (!$cantidad || $cantidad <= 0) {
                        $skipped[] = ['fila' => $i, 'motivo' => 'Cantidad inválida o menor a cero'];
                        continue;
                    }

                    // Usar transacción para asegurar integridad
                    \DB::beginTransaction();

                    try {
                        // Crear registro en la tabla lotes
                        $loteRegistro = \App\Models\Lote::create([
                            'id_insumo' => $idInsumo,
                            'numero_lote' => $lote,
                            'fecha_vencimiento' => $fechaVencimiento,
                            'fecha_ingreso' => $fechaRegistro,
                            'hospital_id' => $hospitalId,
                        ]);

                        // Crear registro de ingreso directo
                        $codigoIngreso = \App\Models\IngresoDirecto::generarCodigoIngreso();
                        $codigoLotesGrupo = 'LOT-' . date('YmdHis') . '-' . $i;
                        
                        $ingresoDirecto = \App\Models\IngresoDirecto::create([
                            'codigo_ingreso' => $codigoIngreso,
                            'tipo_ingreso' => $tipoIngreso,
                            'fecha_ingreso' => $fechaRegistro,
                            'hospital_id' => $hospitalId,
                            'sede_id' => $sedeId,
                            'almacen_tipo' => 'principal',
                            'cantidad_total_items' => (int) $cantidad,
                            'codigo_lotes_grupo' => $codigoLotesGrupo,
                            'user_id' => $user->id,
                            'estado' => 'procesado',
                            'fecha_procesado' => now(),
                            'user_id_procesado' => $user->id,
                            'status' => true,
                        ]);

                        // Crear registro en lotes_grupos para trazabilidad
                        \App\Models\LoteGrupo::create([
                            'codigo' => $codigoLotesGrupo,
                            'lote_id' => $loteRegistro->id,
                            'cantidad_salida' => 0,
                            'cantidad_entrada' => $cantidad,
                            'discrepancia' => false,
                            'status' => 'activo',
                        ]);

                        \DB::commit();
                        $created++;

                    } catch (\Exception $e) {
                        \DB::rollback();
                        throw $e;
                    }

                } catch (\Throwable $e) {
                    $skipped[] = ['fila' => $i, 'motivo' => 'Error al procesar: ' . $e->getMessage()];
                    $errors[] = ['row' => $i, 'error' => $e->getMessage()];
                    Log::warning('Import hospital insumos error', ['row' => $i, 'e' => $e->getMessage()]);
                }
            }

            // Liberar memoria del spreadsheet
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                $reader = null;
            }

            return response()->json([
                'status' => true,
                'mensaje' => 'Importación de insumos de hospital procesada.',
                'data' => [
                    'creados' => $created,
                    'omitidos' => count($skipped),
                    'errores' => count($errors),
                    'detalles_omitidos' => array_slice($skipped, 0, 10), // Primeros 10 omitidos
                    'detalles_errores' => array_slice($errors, 0, 10), // Primeros 10 errores
                    'hospital_id' => $hospitalId,
                    'usuario' => $user->name ?? $user->email,
                ]
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Log::error('Import hospital insumos exception', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'mensaje' => 'Error en importación: ' . $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Parsear DESCRIPCION a atributos de Insumo con reglas de presentación y tipo.
     */
    protected function parseDescripcion(string $descripcion): array
    {
        $descLower = Str::lower($descripcion);

        // Palabras clave para determinar el tipo de insumo
        $farmaceuticoKeys = ['jarabe','suspensión','suspension','tableta','ampolla','capsula','cápsula','ungüento','pomada','solución','solucion'];
        $medicoKeys = ['equipo','material','quirurgico','quirúrgico','medico','médico'];

        // Determinar el tipo basado en palabras clave
        $tipo = 'medico_quirurgico';
        foreach ($farmaceuticoKeys as $k) {
            if (Str::contains($descLower, Str::lower($k))) { 
                $tipo = 'farmaceutico'; 
                break; 
            }
        }
        if ($tipo !== 'farmaceutico') {
            foreach ($medicoKeys as $k) {
                if (Str::contains($descLower, Str::lower($k))) { 
                    $tipo = 'medico_quirurgico'; 
                    break; 
                }
            }
        }

        // Mapeo de términos de presentación con todas sus variantes
        $presentationMap = [
            // AMPOLLA
            'ampolla' => 'AMPOLLA',
            'ampollas' => 'AMPOLLA',
            'amp' => 'AMPOLLA',
            'amp.' => 'AMPOLLA',
            'ampol' => 'AMPOLLA',
            'ampol.' => 'AMPOLLA',
            
            // TABLETA
            'tableta' => 'TABLETA',
            'tabletas' => 'TABLETA',
            'tab' => 'TABLETA',
            'tab.' => 'TABLETA',
            'tabs' => 'TABLETA',
            'tabs.' => 'TABLETA',
            'comp' => 'TABLETA',
            'comp.' => 'TABLETA',
            'comprimido' => 'TABLETA',
            'comprimidos' => 'TABLETA',
            
            // CREMA
            'crema' => 'CREMA',
            'cremas' => 'CREMA',
            'crem' => 'CREMA',
            'crem.' => 'CREMA',
            
            // JARABE
            'jarabe' => 'JARABE',
            'jarabes' => 'JARABE',
            'jbe' => 'JARABE',
            'jbe.' => 'JARABE',
            'jrb' => 'JARABE',
            'jrb.' => 'JARABE',
            
            // UNGÜENTO
            'ungüento' => 'UNGÜENTO',
            'unguento' => 'UNGÜENTO',
            'ung' => 'UNGÜENTO',
            'ung.' => 'UNGÜENTO',
            
            // GOTAS
            'gota' => 'GOTAS',
            'gotas' => 'GOTAS',
            'gts' => 'GOTAS',
            'gts.' => 'GOTAS',
            'gt' => 'GOTAS',
            'gt.' => 'GOTAS',
            
            // FRASCO
            'frasco' => 'FRASCO',
            'frascos' => 'FRASCO',
            'fco' => 'FRASCO',
            'fco.' => 'FRASCO',
            'fras' => 'FRASCO',
            'fras.' => 'FRASCO',
            
            // INHALADOR
            'inhalador' => 'INHALADOR',
            'inhaladores' => 'INHALADOR',
            'inhal' => 'INHALADOR',
            'inhal.' => 'INHALADOR',
            'inhalac' => 'INHALADOR',
            'inhalac.' => 'INHALADOR',
            
            // SUSPENSION
            'suspension' => 'SUSPENSION',
            'suspensión' => 'SUSPENSION',
            'suspencion' => 'SUSPENSION',
            'susp' => 'SUSPENSION',
            'susp.' => 'SUSPENSION',
            'suspn' => 'SUSPENSION',
            'suspn.' => 'SUSPENSION',
            
            // SOBRE
            'sobre' => 'SOBRE',
            'sobres' => 'SOBRE',
            'sbr' => 'SOBRE',
            'sbr.' => 'SOBRE',
            'sobr' => 'SOBRE',
            'sobr.' => 'SOBRE',
            'oral sobre' => 'SOBRE',
            
            // CAPSULA
            'capsula' => 'CAPSULA',
            'cápsula' => 'CAPSULA',
            'capsulas' => 'CAPSULA',
            'cápsulas' => 'CAPSULA',
            'cap' => 'CAPSULA',
            'cap.' => 'CAPSULA',
            'caps' => 'CAPSULA',
            'caps.' => 'CAPSULA',
            
            // GALON
            'galon' => 'GALON',
            'galón' => 'GALON',
            'galones' => 'GALON',
            'gal' => 'GALON',
            'gal.' => 'GALON',
            'gl' => 'GALON',
            'gl.' => 'GALON',
            
            // LITRO
            'litro' => 'LITRO',
            'litros' => 'LITRO',
            'l' => 'LITRO',
            'l.' => 'LITRO',
            'lt' => 'LITRO',
            'lt.' => 'LITRO',
            'ltr' => 'LITRO',
            'ltr.' => 'LITRO',
            
            // OTRAS PRESENTACIONES
            'oral' => 'ORAL',
            'pediatrico' => 'PEDIATRICO',
            'pediátrico' => 'PEDIATRICO',
            'ped' => 'PEDIATRICO',
            'ped.' => 'PEDIATRICO',
            'adulto' => 'ADULTO',
            'adultos' => 'ADULTO',
            'ad' => 'ADULTO',
            'ad.' => 'ADULTO',
            'adult' => 'ADULTO',
            'adult.' => 'ADULTO',
            'yardas' => 'YARDAS',
            'yrd' => 'YARDAS',
            'yrd.' => 'YARDAS',
            'yarda' => 'YARDAS',
            'yard' => 'YARDAS',
            'yard.' => 'YARDAS'
        ];
        
        // Términos que siempre se excluyen de presentación
        $excludedTerms = ['oral', 'pediatrico', 'pediátrico', 'adulto', 'adultos', 'yardas'];
        
        // Obtener términos únicos de presentación del mapa
        $includedPresentations = array_values(array_unique($presentationMap));
        
        // Ordenar por longitud descendente para priorizar coincidencias más largas
        usort($includedPresentations, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        // Procesar la presentación
        $tokens = preg_split('/\s+/', trim($descripcion));
        $candidate = $tokens[count($tokens)-1] ?? null;
        $presentacion = null;
        
        // Si es un termómetro oral, forzar tipo médico
        if (Str::contains(Str::lower($descripcion), 'termómetro oral') || 
            Str::contains(Str::lower($descripcion), 'termometro oral')) {
            $tipo = 'medico_quirurgico';
        } else {
            // Convertir a minúsculas para comparación
            $candidateLower = Str::lower($candidate);
            $descripcionLower = Str::lower($descripcion);
            
            // Verificar si el candidato contiene números
            $hasNumber = $candidate && preg_match('/\d+/', $candidate);
            
            // Buscar coincidencias en la descripción completa
            foreach ($presentationMap as $abbr => $full) {
                // Si la abreviatura está en la descripción y no es un término excluido
                if (preg_match('/\b' . preg_quote($abbr, '/') . '\b/i', $descripcionLower) && 
                    !in_array($abbr, $excludedTerms) && 
                    !in_array(str_replace('.', '', $abbr), $excludedTerms)) {
                    $presentacion = $full;
                    break;
                }
            }
            
            // Si no se encontró ninguna presentación pero el candidato no tiene números, verificar si es una presentación válida
            if (!$presentacion && !$hasNumber) {
                $candidateNormalized = Str::upper($candidate);
                if (in_array($candidateNormalized, $includedPresentations)) {
                    $presentacion = $candidateNormalized;
                }
            }
            
            // Si se encontró una presentación, establecer el tipo
            if ($presentacion) {
                $tipo = 'farmaceutico';
            } else {
                $tipo = 'medico_quirurgico';
            }
        }

        // Obtener el nombre base (sin la presentación si existe)
        $nombreBase = $descripcion;
        
        // Si encontramos una presentación, la quitamos del nombre base
        if ($presentacion) {
            // Buscar todas las variantes de la presentación
            $variantes = array_keys(array_filter($presentationMap, function($value) use ($presentacion) {
                return $value === $presentacion;
            }));
            
            // Eliminar cada variante del nombre base
            foreach ($variantes as $variante) {
                $nombreBase = preg_replace('/\s*\b' . preg_quote($variante, '/') . '\b\s*/i', ' ', $nombreBase);
            }
            
            // Limpiar espacios extras
            $nombreBase = trim(preg_replace('/\s+/', ' ', $nombreBase));
        }
        
        if ($nombreBase === '') { 
            $nombreBase = $descripcion; 
        }
        
        // Limpiar el nombre de palabras clave
        $allKeys = array_merge(
            $farmaceuticoKeys, 
            $medicoKeys, 
            array_keys($presentationMap),
            $excludedTerms,
            array_map('strtolower', $includedPresentations)
        );
        
        // Eliminar duplicados
        $allKeys = array_unique($allKeys);
        
        // Ordenar por longitud descendente para coincidir primero las palabras más largas
        usort($allKeys, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        // Eliminar palabras clave del nombre
        foreach ($allKeys as $k) {
            $nombreBase = preg_replace('/\b' . preg_quote($k, '/') . '\b/i', '', $nombreBase);
        }
        $nombre = trim(preg_replace('/\s+/', ' ', $nombreBase));

        // Determinar unidad y cantidad
        $cantidad = 1; 
        $unidad = 'unidad';
        
        // Si hay presentación, intentar extraer cantidad y unidad
        if ($presentacion) {
            // Extraer números para cantidad
            if (preg_match('/(\d+)/', $presentacion, $m)) { 
                $cantidad = (int) ($m[1] ?? 1); 
            }
            
            // Mapeo de unidades
            $unitMap = [
                'tableta' => 'unidades', 'tabletas' => 'unidades', 
                'ampolla' => 'unidades', 'ampollas' => 'unidades', 
                'capsula' => 'unidades', 'cápsula' => 'unidades', 'cápsulas' => 'unidades',
                'caja' => 'caja', 'blister' => 'blister', 'sobre' => 'sobre',
                'frasco' => 'frasco', 'bolsa' => 'bolsa', 'tira' => 'tira', 'tiras' => 'tiras',
                'amp' => 'unidades', 'tab' => 'unidades', 'jbe' => 'frasco', 'fco' => 'frasco',
                'litro' => 'litros', 'galon' => 'galones', 'galón' => 'galones',
                'susp' => 'frasco', 'suspension' => 'frasco', 'suspensión' => 'frasco'
            ];
            
            // Buscar unidad en la presentación
            foreach ($unitMap as $key => $unit) {
                if (preg_match('/\b' . preg_quote($key, '/') . '\b/i', $presentacion)) {
                    $unidad = $unit;
                    break;
                }
            }
        }

        // Código estable por nombre + presentacion
        $base = Str::slug($nombre . '-' . ($presentacion ?? ''));
        $codigo = strtoupper(substr($base, 0, 3)) . '-' . substr(md5($base), 0, 6);

        return [
            'codigo' => $codigo ?: 'AUTO-' . substr(md5($descripcion), 0, 8),
            'nombre' => $nombre ?: $descripcion,
            'tipo' => $tipo,
            'unidad_medida' => $unidad,
            'cantidad_por_paquete' => $cantidad,
            'presentacion' => $presentacion,
        ];
    }
}
