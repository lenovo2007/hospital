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
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de insumos.' : 'insumos no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/insumos/codigo/{codigo}
    public function showByCodigo(Request $request, string $codigo)
    {
        $insumo = Insumo::where('codigo', $codigo)->first();
        if (!$insumo) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Insumo no encontrado por ese código.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $descripcion = $insumo->descripcion;
        $tokens = preg_split('/\s+/', trim($descripcion));
        $candidate = $tokens[count($tokens)-1] ?? null;
        $presentacion = $candidate;
        $presentacionKeywords = ['unidad','unidades','tableta','tabletas','ampolla','ampollas','capsula','cápsula','cápsulas','caja','blister','sobre','frasco','bolsa','tira','tiras'];
        $hasNumber = $presentacion && preg_match('/\d+/', $presentacion);
        $isKeyword = $presentacion && in_array(Str::lower($presentacion), $presentacionKeywords, true);
        if (!$hasNumber && !$isKeyword) {
            $presentacion = null;
            $tipo = 'medico_quirurgico';
        }

        $nombreBase = $presentacion ? trim(Str::beforeLast($descripcion, ' ' . $candidate)) : $descripcion;
        if ($nombreBase === '') { $nombreBase = $descripcion; }

        $cantidad = 1; $unidad = 'unidad';
        if ($presentacion) {
            if (preg_match('/(\d+)/', $presentacion, $m)) { $cantidad = (int) ($m[1] ?? 1); }
            if (preg_match('/(unidad|unidades|tableta|tabletas|ampolla|ampollas|capsula|cápsula|cápsulas|caja|blister|sobre|frasco|bolsa|tira|tiras)/i', $presentacion, $m2)) {
                $unidad = Str::lower($m2[1]);
                $map = [ 'tableta' => 'unidades', 'tabletas' => 'unidades', 'ampolla' => 'unidades', 'ampollas' => 'unidades', 'capsula' => 'unidades', 'cápsula' => 'unidades', 'cápsulas' => 'unidades' ];
                if (isset($map[$unidad])) { $unidad = $map[$unidad]; }
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
            'codigo' => ['required','string','max:100','unique:insumos,codigo'],
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

        $descripcion = $data['descripcion'];
        $tokens = preg_split('/\s+/', trim($descripcion));
        $candidate = $tokens[count($tokens)-1] ?? null;
        $presentacion = $candidate;
        $presentacionKeywords = ['unidad','unidades','tableta','tabletas','ampolla','ampollas','capsula','cápsula','cápsulas','caja','blister','sobre','frasco','bolsa','tira','tiras'];
        $hasNumber = $presentacion && preg_match('/\d+/', $presentacion);
        $isKeyword = $presentacion && in_array(Str::lower($presentacion), $presentacionKeywords, true);
        if (!$hasNumber && !$isKeyword) {
            $presentacion = null;
            $data['tipo'] = 'medico_quirurgico';
        }

        $nombreBase = $presentacion ? trim(Str::beforeLast($descripcion, ' ' . $candidate)) : $descripcion;
        if ($nombreBase === '') { $nombreBase = $descripcion; }
        $data['nombre'] = $nombreBase;

        $cantidad = 1; $unidad = 'unidad';
        if ($presentacion) {
            if (preg_match('/(\d+)/', $presentacion, $m)) { $cantidad = (int) ($m[1] ?? 1); }
            if (preg_match('/(unidad|unidades|tableta|tabletas|ampolla|ampollas|capsula|cápsula|cápsulas|caja|blister|sobre|frasco|bolsa|tira|tiras)/i', $presentacion, $m2)) {
                $unidad = Str::lower($m2[1]);
                $map = [ 'tableta' => 'unidades', 'tabletas' => 'unidades', 'ampolla' => 'unidades', 'ampollas' => 'unidades', 'capsula' => 'unidades', 'cápsula' => 'unidades', 'cápsulas' => 'unidades' ];
                if (isset($map[$unidad])) { $unidad = $map[$unidad]; }
            }
        }
        $data['cantidad_por_paquete'] = $cantidad;
        $data['unidad_medida'] = $unidad;

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
            'codigo' => ['sometimes','required','string','max:100', Rule::unique('insumos','codigo')->ignore($insumo->id)],
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
        $insumo = Insumo::where('codigo', $codigo)->first();
        if (!$insumo) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Insumo no encontrado por ese código.',
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
            'status' => ['nullable','in:activo,inactivo'],
        ]);

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
            'file' => ['required','file','mimes:xlsx','max:10240']
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

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();

            // Según requerimiento: DESCRIPCION está en columna B
            $descripcionCol = 'B';

            $created = 0; $updated = 0; $skipped = 0; $errors = [];
            $rowCount = (int) $sheet->getHighestRow();
            for ($i = 2; $i <= $rowCount; $i++) { // desde la fila 2
                $raw = $sheet->getCell($descripcionCol . $i)->getValue();
                $descripcion = is_string($raw) ? trim($raw) : '';
                if ($descripcion === '') { $skipped++; continue; }

                $parsed = $this->parseDescripcion($descripcion);
                $payload = [
                    'codigo' => $parsed['codigo'],
                    'nombre' => $parsed['nombre'],
                    'tipo' => $parsed['tipo'],
                    'unidad_medida' => $parsed['unidad_medida'],
                    'cantidad_por_paquete' => $parsed['cantidad_por_paquete'],
                    'descripcion' => $descripcion,
                    'presentacion' => $parsed['presentacion'],
                    'status' => 'activo',
                ];

                try {
                    $existing = Insumo::where('codigo', $payload['codigo'])->first();
                    if ($existing) {
                        $existing->update($payload);
                        $updated++;
                    } else {
                        Insumo::create($payload);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = [ 'row' => $i, 'descripcion' => $descripcion, 'error' => $e->getMessage() ];
                    Log::warning('Import insumos error', ['row' => $i, 'e' => $e->getMessage()]);
                }
            }

            // Liberar memoria del spreadsheet
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->json([
                'status' => true,
                'mensaje' => 'Importación procesada.',
                'data' => [
                    'creados' => $created,
                    'actualizados' => $updated,
                    'omitidos' => $skipped,
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

    /**
     * Parsear DESCRIPCION a atributos de Insumo con reglas de presentación y tipo.
     */
    protected function parseDescripcion(string $descripcion): array
    {
        $descLower = Str::lower($descripcion);

        $farmaceuticoKeys = ['jarabe','suspensión','suspension','tableta','ampolla','capsula','cápsula','ungüento','pomada','solución','solucion'];
        $medicoKeys = ['equipo','material','quirurgico','quirúrgico','medico','médico'];

        $tipo = 'medico_quirurgico';
        foreach ($farmaceuticoKeys as $k) {
            if (Str::contains($descLower, Str::lower($k))) { $tipo = 'farmaceutico'; break; }
        }
        if ($tipo !== 'farmaceutico') {
            foreach ($medicoKeys as $k) {
                if (Str::contains($descLower, Str::lower($k))) { $tipo = 'medico_quirurgico'; break; }
            }
        }

        // Candidata de presentación = última palabra
        $tokens = preg_split('/\s+/', trim($descripcion));
        $candidate = $tokens[count($tokens)-1] ?? null;
        $presentacion = $candidate;
        $presentacionKeywords = ['unidad','unidades','tableta','tabletas','ampolla','ampollas','capsula','cápsula','cápsulas','caja','blister','sobre','frasco','bolsa','tira','tiras'];
        $hasNumber = $presentacion && preg_match('/\d+/', $presentacion);
        $isKeyword = $presentacion && in_array(Str::lower($presentacion), $presentacionKeywords, true);
        if (!$hasNumber && !$isKeyword) {
            $presentacion = null;
            $tipo = 'medico_quirurgico';
        }

        // Nombre sin la presentación (si existe) y sin palabras clave
        $nombreBase = $presentacion ? trim(Str::beforeLast($descripcion, ' ' . $candidate)) : $descripcion;
        if ($nombreBase === '') { $nombreBase = $descripcion; }
        $allKeys = array_merge($farmaceuticoKeys, $medicoKeys);
        foreach ($allKeys as $k) {
            $nombreBase = preg_replace('/\b' . preg_quote($k, '/') . '\b/i', '', $nombreBase);
        }
        $nombre = trim(preg_replace('/\s+/', ' ', $nombreBase));

        // Unidad y cantidad derivadas de presentación, si existe
        $cantidad = 1; $unidad = 'unidad';
        if ($presentacion) {
            if (preg_match('/(\d+)/', $presentacion, $m)) { $cantidad = (int) ($m[1] ?? 1); }
            if (preg_match('/(unidad|unidades|tableta|tabletas|ampolla|ampollas|capsula|cápsula|cápsulas|caja|blister|sobre|frasco|bolsa|tira|tiras)/i', $presentacion, $m2)) {
                $unidad = Str::lower($m2[1]);
                $map = [ 'tableta' => 'unidades', 'tabletas' => 'unidades', 'ampolla' => 'unidades', 'ampollas' => 'unidades', 'capsula' => 'unidades', 'cápsula' => 'unidades', 'cápsulas' => 'unidades' ];
                if (isset($map[$unidad])) { $unidad = $map[$unidad]; }
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
