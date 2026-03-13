<?php
session_start();
date_default_timezone_set('America/Guayaquil'); // UTC-5 (Ecuador)

require_once __DIR__ . '/conexion.php';

// Verificar autenticación
/*if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header('Location: ../index.php');
    exit;
}*/

try {
    $db = new Conexion();
    $conn = $db->conectar();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    // Obtener filtros de la URL
    $filtros_json = $_GET['filtros'] ?? '{}';
    $filtros = json_decode($filtros_json, true);
    
    // Construir WHERE
    $where = [];
    $params = [];
    
    if (!empty($filtros['modulo'])) {
        $where[] = "modulo = ?";
        $params[] = $filtros['modulo'];
    }
    
    if (!empty($filtros['resultado'])) {
        $where[] = "resultado = ?";
        $params[] = $filtros['resultado'];
    }
    
    if (!empty($filtros['fecha_desde'])) {
        $where[] = "fecha_hora >= ?";
        $params[] = $filtros['fecha_desde'] . ' 00:00:00';
    }
    
    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "fecha_hora <= ?";
        $params[] = $filtros['fecha_hasta'] . ' 23:59:59';
    }
    
    if (!empty($filtros['busqueda'])) {
        $where[] = "(accion LIKE ? OR modulo LIKE ? OR numero_orden LIKE ? OR detalles LIKE ? OR usuario_nombre LIKE ?)";
        $like = "%{$filtros['busqueda']}%";
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // 🔥 CORRECCIÓN: Al exportar, también restamos 4 horas
    $sql = "SELECT 
                CONVERT(VARCHAR(19), DATEADD(hour, -5, fecha_hora), 120) as fecha,
                usuario_nombre,
                accion,
                modulo,
                numero_orden,
                valor_buscado,
                resultado,
                detalles
            FROM externos.Auditoria_Acciones
            $where_clause
            ORDER BY fecha_hora DESC";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) throw new Exception('Error en consulta: ' . print_r(sqlsrv_errors(), true));
    
    // Configurar headers para descarga CSV
    $filename = 'auditoria_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Crear archivo CSV
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, [
        'Fecha',
        'Usuario',
        'Acción',
        'Módulo',
        'Número Orden',
        'Valor Buscado',
        'Resultado',
        'Detalles'
    ], ';');
    
    // Datos
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        fputcsv($output, [
            $row['fecha'] ?? '',
            $row['usuario_nombre'] ?? 'Sistema',
            $row['accion'] ?? '',
            $row['modulo'] ?? '',
            $row['numero_orden'] ?: ($row['valor_buscado'] ?: '-'),
            $row['valor_buscado'] ?: '-',
            $row['resultado'] ?? '',
            $row['detalles'] ?: '-'
        ], ';');
    }
    
    fclose($output);
    $db->cerrar();
    
} catch (Exception $e) {
    error_log('Error en exportar_auditoria.php: ' . $e->getMessage());
    header('Location: ../pages/auditoria.php?error=export');
}
?>