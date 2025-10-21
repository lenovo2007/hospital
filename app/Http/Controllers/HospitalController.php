<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HospitalController extends Controller
{
    // GET /api/hospitales
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        // Mapear 'todos' a 'all' y validar
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) {
            $status = 'activo';
        }

        $query = Hospital::query();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $items = $query->latest()->get();
        $mensaje = $items->count() > 0 ? 'Listado de hospitales.' : 'hospitales no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/hospitales/buscar_por_rif?rif=J-12345678-9
    public function buscarPorRif(Request $request)
    {
        $data = $request->validate([
            'rif' => ['required','string','max:255'],
        ]);

        $rif = $data['rif'];

        $hospital = Hospital::where('rif', $rif)->first();

        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Hospital no encontrado por ese RIF.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital encontrado.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/hospitales/actualizar_por_rif?rif=J-12345678-9
    public function actualizarPorRif(Request $request)
    {
        $validated = $request->validate([
            'rif' => ['required','string','max:255'],
        ]);

        $hospital = Hospital::where('rif', $validated['rif'])->first();

        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Hospital no encontrado por ese RIF.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'nombre_completo' => ['nullable','string','max:255'],
            'email' => ['nullable','email','max:255', Rule::unique('hospitales','email')->ignore($hospital->id)],
            'email_contacto' => ['nullable','email','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'nombre_contacto' => ['nullable','string','max:255'],
            'cod_sicm' => ['nullable','string','max:100', Rule::unique('hospitales','cod_sicm')->ignore($hospital->id)],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'dependencia' => ['nullable','string','max:255'],
            'estado' => ['nullable','string','max:255'],
            'municipio' => ['nullable','string','max:255'],
            'parroquia' => ['nullable','string','max:255'],
            'tipo' => ['sometimes','required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'email.unique' => 'El email ya está registrado para otro hospital.',
            'cod_sicm.unique' => 'El cod_sicm ya está registrado para otro hospital.',
        ]);

        // Evitar que cambien el RIF desde este endpoint
        unset($data['rif']);

        $hospital->update($data);
        $hospital->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital actualizado por RIF.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/hospitales/cod_sicm/{cod_sicm}
    public function showByCodSicm(Request $request, string $cod_sicm)
    {
        $hospital = Hospital::where('cod_sicm', $cod_sicm)->first();
        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Hospital no encontrado por ese COD_SICM.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de hospital por COD_SICM.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/hospitales/cod_sicm/{cod_sicm}
    public function updateByCodSicm(Request $request, string $cod_sicm)
    {
        $hospital = Hospital::where('cod_sicm', $cod_sicm)->first();
        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Hospital no encontrado por ese COD_SICM.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'nombre_completo' => ['nullable','string','max:255'],
            'email' => ['nullable','email','max:255', Rule::unique('hospitales','email')->ignore($hospital->id)],
            'email_contacto' => ['nullable','email','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'nombre_contacto' => ['nullable','string','max:255'],
            'cod_sicm' => ['nullable','string','max:100', Rule::unique('hospitales','cod_sicm')->ignore($hospital->id)],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'dependencia' => ['nullable','string','max:255'],
            'estado' => ['nullable','string','max:255'],
            'municipio' => ['nullable','string','max:255'],
            'parroquia' => ['nullable','string','max:255'],
            'tipo' => ['sometimes','required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'email.unique' => 'El email ya está registrado para otro hospital.',
            'cod_sicm.unique' => 'El cod_sicm ya está registrado para otro hospital.',
        ]);

        $hospital->update($data);
        $hospital->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital actualizado por COD_SICM.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/hospitales
    public function store(Request $request)
    {
        \Log::info('HospitalController@store: request received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'user_id' => optional($request->user())->id,
        ]);
        $data = $request->validate([
            'nombre' => ['required','string','max:255'],
            'rif' => ['nullable','string','max:255'],
            'email' => ['nullable','email','max:255','unique:hospitales,email'],
            'email_contacto' => ['nullable','email','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'nombre_contacto' => ['nullable','string','max:255'],
            'cod_sicm' => ['nullable','string','max:100','unique:hospitales,cod_sicm'],
            'nombre_completo' => ['nullable','string','max:255'],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'dependencia' => ['nullable','string','max:255'],
            'estado' => ['nullable','string','max:255'],
            'municipio' => ['nullable','string','max:255'],
            'parroquia' => ['nullable','string','max:255'],
            'tipo' => ['required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'email.unique' => 'El email ya está registrado para otro hospital.',
            'cod_sicm.unique' => 'El cod_sicm ya está registrado para otro hospital.',
        ]);

        \Log::info('HospitalController@store: data validated', $data);
        if (!isset($data['status'])) { $data['status'] = 'activo'; }
        $item = Hospital::create($data);

        \Log::info('HospitalController@store: hospital created', ['id' => $item->id]);
        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/hospitales/{id}
    public function show($id)
    {
        $hospital = Hospital::find($id);
        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'hospitales no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de hospital.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/hospitales/{hospital}
    public function update(Request $request, Hospital $hospital)
    {
        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            // rif no se actualiza aquí para mantener unicidad consistente por otro endpoint
            'nombre_completo' => ['nullable','string','max:255'],
            'email' => ['nullable','email','max:255', Rule::unique('hospitales','email')->ignore($hospital->id)],
            'email_contacto' => ['nullable','email','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'nombre_contacto' => ['nullable','string','max:255'],
            'cod_sicm' => ['nullable','string','max:100', Rule::unique('hospitales','cod_sicm')->ignore($hospital->id)],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'tipo' => ['sometimes','required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'email.unique' => 'El email ya está registrado para otro hospital.',
        ]);

        $hospital->update($data);
        $hospital->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital actualizado.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/hospitals/{hospital}
    public function destroy(Hospital $hospital)
    {
        $hospital->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/hospitales/import
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:xls,xlsx','max:10240']
        ]);

        // Verificar extensiones PHP requeridas
        $missingExt = [];
        if (!extension_loaded('zip')) { $missingExt[] = 'zip'; }
        if (!extension_loaded('xml')) { $missingExt[] = 'xml'; }
        if (!extension_loaded('mbstring')) { $missingExt[] = 'mbstring'; }
        if ($missingExt) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Extensiones PHP requeridas no disponibles: ' . implode(', ', $missingExt) . '. Habilítalas en php.ini.',
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
            @ini_set('memory_limit', '512M');
            $file = $request->file('file');
            $path = $file->getRealPath();

            // Detectar automáticamente el tipo de archivo (xls o xlsx)
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $created = 0; $updated = 0; $skipped = []; $errors = [];
            $rowCount = (int) $sheet->getHighestRow();

            // Columnas del Excel (desde fila 2):
            // A=rif, B=cod_sicm, C=nombre, D=tipo, E=dependencia, F=estado, G=municipio, 
            // H=parroquia, I=email, J=nombre_contacto, K=email_contacto, L=telefono, 
            // M=direccion, N=lat, O=lng

            for ($i = 2; $i <= $rowCount; $i++) {
                try {
                    $rif = trim((string)$sheet->getCell('A' . $i)->getValue());
                    $cod_sicm = trim((string)$sheet->getCell('B' . $i)->getValue());
                    $nombre = trim((string)$sheet->getCell('C' . $i)->getValue());
                    $tipo = trim((string)$sheet->getCell('D' . $i)->getValue());
                    $dependencia = trim((string)$sheet->getCell('E' . $i)->getValue());
                    $estado = trim((string)$sheet->getCell('F' . $i)->getValue());
                    $municipio = trim((string)$sheet->getCell('G' . $i)->getValue());
                    $parroquia = trim((string)$sheet->getCell('H' . $i)->getValue());
                    $email = trim((string)$sheet->getCell('I' . $i)->getValue());
                    $nombre_contacto = trim((string)$sheet->getCell('J' . $i)->getValue());
                    $email_contacto = trim((string)$sheet->getCell('K' . $i)->getValue());
                    $telefono = trim((string)$sheet->getCell('L' . $i)->getValue());
                    $direccion = trim((string)$sheet->getCell('M' . $i)->getValue());
                    $lat = trim((string)$sheet->getCell('N' . $i)->getValue());
                    $lng = trim((string)$sheet->getCell('O' . $i)->getValue());

                    // Validar que al menos tenga nombre
                    if (empty($nombre)) {
                        $skipped[] = ['fila' => $i, 'motivo' => 'Nombre vacío'];
                        continue;
                    }

                    // Limpiar y validar RIF (convertir '?' a NULL)
                    if ($rif === '?' || empty($rif)) {
                        $rif = null;
                    }

                    // Limpiar y validar cod_sicm
                    if ($cod_sicm === '?' || empty($cod_sicm)) {
                        $cod_sicm = null;
                    }

                    // Limpiar y validar emails (evitar duplicados con valores genéricos)
                    if (empty($email) || $email === '?' || strtoupper($email) === 'NO TIENE') {
                        $email = null;
                    }
                    if (empty($email_contacto) || $email_contacto === '?') {
                        $email_contacto = null;
                    }

                    // Truncar teléfono a 50 caracteres (límite de la BD)
                    if (!empty($telefono) && $telefono !== '?') {
                        $telefono = substr($telefono, 0, 50);
                    } else {
                        $telefono = null;
                    }

                    // Limpiar y validar coordenadas GPS
                    $ubicacion = null;
                    if (!empty($lat) && !empty($lng) && $lat !== '?' && $lng !== '?') {
                        // Extraer solo números y puntos de las coordenadas
                        $latClean = preg_replace('/[^0-9.\-]/', '', $lat);
                        $lngClean = preg_replace('/[^0-9.\-]/', '', $lng);
                        
                        // Validar que sean números válidos
                        if (is_numeric($latClean) && is_numeric($lngClean)) {
                            $latFloat = (float)$latClean;
                            $lngFloat = (float)$lngClean;
                            
                            // Validar rangos válidos
                            if ($latFloat >= -90 && $latFloat <= 90 && $lngFloat >= -180 && $lngFloat <= 180) {
                                $ubicacion = ['lat' => (string)$latFloat, 'lng' => (string)$lngFloat];
                            }
                        }
                    }

                    // Preparar payload
                    $payload = [
                        'nombre' => $nombre,
                        'rif' => $rif,
                        'cod_sicm' => $cod_sicm,
                        'tipo' => !empty($tipo) ? $tipo : 'No especificado',
                        'dependencia' => (!empty($dependencia) && $dependencia !== '?') ? $dependencia : null,
                        'estado' => (!empty($estado) && $estado !== '?') ? $estado : null,
                        'municipio' => (!empty($municipio) && $municipio !== '?') ? $municipio : null,
                        'parroquia' => (!empty($parroquia) && $parroquia !== '?') ? $parroquia : null,
                        'email' => $email,
                        'nombre_contacto' => (!empty($nombre_contacto) && $nombre_contacto !== '?') ? $nombre_contacto : null,
                        'email_contacto' => $email_contacto,
                        'telefono' => $telefono,
                        'direccion' => (!empty($direccion) && $direccion !== '?') ? $direccion : null,
                        'ubicacion' => $ubicacion,
                        'status' => 'activo',
                    ];

                    // Buscar hospital existente por cod_sicm o por nombre+estado+municipio
                    $existing = null;
                    if (!empty($cod_sicm)) {
                        $existing = Hospital::where('cod_sicm', $cod_sicm)->first();
                    }
                    if (!$existing) {
                        $existing = Hospital::where('nombre', $nombre)
                            ->where('estado', $estado)
                            ->where('municipio', $municipio)
                            ->first();
                    }

                    if ($existing) {
                        // Actualizar - manejar conflictos de email
                        try {
                            // Si el email ya existe en otro hospital, no actualizar ese campo
                            if ($payload['email'] && Hospital::where('email', $payload['email'])
                                ->where('id', '!=', $existing->id)
                                ->exists()) {
                                $payload['email'] = $existing->email; // Mantener el email original
                            }
                            
                            $existing->update($payload);
                            $updated++;
                        } catch (\Exception $e) {
                            // Si falla la actualización, registrar error pero continuar
                            $errors[] = ['fila' => $i, 'error' => 'Error al actualizar: ' . $e->getMessage()];
                        }
                    } else {
                        // Crear nuevo - manejar conflictos de email
                        try {
                            // Si el email ya existe, generar uno único o dejarlo NULL
                            if ($payload['email'] && Hospital::where('email', $payload['email'])->exists()) {
                                $payload['email'] = null; // Dejar NULL si hay conflicto
                            }
                            
                            Hospital::create($payload);
                            $created++;
                        } catch (\Exception $e) {
                            // Si falla la creación, registrar error pero continuar
                            $errors[] = ['fila' => $i, 'error' => 'Error al crear: ' . $e->getMessage()];
                        }
                    }

                } catch (\Exception $e) {
                    $errors[] = ['fila' => $i, 'error' => $e->getMessage()];
                }
            }

            return response()->json([
                'status' => true,
                'mensaje' => 'Importación completada.',
                'data' => [
                    'creados' => $created,
                    'actualizados' => $updated,
                    'omitidos' => count($skipped),
                    'errores' => count($errors),
                    'detalles_omitidos' => $skipped,
                    'detalles_errores' => $errors,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al procesar el archivo Excel: ' . $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }
}
