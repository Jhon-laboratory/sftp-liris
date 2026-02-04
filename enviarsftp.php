<?php
require __DIR__ . '/phpseclib/vendor/autoload.php';
use phpseclib3\Net\SFTP;

header('Content-Type: application/json');

// Permitir CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ---------- VALIDACIÓN DE DATOS ----------
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibieron datos JSON']);
    exit;
}

$contenido = $input['contenido'] ?? '';
$nombre_archivo = $input['nombre_archivo'] ?? '';
$orden = $input['orden'] ?? '';

if (empty($contenido) || empty($nombre_archivo) || empty($orden)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Datos incompletos',
        'recibido' => [
            'contenido' => !empty($contenido),
            'nombre_archivo' => $nombre_archivo,
            'orden' => $orden
        ]
    ]);
    exit;
}

// Validación más flexible del nombre del archivo
if (!preg_match('/\.txt$/i', $nombre_archivo)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Nombre de archivo inválido. Debe terminar en .txt',
        'nombre_recibido' => $nombre_archivo
    ]);
    exit;
}

// ---------- CONFIGURACIÓN SFTP ----------
$host = '40.121.159.89';
$port = 22;
$user = 'lirisprd';
$pass = 'lirisPROD01';

// **CAMBIOS IMPORTANTES AQUÍ:**
// 1. Carpeta base donde se deben colocar los archivos
$base_remote_dir = '/RECIBE/TRANSACCIONES/ConfirmacionCG';
// 2. Subcarpeta específica para estos archivos
$subfolder = 'Historico';

// Crear carpeta logs si no existe
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

try {
    // Crear conexión SFTP
    $sftp = new SFTP($host, $port);
    $sftp->setTimeout(30); // Timeout de 30 segundos
    
    if (!$sftp->login($user, $pass)) {
        throw new Exception("No se pudo autenticar en el servidor SFTP. Verifica credenciales.");
    }
    
    // **VERIFICAR Y CREAR ESTRUCTURA DE CARPETAS**
    // Lista de carpetas que deben existir
    $folders = [
        $base_remote_dir,
        $base_remote_dir . '/' . $subfolder
    ];
    
    foreach ($folders as $folder) {
        if (!$sftp->file_exists($folder)) {
            if (!$sftp->mkdir($folder, 0755, true)) {
                throw new Exception("No se pudo crear la carpeta: $folder");
            }
        }
    }
    
    // **CREAR CARPETA DIARIA PARA BACKUP**
    $fecha_carpeta = date('Ymd');
    $carpeta_diaria = $base_remote_dir . '/' . $subfolder . '/' . $fecha_carpeta;
    
    if (!$sftp->file_exists($carpeta_diaria)) {
        if (!$sftp->mkdir($carpeta_diaria, 0755)) {
            // Si no se puede crear la carpeta diaria, continuar igual
            // Los archivos se subirán a la carpeta principal
        }
    }
    
    // **RUTAS DE DESTINO**
    // 1. Ruta principal (donde debe ir el archivo activo)
    $remote_path_main = $base_remote_dir . '/' . $subfolder . '/' . $nombre_archivo;
    
    // 2. Ruta de backup (en carpeta diaria)
    $remote_path_backup = $carpeta_diaria . '/' . $nombre_archivo;
    
    // **VALIDAR CONTENIDO**
    $tamano = strlen($contenido);
    $lineas = substr_count($contenido, "\n") + 1;
    
    if (empty(trim($contenido))) {
        throw new Exception("El contenido del archivo está vacío");
    }
    
    // **VERIFICAR SI EL ARCHIVO YA EXISTE**
    if ($sftp->file_exists($remote_path_main)) {
        // OPCIONAL: Renombrar archivo existente con timestamp
        $timestamp = date('Ymd_His');
        $old_filename = $nombre_archivo . '_' . $timestamp;
        $old_path = $base_remote_dir . '/' . $subfolder . '/' . $old_filename;
        
        if (!$sftp->rename($remote_path_main, $old_path)) {
            // Si no se puede renombrar, eliminar el viejo
            $sftp->delete($remote_path_main);
        }
    }
    
    // **LOG DE DEPURACIÓN**
    $debug_log = date('Y-m-d H:i:s') . " | Archivo: $nombre_archivo | Tamaño: $tamano bytes | Líneas: $lineas\n";
    $debug_log .= "Ruta principal: $remote_path_main\n";
    $debug_log .= "Ruta backup: $remote_path_backup\n\n";
    file_put_contents($log_dir . '/sftp_debug.txt', $debug_log, FILE_APPEND);
    
    // **SUBIR ARCHIVO PRINCIPAL**
    if (!$sftp->put($remote_path_main, $contenido)) {
        $last_error = $sftp->getLastSFTPError();
        throw new Exception("Error al subir archivo principal: " . $last_error);
    }
    
    // **SUBIR COPIA DE BACKUP (si existe la carpeta diaria)**
    if ($sftp->file_exists($carpeta_diaria)) {
        $sftp->put($remote_path_backup, $contenido);
    }
    
    // **VERIFICAR QUE EL ARCHIVO SE SUBIÓ CORRECTAMENTE**
    if (!$sftp->file_exists($remote_path_main)) {
        throw new Exception("No se pudo verificar la subida del archivo");
    }
    
    // **LOGS DE ÉXITO**
    $log_entry = date('Y-m-d H:i:s') . " | ÉXITO\n";
    $log_entry .= "Orden: $orden\n";
    $log_entry .= "Archivo: $nombre_archivo\n";
    $log_entry .= "Tamaño: $tamano bytes\n";
    $log_entry .= "Líneas: $lineas\n";
    $log_entry .= "Ruta: $remote_path_main\n";
    $log_entry .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida') . "\n";
    $log_entry .= "-----------------------------\n";
    
    file_put_contents($log_dir . '/sftp_log.txt', $log_entry, FILE_APPEND);
    
    // **RESPUESTA DE ÉXITO**
    echo json_encode([
        'success' => true,
        'mensaje' => 'Archivo enviado correctamente a SFTP',
        'archivo' => $nombre_archivo,
        'ruta' => $remote_path_main,
        'bytes' => $tamano,
        'lineas' => $lineas,
        'fecha' => date('Y-m-d H:i:s'),
        'orden' => $orden,
        'backup' => $remote_path_backup,
        'server_path' => $base_remote_dir . '/' . $subfolder
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    
    // **LOG DE ERROR DETALLADO**
    $error_log = date('Y-m-d H:i:s') . " | ERROR\n";
    $error_log .= "Mensaje: " . $e->getMessage() . "\n";
    $error_log .= "Orden: $orden\n";
    $error_log .= "Archivo: $nombre_archivo\n";
    $error_log .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida') . "\n";
    $error_log .= "Trace: " . $e->getTraceAsString() . "\n";
    $error_log .= "-----------------------------\n";
    
    file_put_contents($log_dir . '/sftp_errors.txt', $error_log, FILE_APPEND);
    
    // **RESPUESTA DE ERROR**
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'detalles' => 'Error en conexión SFTP',
        'archivo_intentado' => $nombre_archivo,
        'ruta_intentada' => isset($remote_path_main) ? $remote_path_main : 'No definida',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>