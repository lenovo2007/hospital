<?php

/**
 * Script para corregir campos con acentos y tildes en hospitales existentes
 * Ejecutar desde la raíz del proyecto: php fix_hospital_accents.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Hospital;

echo "=== Corrección de Acentos y Tildes en Hospitales ===\n\n";

// Obtener todos los hospitales
$hospitales = Hospital::all();
$corregidos = 0;
$sinCambios = 0;

foreach ($hospitales as $hospital) {
    $cambios = false;
    $originalData = $hospital->toArray();

    // Campos a corregir
    $campos = ['dependencia', 'estado', 'municipio', 'parroquia'];

    foreach ($campos as $campo) {
        if (!empty($hospital->$campo)) {
            $valorOriginal = $hospital->$campo;
            $valorCorregido = mb_strtolower($valorOriginal);

            if ($valorOriginal !== $valorCorregido) {
                echo "ID {$hospital->id} - {$hospital->nombre}\n";
                echo "  {$campo}: '{$valorOriginal}' → '{$valorCorregido}'\n";
                $hospital->$campo = $valorCorregido;
                $cambios = true;
            }
        }
    }

    if ($cambios) {
        $hospital->save();
        $corregidos++;
    } else {
        $sinCambios++;
    }
}

echo "\n=== Resumen ===\n";
echo "Total hospitales: " . $hospitales->count() . "\n";
echo "Corregidos: {$corregidos}\n";
echo "Sin cambios: {$sinCambios}\n";
echo "\n✅ Proceso completado.\n";
echo "\nEjemplos de correcciones aplicadas:\n";
echo "- 'girÓn' → 'girón'\n";
echo "- 'alcalÁ' → 'alcalá'\n";
echo "- 'josÉ' → 'josé'\n";
echo "- 'MIRANDA' → 'miranda'\n";
echo "- 'CARACAS' → 'caracas'\n";
