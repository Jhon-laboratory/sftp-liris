<?php
/**
 * Archivo de conexión a SQL Server para el sistema LIRIS
 * Ubicación: /var/www/html/sftp-liris/Conexion/conexion_mysqli.php
 */

// Evitar acceso directo
if (!defined('ACCESS_ALLOWED') && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

// Configuración de la base de datos - ¡CAMBIA ESTOS VALORES!
define('DB_SERVER', 'Jorgeserver.database.windows.net');        // IP o nombre del servidor SQL Server
define('DB_NAME', 'DPL');                      // Nombre de la base de datos
define('DB_USER', 'Jmmc');              // Usuario de SQL Server
define('DB_PASSWORD', 'ChaosSoldier01');       // Contraseña de SQL Server
define('DB_ENCODING', 'UTF-8');                // Codificación

/**
 * Función principal de conexión a SQL Server
 * @return resource|false Recurso de conexión o false si hay error
 */
function conexionSQL() {
    static $conn = null;
    
    // Si ya hay una conexión activa, la retornamos
    if ($conn !== null && is_resource($conn)) {
        return $conn;
    }
    
    // Configuración de la conexión
    $connectionOptions = array(
        "Database" => DB_NAME,
        "Uid" => DB_USER,
        "PWD" => DB_PASSWORD,
        "CharacterSet" => DB_ENCODING,
        "ReturnDatesAsStrings" => true, // Para que las fechas vengan como string
        "MultipleActiveResultSets" => false,
        "ConnectionPooling" => true,
        "Encrypt" => false,              // Cambiar a true si usas SSL
        "TrustServerCertificate" => false // Cambiar a true si usas SSL
    );
    
    // Intentar conexión
    $conn = sqlsrv_connect(DB_SERVER, $connectionOptions);
    
    // Verificar si hubo error
    if ($conn === false) {
        $errors = sqlsrv_errors();
        $errorMsg = "Error de conexión a SQL Server:\n";
        foreach ($errors as $error) {
            $errorMsg .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
            $errorMsg .= "Código: " . $error['code'] . "\n";
            $errorMsg .= "Mensaje: " . $error['message'] . "\n";
        }
        
        // Registrar error en log
        error_log($errorMsg);
        
        // En entorno de desarrollo, puedes mostrar el error
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            die(nl2br($errorMsg));
        }
        
        return false;
    }
    
    // Configurar opciones adicionales si es necesario
    sqlsrv_configure('WarningsReturnAsErrors', 1);
    
    return $conn;
}

/**
 * Función para cerrar la conexión explícitamente
 * @param resource $conn Recurso de conexión
 * @return bool True si se cerró correctamente
 */
function cerrarConexion($conn) {
    if ($conn && is_resource($conn)) {
        return sqlsrv_close($conn);
    }
    return true;
}

/**
 * Función para ejecutar una consulta con manejo de errores
 * @param resource $conn Recurso de conexión
 * @param string $sql Consulta SQL
 * @param array $params Parámetros para la consulta
 * @return resource|false Recurso de resultado o false si hay error
 */
function ejecutarConsulta($conn, $sql, $params = array()) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMsg = "Error en consulta SQL:\n$sql\n";
        foreach ($errors as $error) {
            $errorMsg .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
            $errorMsg .= "Código: " . $error['code'] . "\n";
            $errorMsg .= "Mensaje: " . $error['message'] . "\n";
        }
        error_log($errorMsg);
        return false;
    }
    
    return $stmt;
}

/**
 * Función para obtener un array asociativo de un resultado
 * @param resource $stmt Recurso de statement
 * @return array|false Array asociativo o false si no hay más filas
 */
function fetchAssoc($stmt) {
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

/**
 * Función para obtener todos los resultados como array
 * @param resource $stmt Recurso de statement
 * @return array Array con todos los resultados
 */
function fetchAll($stmt) {
    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    return $results;
}

/**
 * Función para obtener el último ID insertado
 * @param resource $conn Recurso de conexión
 * @return mixed Último ID insertado o false si hay error
 */
function ultimoId($conn) {
    $stmt = sqlsrv_query($conn, "SELECT SCOPE_IDENTITY() AS id");
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        sqlsrv_free_stmt($stmt);
        return $row['id'];
    }
    return false;
}

/**
 * Función para escapar strings (sqlsrv no necesita escaping con parámetros)
 * @param string $str String a "escapar"
 * @return string El mismo string (mantenido por compatibilidad)
 */
function escapeString($str) {
    return $str;
}

// Ejemplo de uso (comentado para no ejecutar automáticamente)
/*
$conn = conexionSQL();
if ($conn) {
    echo "Conexión exitosa a SQL Server";
    
    $sql = "SELECT * FROM IT.usuarios_pt WHERE id = ?";
    $params = array(1);
    $stmt = ejecutarConsulta($conn, $sql, $params);
    
    if ($stmt) {
        while ($row = fetchAssoc($stmt)) {
            print_r($row);
        }
        sqlsrv_free_stmt($stmt);
    }
    
    cerrarConexion($conn);
}
*/
?>