<?php
// enviarsftp.php - VERSIÓN LIMPIA SIN POSIBLES ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// 1. Buffer de salida para capturar cualquier output no deseado
ob_start();

// 2. Verificar autoloader
$autoload_path = __DIR__ . '/phpseclib/vendor/autoload.php';
if (!file_exists($autoload_path)) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'phpseclib no encontrado']);
    exit;
}

require_once $autoload_path;

// 3. Configurar headers después de verificar todo
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 4. Manejar OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// 5. Obtener y validar datos
$input_data = file_get_contents('php://input');
if (empty($input_data)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'No se recibieron datos']);
    exit;
}

$input = json_decode($input_data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'error' => 'JSON inválido',
        'json_error' => json_last_error_msg(),
        'raw_data' => substr($input_data, 0, 200)
    ]);
    exit;
}

// 6. Validar campos requeridos
$required = ['contenido', 'nombre_archivo', 'orden'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => "Campo '$field' requerido"]);
        exit;
    }
}

$contenido = $input['contenido'];
$nombre_archivo = $input['nombre_archivo'];
$orden = $input['orden'];

// 7. Limpiar buffer de salida antes de procesar
$buffer_content = ob_get_contents();
if (strlen($buffer_content) > 0) {
    error_log("ADVERTENCIA: Buffer tenía contenido antes de procesar: " . substr($buffer_content, 0, 100));
}
ob_end_clean();

// 8. Configuración SFTP
$host = '40.121.159.89';
$port = 22;
$user = 'lirisprd';
$pass = 'lirisPROD01';
$base_remote_dir = '/RECIBE/TRANSACCIONES/ConfirmacionCG';
$subfolder = 'Historico';

// 9. Crear logs
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

try {
    // 10. Crear conexión SFTP
    $sftp = new phpseclib3\Net\SFTP($host, $port);
    $sftp->setTimeout(30);
    
    if (!$sftp->login($user, $pass)) {
        throw new Exception("Error de autenticación SFTP");
    }
    
    // 11. Verificar/Crear estructura de carpetas
    $main_folder = $base_remote_dir . '/' . $subfolder;
    if (!$sftp->file_exists($main_folder)) {
        if (!$sftp->mkdir($main_folder, 0755, true)) {
            throw new Exception("No se pudo crear carpeta: $main_folder");
        }
    }
    
    // 12. Ruta destino
    $remote_path = $main_folder . '/' . $nombre_archivo;
    
    // 13. Subir archivo
    if (!$sftp->put($remote_path, $contenido)) {
        throw new Exception("Error al subir archivo a SFTP");
    }
    
    // 14. Verificar subida
    if (!$sftp->file_exists($remote_path)) {
        throw new Exception("No se pudo verificar la subida del archivo");
    }
    
    $file_size = $sftp->filesize($remote_path);
    
    // 15. Log de éxito
    file_put_contents($log_dir . '/sftp_success.log', 
        date('Y-m-d H:i:s') . " | $orden | $nombre_archivo | $file_size bytes\n", 
        FILE_APPEND
    );
    
    // 16. Respuesta de éxito
    echo json_encode([
        'success' => true,
        'message' => 'Archivo enviado correctamente',
        'archivo' => $nombre_archivo,
        'ruta' => $remote_path,
        'bytes' => $file_size,
        'orden' => $orden
    ]);
    
} catch (Exception $e) {
    // 17. Log de error
    file_put_contents($log_dir . '/sftp_errors.log', 
        date('Y-m-d H:i:s') . " | ERROR: " . $e->getMessage() . "\n", 
        FILE_APPEND
    );
    
    // 18. Respuesta de error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'archivo' => $nombre_archivo
    ]);
}
?>