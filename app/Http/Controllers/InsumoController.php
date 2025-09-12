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
}
