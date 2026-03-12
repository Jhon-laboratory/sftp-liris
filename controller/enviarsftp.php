<?php
// enviarsftp.php - BASADO EN TU ARCHIVO QUE FUNCIONA
require __DIR__ . '/../phpseclib/vendor/autoload.php';
use phpseclib3\Net\SFTP;

// Headers para JSON
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
    
    // Configuración SFTP (TUS DATOS)
    $host = '40.121.159.89';
    $port = 22;
    $user = 'lirisprd';
    $pass = 'lirisPROD01';
    
    // Conectar
    $sftp = new SFTP($host, $port);
    
    if (!$sftp->login($user, $pass)) {
        throw new Exception("No se pudo conectar al SFTP");
    }
    
    // Crear carpeta si no existe
    $remote_dir = '/RECIBE/TRANSACCIONES/ConfirmacionCG/Historico';
    if (!$sftp->file_exists($remote_dir)) {
        $sftp->mkdir($remote_dir, 0755, true);
    }
    
    // Ruta completa del archivo
    $remote_file = $remote_dir . '/' . $nombre_archivo;
    
    // Subir archivo
    if (!$sftp->put($remote_file, $contenido)) {
        throw new Exception("Error al subir el archivo");
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Archivo enviado correctamente',
        'archivo' => $nombre_archivo,
        'ruta' => $remote_file,
        'orden' => $orden
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>