<?php
// test_error.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== VERIFICANDO PHP ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n\n";

echo "=== VERIFICANDO EXTENSIONES ===\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? '✓' : '✗') . "\n";
echo "cURL: " . (extension_loaded('curl') ? '✓' : '✗') . "\n";
echo "JSON: " . (extension_loaded('json') ? '✓' : '✗') . "\n\n";

echo "=== VERIFICANDO phpseclib ===\n";
$path = __DIR__ . '/phpseclib/vendor/autoload.php';
if (file_exists($path)) {
    echo "✓ phpseclib encontrado en: $path\n";
    
    // Probar carga
    require_once $path;
    echo "✓ Autoload cargado\n";
    
    // Probar clase
    if (class_exists('phpseclib3\Net\SFTP')) {
        echo "✓ Clase SFTP disponible\n";
    } else {
        echo "✗ Clase SFTP NO disponible\n";
    }
} else {
    echo "✗ phpseclib NO encontrado\n";
    echo "Buscado en: $path\n\n";
    
    // Listar directorio
    echo "Contenido de " . __DIR__ . ":\n";
    foreach (scandir(__DIR__) as $file) {
        echo "- $file\n";
    }
}

echo "\n=== VERIFICANDO PERMISOS ===\n";
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    if (!mkdir($log_dir, 0755, true)) {
        echo "✗ No se pudo crear directorio logs\n";
    } else {
        echo "✓ Directorio logs creado\n";
    }
} else {
    echo "✓ Directorio logs existe\n";
    echo "Permisos: " . decoct(fileperms($log_dir) & 0777) . "\n";
}
?>