<?php
session_start();
date_default_timezone_set('America/La_Paz');

header('Content-Type: application/json');

require_once __DIR__ . '/conexion.php';

/*if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}*/

try {
    $db = new Conexion();
    $conn = $db->conectar();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    $hoy = date('Y-m-d');
    $tabla = "externos.Auditoria_Acciones";
    
    $stats = [];
    
    // Total de registros
    $sql_total = "SELECT COUNT(*) as total FROM $tabla";
    $stmt = sqlsrv_query($conn, $sql_total);
    if ($stmt === false) throw new Exception('Error en total');
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $stats['total'] = $row['total'] ?? 0;
    
    // Envíos SFTP exitosos
    $sql_envios = "SELECT COUNT(*) as total 
                   FROM $tabla 
                   WHERE accion = 'ENVIO_SFTP' AND resultado = 'EXITO'";
    $stmt = sqlsrv_query($conn, $sql_envios);
    if ($stmt === false) throw new Exception('Error en envios');
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $stats['envios_exitosos'] = $row['total'] ?? 0;
    
    // 🔥 CORRECCIÓN: Para contar las descargas de hoy, usamos la fecha con -4 horas
    $sql_descargas = "SELECT COUNT(*) as total 
                      FROM $tabla 
                      WHERE CAST(DATEADD(hour, -5, fecha_hora) AS DATE) = ?
                      AND accion IN ('DESCARGA_EXCEL', 'DESCARGA_TXT')
                      AND resultado = 'EXITO'";
    $stmt = sqlsrv_query($conn, $sql_descargas, [$hoy]);
    if ($stmt === false) throw new Exception('Error en descargas');
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $stats['descargas_hoy'] = $row['total'] ?? 0;
    
    // Total de errores
    $sql_errores = "SELECT COUNT(*) as total 
                    FROM $tabla 
                    WHERE resultado = 'ERROR'";
    $stmt = sqlsrv_query($conn, $sql_errores);
    if ($stmt === false) throw new Exception('Error en errores');
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $stats['errores'] = $row['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'total' => $stats['total'],
        'envios_exitosos' => $stats['envios_exitosos'],
        'descargas_hoy' => $stats['descargas_hoy'],
        'errores' => $stats['errores']
    ]);
    
    $db->cerrar();
    
} catch (Exception $e) {
    error_log('Error en estadisticas_auditoria.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estadísticas'
    ]);
}
?>