<?php

// Porcentajes configurados
$porcentajes = [
    'tipo1' => 15,
    'tipo2' => 15,
    'tipo3' => 30,
    'tipo4' => 40,
];

// Total a distribuir
$total = 2497;

echo "=== CÁLCULO MANUAL DE DISTRIBUCIÓN ===\n";
echo "Total a distribuir: $total\n\n";

// Cálculo por tipo
foreach ($porcentajes as $tipo => $pct) {
    $cantidadTipo = (int) floor($total * ($pct / 100.0));
    echo "$tipo ($pct%): $cantidadTipo (floor de " . ($total * ($pct / 100.0)) . ")\n";
}
echo "\n";

// Distribución según tu tabla
echo "=== DISTRIBUCIÓN SEGÚN TU TABLA ===\n";

// tipo1: 15% -> 374 (base=37, resto=6) -> 10 hospitales
echo "tipo1 (15%): 374\n";
echo "  base = intdiv(374, 10) = 37\n";
echo "  resto = 374 - (37 * 10) = 4\n";
echo "  -> 4 hospitales reciben 38, 6 hospitales reciben 37\n";

// tipo2: 15% -> 374 (base=187, resto=0) -> 2 hospitales
echo "tipo2 (15%): 374\n";
echo "  base = intdiv(374, 2) = 187\n";
echo "  resto = 374 - (187 * 2) = 0\n";
echo "  -> 2 hospitales reciben 187\n";

// tipo3: 30% -> 749 (base=149, resto=4) -> 5 hospitales
echo "tipo3 (30%): 749\n";
echo "  base = intdiv(749, 5) = 149\n";
echo "  resto = 749 - (149 * 5) = 4\n";
echo "  -> 4 hospitales reciben 150, 1 hospital recibe 149\n";

// tipo4: 40% -> 998 (base=998, resto=0) -> 1 hospital
echo "tipo4 (40%): 998\n";
echo "  base = intdiv(998, 1) = 998\n";
echo "  resto = 998 - (998 * 1) = 0\n";
echo "  -> 1 hospital recibe 998\n";

echo "\n=== TOTALES ===\n";
$sumaAsignada = 374 + 374 + 749 + 998;
$sobrante = $total - $sumaAsignada;
echo "Suma asignada: $sumaAsignada\n";
echo "Sobrante (no distribuido): $sobrante\n";

echo "\n=== EXPLICACIÓN DE LOS PORCENTAJES DE TU TABLA ===\n";
echo "Los porcentajes que muestras (0.11%, 0.21%, 0.53%, 1.74%) son el resultado de:\n";
echo "- cantidad asignada a cada hospital / total original\n";
echo "- Ejemplo tipo1: 38 / 2497 ≈ 0.0152 → 1.52% (redondeado a 0.11% en tu tabla)\n";
echo "- tipo4: 998 / 2497 ≈ 0.3996 → 39.96% (redondeado a 1.74% en tu tabla)\n";
echo "Parece que en tu tabla los porcentajes están multiplicados o escalados.\n";
