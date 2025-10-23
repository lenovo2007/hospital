<?php

/**
 * Script para corregir tipos de hospitales mal registrados
 * Ejecutar desde la raíz del proyecto: php fix_hospital_tipos.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Hospital;

echo "=== Corrección de Tipos de Hospitales ===\n\n";

// Obtener todos los hospitales
$hospitales = Hospital::all();
$corregidos = 0;
$sinCambios = 0;

foreach ($hospitales as $hospital) {
    $tipoOriginal = $hospital->tipo;
    
    if (empty($tipoOriginal)) {
        continue;
    }
    
    // Normalizar: reemplazar todas las L y l por I
    $tipoNormalizado = str_replace(['L', 'l'], 'I', trim($tipoOriginal));
    $tipoNormalizado = strtoupper($tipoNormalizado);
    
    // Contar cuántas I hay para determinar el tipo
    $cantidadI = substr_count($tipoNormalizado, 'I');
    
    $tipoCorregido = null;
    
    if ($cantidadI === 1) {
        $tipoCorregido = 'hospital_tipo1';
    } elseif ($cantidadI === 2) {
        $tipoCorregido = 'hospital_tipo2';
    } elseif ($cantidadI === 3) {
        $tipoCorregido = 'hospital_tipo3';
    } elseif ($cantidadI === 4 || $tipoNormalizado === 'IV') {
        $tipoCorregido = 'hospital_tipo4';
    }
    
    // Si se encontró una corrección y es diferente al original
    if ($tipoCorregido && $tipoCorregido !== $tipoOriginal) {
        echo "ID {$hospital->id} - {$hospital->nombre}\n";
        echo "  Antes: '{$tipoOriginal}' → Después: '{$tipoCorregido}'\n";
        
        $hospital->tipo = $tipoCorregido;
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
