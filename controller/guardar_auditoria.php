<?php
session_start();
header('Content-Type: application/json');

// Habilitar logs para depuración
error_log("=== INICIO guardar_auditoria.php ===");
error_log("Session ID: " . session_id());
error_log("Usuario en sesión: " . (isset($_SESSION['logueado']) ? $_SESSION['logueado'] : 'No existe'));
error_log("ID User: " . (isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 'No existe'));

require_once 'auditoria.php';

// Verificar que hay sesión activa
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    error_log("ERROR: No autorizado - sesión no iniciada");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado - Sesión no iniciada']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
error_log("Datos recibidos: " . print_r($input, true));

if (!$input) {
    error_log("ERROR: Datos inválidos - JSON mal formado");
    echo json_encode(['success' => false, 'error' => 'Datos inválidos - JSON mal formado']);
    exit;
}

// Validar campos requeridos
$campos_requeridos = ['accion', 'modulo', 'resultado'];
foreach ($campos_requeridos as $campo) {
    if (!isset($input[$campo])) {
        error_log("ERROR: Campo $campo requerido");
        echo json_encode(['success' => false, 'error' => "Campo $campo requerido"]);
        exit;
    }
}

// Validar que la acción sea válida
$acciones_validas = ['ENVIO_SFTP', 'DESCARGA_EXCEL', 'DESCARGA_TXT'];
if (!in_array($input['accion'], $acciones_validas)) {
    error_log("ERROR: Acción no válida: " . $input['accion']);
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    exit;
}

// Validar que el módulo sea válido
$modulos_validos = ['DESPACHO', 'RECEPCION'];
if (!in_array($input['modulo'], $modulos_validos)) {
    error_log("ERROR: Módulo no válido: " . $input['modulo']);
    echo json_encode(['success' => false, 'error' => 'Módulo no válido']);
    exit;
}

// Validar resultado
$resultados_validos = ['EXITO', 'ERROR', 'CANCELADO', 'INICIADO'];
if (!in_array($input['resultado'], $resultados_validos)) {
    error_log("ERROR: Resultado no válido: " . $input['resultado']);
    echo json_encode(['success' => false, 'error' => 'Resultado no válido']);
    exit;
}

// Registrar la acción
try {
    error_log("Creando instancia de Auditoria...");
    $auditoria = new Auditoria();
    
    error_log("Llamando a registrarAccion con:");
    error_log("  accion: " . $input['accion']);
    error_log("  modulo: " . $input['modulo']);
    error_log("  valor_buscado: " . ($input['valor_buscado'] ?? ''));
    error_log("  numero_orden: " . ($input['numero_orden'] ?? ''));
    error_log("  resultado: " . $input['resultado']);
    error_log("  detalles: " . ($input['detalles'] ?? ''));
    
    $success = $auditoria->registrarAccion(
        $input['accion'],
        $input['modulo'],
        $input['valor_buscado'] ?? '',
        $input['numero_orden'] ?? '',
        $input['resultado'],
        $input['detalles'] ?? ''
    );
    
    error_log("Resultado de registrarAccion: " . ($success ? 'true' : 'false'));
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar en base de datos']);
    }
} catch (Exception $e) {
    error_log('Excepción en auditoría: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}

error_log("=== FIN guardar_auditoria.php ===");
?>