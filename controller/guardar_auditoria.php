<?php
session_start();
header('Content-Type: application/json');

require_once 'auditoria.php';

// Verificar que hay sesión activa
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado - Sesión no iniciada']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos - JSON mal formado']);
    exit;
}

// Validar campos requeridos
$campos_requeridos = ['accion', 'modulo', 'resultado'];
foreach ($campos_requeridos as $campo) {
    if (!isset($input[$campo])) {
        echo json_encode(['success' => false, 'error' => "Campo $campo requerido"]);
        exit;
    }
}

// Validar que la acción sea válida
$acciones_validas = ['ENVIO_SFTP', 'DESCARGA_EXCEL', 'DESCARGA_TXT'];
if (!in_array($input['accion'], $acciones_validas)) {
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    exit;
}

// Validar que el módulo sea válido
$modulos_validos = ['DESPACHO', 'RECEPCION'];
if (!in_array($input['modulo'], $modulos_validos)) {
    echo json_encode(['success' => false, 'error' => 'Módulo no válido']);
    exit;
}

// Validar resultado
$resultados_validos = ['EXITO', 'ERROR', 'CANCELADO', 'INICIADO'];
if (!in_array($input['resultado'], $resultados_validos)) {
    echo json_encode(['success' => false, 'error' => 'Resultado no válido']);
    exit;
}

// Registrar la acción
try {
    $auditoria = new Auditoria();
    $success = $auditoria->registrarAccion(
        $input['accion'],
        $input['modulo'],
        $input['valor_buscado'] ?? '',
        $input['numero_orden'] ?? '',
        $input['resultado'],
        $input['detalles'] ?? ''
    );
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar en base de datos']);
    }
} catch (Exception $e) {
    error_log('Error en auditoría: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
?>