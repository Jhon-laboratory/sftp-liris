<?php
session_start();
date_default_timezone_set('America/Guayaquil'); // UTC-5 (Ecuador)


header('Content-Type: application/json');

require_once __DIR__ . '/conexion.php';

// Verificar autenticación
/*if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}*/

try {
    $db = new Conexion();
    $conn = $db->conectar();

    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }

    // Parámetros de DataTables
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 25);
    $search = $_POST['search']['value'] ?? '';
    
    // Filtros personalizados
    $modulo = $_POST['modulo'] ?? '';
    $resultado = $_POST['resultado'] ?? '';
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    $busqueda = $_POST['busqueda'] ?? '';

    // Si no hay filtros de fecha, usar el día actual
    if (empty($fecha_desde) && empty($fecha_hasta)) {
        $hoy = date('Y-m-d');
        $fecha_desde = $hoy;
        $fecha_hasta = $hoy;
    }

    // Construcción de la cláusula WHERE
    $where = [];
    $params = [];

    if (!empty($modulo)) {
        $where[] = "aa.modulo = ?";
        $params[] = $modulo;
    }
    
    if (!empty($resultado)) {
        $where[] = "aa.resultado = ?";
        $params[] = $resultado;
    }
    
    // 🔥 CORRECCIÓN: Para filtrar, las fechas de búsqueda deben compararse con la BD
    // Como la BD tiene hora +4, debemos restar 4 a la fecha de búsqueda para que coincida
    if (!empty($fecha_desde)) {
        $where[] = "aa.fecha_hora >= ?";
        $fecha_busqueda = $fecha_desde . ' 00:00:00';
        $params[] = $fecha_busqueda; // La BD ya tiene +4, así que no ajustamos
    }
    
    if (!empty($fecha_hasta)) {
        $where[] = "aa.fecha_hora <= ?";
        $fecha_busqueda = $fecha_hasta . ' 23:59:59';
        $params[] = $fecha_busqueda; // La BD ya tiene +4
    }

    $termino = !empty($search) ? $search : $busqueda;
    if (!empty($termino)) {
        $where[] = "(aa.accion LIKE ? OR aa.modulo LIKE ? OR aa.numero_orden LIKE ? OR aa.detalles LIKE ? OR aa.usuario_nombre LIKE ?)";
        $like = "%$termino%";
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $tabla = "externos.Auditoria_Acciones";

    // Total de registros (sin filtros)
    $sql_total = "SELECT COUNT(*) as total FROM $tabla aa";
    $stmt_total = sqlsrv_query($conn, $sql_total);
    if ($stmt_total === false) throw new Exception('Error en total: ' . print_r(sqlsrv_errors(), true));
    $row_total = sqlsrv_fetch_array($stmt_total, SQLSRV_FETCH_ASSOC);
    $total_registros = $row_total['total'];

    // Total filtrados
    $sql_filtrados = "SELECT COUNT(*) as total FROM $tabla aa $where_clause";
    $stmt_filtrados = sqlsrv_query($conn, $sql_filtrados, $params);
    if ($stmt_filtrados === false) throw new Exception('Error en filtrados: ' . print_r(sqlsrv_errors(), true));
    $row_filtrados = sqlsrv_fetch_array($stmt_filtrados, SQLSRV_FETCH_ASSOC);
    $total_filtrados = $row_filtrados['total'];

    // 🔥 CORRECCIÓN: Al mostrar, RESTAMOS 4 horas a la fecha de la BD
    // Porque la BD tiene 18:40 pero queremos mostrar 14:40
    $sql_datos = "SELECT 
                    CONVERT(VARCHAR(19), DATEADD(hour, -5, aa.fecha_hora), 120) as fecha,
                    aa.usuario_nombre as nombre_usuario,
                    aa.accion,
                    aa.modulo,
                    aa.numero_orden,
                    aa.valor_buscado,
                    aa.resultado,
                    aa.detalles
                  FROM $tabla aa
                  $where_clause 
                  ORDER BY aa.fecha_hora DESC 
                  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    $params_datos = array_merge($params, [$start, $length]);
    $stmt_datos = sqlsrv_query($conn, $sql_datos, $params_datos);
    if ($stmt_datos === false) throw new Exception('Error en datos: ' . print_r(sqlsrv_errors(), true));

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt_datos, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            'fecha' => $row['fecha'] ?? '',
            'nombre_usuario' => $row['nombre_usuario'] ?? 'Sistema',
            'accion' => $row['accion'] ?? '',
            'modulo' => $row['modulo'] ?? '',
            'numero_orden' => $row['numero_orden'] ?: ($row['valor_buscado'] ?: '-'),
            'resultado' => $row['resultado'] ?? '',
            'detalles' => $row['detalles'] ?? '-'
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => intval($total_registros),
        'recordsFiltered' => intval($total_filtrados),
        'data' => $data
    ]);

    $db->cerrar();

} catch (Exception $e) {
    error_log('Error en obtener_auditoria.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>