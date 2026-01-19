<?php
echo "PHP está funcionando<br>";
echo "Versión PHP: " . phpversion() . "<br>";
echo "Directorio actual: " . __DIR__ . "<br>";
echo "Archivo index.php existe: " . (file_exists(__DIR__ . '/index.php') ? 'Sí' : 'No') . "<br>";
echo "Vendor/autoload.php existe: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'Sí' : 'No') . "<br>";

// Test Laravel bootstrap
try {
    require __DIR__.'/../vendor/autoload.php';
    echo "Autoload cargado correctamente<br>";
} catch (Exception $e) {
    echo "Error cargando autoload: " . $e->getMessage() . "<br>";
}
?>
