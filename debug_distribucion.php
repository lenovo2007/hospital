<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\TipoHospitalDistribucion;
use App\Models\Hospital;

// ---- CONFIGURACIÓN ----
$cfg = TipoHospitalDistribucion::first();
$porcentajes = [
    'tipo1' => (float) $cfg->tipo1,
    'tipo2' => (float) $cfg->tipo2,
    'tipo3' => (float) $cfg->tipo3,
    'tipo4' => (float) $cfg->tipo4,
];

echo "=== PORCENTAJES CONFIGURADOS ===\n";
foreach ($porcentajes as $k => $v) {
    echo "$k: $v%\n";
}
echo "\n";

// ---- HOSPITALES ELEGIBLES (estado Lara) ----
$hospitales = Hospital::where('status', true)->where('estado', 'Lara')->get(['id','nombre','tipo']);
$porTipo = [];
foreach ($hospitales as $h) {
    $t = $h->tipo ?? 'null';
    $porTipo[$t] = ($porTipo[$t] ?? 0) + 1;
}
echo "=== HOSPITALES POR TIPO ===\n";
foreach ($porTipo as $tipo => $cnt) {
    echo "$tipo: $cnt hospitales\n";
}
echo "\n";

// ---- FUNCIÓN DE MAPEO ----
function mapearTipoHospitalAClavePorcentaje(string $tipo): string
{
    $t = strtolower(trim($tipo));
    $t = str_replace([' ', '-', '__'], ['', '', ''], $t);
    $t = str_replace('_', '', $t);
    if (in_array($t, ['1', '2', '3', '4'], true)) {
        return 'tipo' . $t;
    }
    if (preg_match('/^tipo([1-4])$/', $t, $m)) {
        return 'tipo' . $m[1];
    }
    if (preg_match('/^hospitaltipo([1-4])$/', $t, $m)) {
        return 'tipo' . $m[1];
    }
    return $t;
}

// ---- SIMULACIÓN DE DISTRIBUCIÓN ----
$cantidadTotal = 2497; // ejemplo: total de insumos a distribuir
$asignacion = [];
$sumaAsignada = 0;

foreach ($porTipo as $tipoRaw => $cnt) {
    if ($cnt <= 0) continue;
    $clave = mapearTipoHospitalAClavePorcentaje($tipoRaw);
    if (!array_key_exists($clave, $porcentajes)) {
        echo "Tipo no mapeado: $tipoRaw -> $clave\n";
        continue;
    }
    $porc = $porcentajes[$clave];
    if ($porc <= 0) continue;

    $cantidadTipo = (int) floor($cantidadTotal * ($porc / 100.0));
    echo "Tipo $clave ($tipoRaw): $porc% de $cantidadTotal = $cantidadTipo (floor)\n";

    $base = intdiv($cantidadTipo, $cnt);
    $resto = $cantidadTipo - ($base * $cnt);
    echo "  -> $cnt hospitales: base=$base, resto=$resto\n";

    $idx = 0;
    foreach ($hospitales->where('tipo', $tipoRaw) as $h) {
        $hid = $h->id;
        $asig = $base + ($idx < $resto ? 1 : 0);
        $asignacion[$hid] = ($asignacion[$hid] ?? 0) + $asig;
        $sumaAsignada += $asig;
        echo "  -> Hospital {$h->id} ({$h->nombre}): $asig\n";
        $idx++;
    }
}

echo "\n=== RESUMEN ===\n";
echo "Cantidad original: $cantidadTotal\n";
echo "Suma asignada: $sumaAsignada\n";
echo "Diferencia (no distribuido): " . ($cantidadTotal - $sumaAsignada) . "\n";
echo "\n";
