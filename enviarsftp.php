<?php
require __DIR__ . '/phpseclib/vendor/autoload.php';
use phpseclib3\Net\SFTP;

header('Content-Type: application/json');

// Permitir CORS (importante para desarrollo)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ---------- SEGURIDAD (COMENTAR TEMPORALMENTE PARA PRUEBAS) ----------
/*
$allowed_ips = ['127.0.0.1', '::1']; // Agregar IPs permitidas
$client_ip = $_SERVER['REMOTE_ADDR'];

if (!in_array($client_ip, $allowed_ips)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado desde IP: ' . $client_ip]);
    exit;
}
*/

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

// Validar que sea un archivo TXT de LIRIS (relajar validación para pruebas)
if (!preg_match('/^LIRIS_.+\.txt$/', $nombre_archivo)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Nombre de archivo inválido. Debe empezar con LIRIS_ y terminar en .txt',
        'nombre_recibido' => $nombre_archivo
    ]);
    exit;
}

// ---------- CONEXIÓN SFTP ----------
$host = '40.121.159.89';
$port = 22;
$user = 'lirisprd';
$pass = 'lirisPROD01';

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
    
    // Verificar que la carpeta /ENVIA existe
    $remoteDir = '/RECIBE/TRANSACCIONES/ConfirmacionCG/Historico';
    if (!$sftp->file_exists($remoteDir)) {
        if (!$sftp->mkdir($remoteDir, 0755)) {
            throw new Exception("No se pudo crear la carpeta $remoteDir en el servidor SFTP");
        }
    }
    
    // Crear carpeta con timestamp diario (opcional)
    $fecha_carpeta = date('Ymd');
    $carpeta_diaria = $remoteDir . '/' . $fecha_carpeta;
    
    if (!$sftp->file_exists($carpeta_diaria)) {
        $sftp->mkdir($carpeta_diaria, 0755);
    }
    
    // Subir archivo principal en /ENVIA
    $remote_path = $remoteDir . '/' . $nombre_archivo;
    
    // DEBUG: Verificar contenido
    $tamano = strlen($contenido);
    $lineas = substr_count($contenido, "\n") + 1;
    
    // Intentar subir el archivo
    if ($sftp->put($remote_path, $contenido)) {
        // Copia de seguridad en carpeta diaria
        $backup_path = $carpeta_diaria . '/' . $nombre_archivo;
        $sftp->put($backup_path, $contenido);
        
        // Registrar en log local
        $log_entry = date('Y-m-d H:i:s') . " | Orden: $orden | Archivo: $nombre_archivo | Tamaño: $tamano bytes | Líneas: $lineas | IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
        file_put_contents($log_dir . '/sftp_log.txt', $log_entry, FILE_APPEND);
        
        echo json_encode([
            'success' => true,
            'mensaje' => 'Archivo enviado correctamente a SFTP',
            'archivo' => $nombre_archivo,
            'ruta' => $remote_path,
            'bytes' => $tamano,
            'lineas' => $lineas,
            'fecha' => date('Y-m-d H:i:s'),
            'orden' => $orden,
            'backup' => $backup_path
        ]);
        
    } else {
        throw new Exception("Error al subir el archivo al servidor SFTP. Verifica permisos.");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    
    // Log de error
    $error_log = date('Y-m-d H:i:s') . " | ERROR: " . $e->getMessage() . " | Orden: $orden | Archivo: $nombre_archivo | IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    file_put_contents($log_dir . '/sftp_errors.txt', $error_log, FILE_APPEND);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'detalles' => 'Error en conexión SFTP'
    ]);
}
?>