<?php
// enviarsftp.php - CON RUTAS DIFERENTES PARA DESPACHO Y RECEPCIÓN
// VERSIÓN CORREGIDA - Manejo de errores PHP
require __DIR__ . '/../phpseclib/vendor/autoload.php';
use phpseclib3\Net\SFTP;

// 🔥 IMPORTANTE: Deshabilitar muestra de errores en producción
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Solo loguear, no mostrar

// Headers para JSON - DEBEN IR ANTES DE CUALQUIER OUTPUT
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['contenido']) || !isset($input['nombre_archivo'])) {
        throw new Exception('Datos incompletos');
    }
    
    $contenido = $input['contenido'];
    $nombre_archivo = $input['nombre_archivo'];
    $orden = $input['orden'] ?? 'desconocido';
    $tipo = $input['tipo'] ?? 'despacho';
    $lineas = $input['lineas'] ?? 0;
    
    // Configuración SFTP base
    $host = '40.121.159.89';
    $port = 22;
    $user = 'lirisprd';
    $pass = 'lirisPROD01';
    
    // Definir rutas según el tipo
    $rutas = [
        'despacho' => [
            'base' => '/RECIBE/TRANSACCIONES/TransferenciaPick',
            'log_prefix' => 'DESPACHO'
        ],
        'recepcion' => [
            'base' => '/RECIBE/TRANSACCIONES/ConfirmacionOC',
            'log_prefix' => 'RECEPCION'
        ],
        'devolucion' => [
            'base' => '/RECIBE/TRANSACCIONES/DevolucionProveedor/Historico',
            'log_prefix' => 'DEVOLUCION'
        ]
    ];
    
    // Validar que el tipo exista
    if (!isset($rutas[$tipo])) {
        $tipo = 'despacho'; // Por defecto
    }
    
    $ruta_config = $rutas[$tipo];
    $remote_dir = $ruta_config['base'];
    $log_prefix = $ruta_config['log_prefix'];
    
    // 🔥 VERIFICAR QUE PHPSECLIB ESTÁ CARGADO CORRECTAMENTE
    if (!class_exists('phpseclib3\Net\SFTP')) {
        throw new Exception("Clase SFTP no encontrada. Verificar instalación de phpseclib");
    }
    
    // Conectar
    $sftp = new SFTP($host, $port);
    $sftp->setTimeout(30);
    
    if (!$sftp->login($user, $pass)) {
        throw new Exception("No se pudo conectar al SFTP - Credenciales incorrectas o servidor no responde");
    }
    
    // Crear carpeta si no existe
    if (!$sftp->file_exists($remote_dir)) {
        if (!$sftp->mkdir($remote_dir, 0755, true)) {
            throw new Exception("No se pudo crear la carpeta: $remote_dir");
        }
    }
    
    // Ruta completa del archivo
    $remote_file = $remote_dir . '/' . $nombre_archivo;
    
    // Subir archivo
    if (!$sftp->put($remote_file, $contenido)) {
        throw new Exception("Error al subir el archivo");
    }
    
    // Verificar que se subió
    if (!$sftp->file_exists($remote_file)) {
        throw new Exception("No se pudo verificar la subida del archivo");
    }
    
    $file_size = $sftp->filesize($remote_file);
    
    // Log de éxito
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_dir . '/sftp_success.log', 
        date('Y-m-d H:i:s') . " | $log_prefix | $orden | $nombre_archivo | $lineas líneas | $file_size bytes\n", 
        FILE_APPEND
    );
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Archivo enviado correctamente',
        'archivo' => $nombre_archivo,
        'ruta' => $remote_file,
        'orden' => $orden,
        'tipo' => $tipo,
        'lineas' => $lineas,
        'bytes' => $file_size
    ]);
    
} catch (Exception $e) {
    // Log de error
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $error_message = $e->getMessage();
    
    file_put_contents($log_dir . '/sftp_errors.log', 
        date('Y-m-d H:i:s') . " | ERROR: " . $error_message . " | " . ($tipo ?? 'desconocido') . "\n", 
        FILE_APPEND
    );
    
    // 🔥 IMPORTANTE: Limpiar cualquier output previo
    if (ob_get_level()) ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $error_message,
        'archivo' => $nombre_archivo ?? 'desconocido',
        'tipo' => $tipo ?? 'desconocido'
    ]);
}
?>