<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Conexión SFTP</h1>";

// Ruta al autoloader
$autoload_path = '/var/www/html/sftp-liris/phpseclib/vendor/autoload.php';

if (!file_exists($autoload_path)) {
    die("❌ phpseclib no encontrado en: $autoload_path");
}

require_once $autoload_path;

echo "✅ phpseclib cargado correctamente<br>";

// Datos de conexión
$host = '40.121.159.89';
$port = 22;
$user = 'lirisprd';
$pass = 'lirisPROD01';

echo "Intentando conectar a $host:$port...<br>";

try {
    $sftp = new phpseclib3\Net\SFTP($host, $port);
    $sftp->setTimeout(10);
    
    if (!$sftp->login($user, $pass)) {
        throw new Exception("Error de autenticación");
    }
    
    echo "✅ Conexión SFTP exitosa<br>";
    echo "Directorio actual: " . $sftp->pwd() . "<br>";
    
    // Listar directorio
    $files = $sftp->nlist();
    echo "Archivos en directorio raíz:<br>";
    echo "<pre>";
    print_r($files);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>