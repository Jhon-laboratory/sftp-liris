<?php
/**
 * Archivo de conexión a SQL Server Azure
 * Ubicación: /var/www/html/sftp-liris/conexion/conexion_mysqli.php
 */

// Configuración de Azure SQL - TUS DATOS
define('DB_SERVER', 'Jorgeserver.database.windows.net');
define('DB_NAME', 'DPL');
define('DB_USER', 'Jmmc');
define('DB_PASSWORD', 'ChaosSoldier01');
define('DB_ENCODING', 'UTF-8');

/**
 * Función principal de conexión a SQL Server Azure
 * Versión corregida - Sin opciones no válidas
 */
function conexionSQL() {
    // Opciones VÁLIDAS para sqlsrv_connect
    $connectionOptions = array(
        "Database" => DB_NAME,
        "Uid" => DB_USER,
        "PWD" => DB_PASSWORD,
        "CharacterSet" => DB_ENCODING,
        "ReturnDatesAsStrings" => true,
        "Encrypt" => true,                    // Requerido para Azure
        "TrustServerCertificate" => false,
        "LoginTimeout" => 30                   // Timeout de login en segundos (SÍ es válido)
        // "ConnectTimeout" => 30              // ❌ ESTA OPCIÓN NO ES VÁLIDA - Eliminada
    );
    
    // Intentar conexión
    $conn = sqlsrv_connect(DB_SERVER, $connectionOptions);
    
    if ($conn === false) {
        // Registrar error sin mostrarlo al usuario
        $errors = sqlsrv_errors();
        error_log("Error conexión Azure: " . print_r($errors, true));
        return false;
    }
    
    return $conn;
}

// Para pruebas rápidas - descomentar SOLO para probar
/*
echo "<pre>";
$conn = conexionSQL();
if ($conn) {
    echo "✅ Conectado a Azure SQL\n";
    
    // Probar query simple
    $sql = "SELECT @@VERSION as version";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        echo "Versión: " . substr($row['version'], 0, 100) . "...\n";
        sqlsrv_free_stmt($stmt);
    }
    
    sqlsrv_close($conn);
} else {
    echo "❌ Error de conexión\n";
    print_r(sqlsrv_errors());
}
echo "</pre>";
*/
?>