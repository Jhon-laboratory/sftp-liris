<?php
/**
 * Archivo de conexión a SQL Server Azure para el sistema LIRIS
 * Ubicación: /var/www/html/sftp-liris/Conexion/conexion_mysqli.php
 */

// Configuración de la base de datos - CON TUS DATOS
define('DB_SERVER', 'Jorgeserver.database.windows.net');        // Servidor Azure
define('DB_NAME', 'DPL');                                        // Base de datos
define('DB_USER', 'Jmmc');                                       // Usuario
define('DB_PASSWORD', 'ChaosSoldier01');                         // Contraseña
define('DB_ENCODING', 'UTF-8');                                  // Codificación

/**
 * Función principal de conexión a SQL Server Azure
 * @return resource|false Recurso de conexión o false si hay error
 */
function conexionSQL() {
    static $conn = null;
    
    // Si ya hay una conexión activa, la retornamos
    if ($conn !== null && is_resource($conn)) {
        return $conn;
    }
    
    // Configuración específica para Azure SQL
    $connectionOptions = array(
        "Database" => DB_NAME,
        "Uid" => DB_USER,
        "PWD" => DB_PASSWORD,
        "CharacterSet" => DB_ENCODING,
        "ReturnDatesAsStrings" => true,          // Las fechas como string
        "MultipleActiveResultSets" => false,
        "ConnectionPooling" => true,
        "Encrypt" => true,                        // Azure requiere encriptación
        "TrustServerCertificate" => false,        // No confiar en certificado local
        "LoginTimeout" => 30,                      // Timeout de login en segundos
        "ConnectTimeout" => 30                      // Timeout de conexión
    );
    
    // Intentar conexión
    $conn = sqlsrv_connect(DB_SERVER, $connectionOptions);
    
    // Verificar si hubo error
    if ($conn === false) {
        $errors = sqlsrv_errors();
        $errorMsg = "Error de conexión a SQL Server Azure:\n";
        foreach ($errors as $error) {
            $errorMsg .= "SQLSTATE: " . ($error['SQLSTATE'] ?? 'N/A') . "\n";
            $errorMsg .= "Código: " . ($error['code'] ?? 'N/A') . "\n";
            $errorMsg .= "Mensaje: " . ($error['message'] ?? 'N/A') . "\n";
        }
        
        // Registrar error en log
        error_log($errorMsg);
        
        return false;
    }
    
    return $conn;
}

/**
 * Función para cerrar la conexión
 */
function cerrarConexion($conn) {
    if ($conn && is_resource($conn)) {
        return sqlsrv_close($conn);
    }
    return true;
}

/**
 * Función para ejecutar una consulta con manejo de errores
 */
function ejecutarConsulta($conn, $sql, $params = array()) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMsg = "Error en consulta SQL:\n$sql\n";
        foreach ($errors as $error) {
            $errorMsg .= "Mensaje: " . ($error['message'] ?? 'N/A') . "\n";
        }
        error_log($errorMsg);
        return false;
    }
    
    return $stmt;
}

/**
 * Función para obtener array asociativo
 */
function fetchAssoc($stmt) {
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

// Probar conexión (descomentar para pruebas)
/*
$conn = conexionSQL();
if ($conn) {
    echo "✅ Conexión exitosa a Azure SQL";
    cerrarConexion($conn);
} else {
    echo "❌ Error de conexión a Azure SQL";
    print_r(sqlsrv_errors());
}
*/
?>