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
 * VERSIÓN SIMPLIFICADA - Sin opciones problemáticas
 */
function conexionSQL() {
    // Opciones MÍNIMAS Y VÁLIDAS para sqlsrv_connect
    $connectionOptions = array(
        "Database" => DB_NAME,
        "Uid" => DB_USER,
        "PWD" => DB_PASSWORD,
        "CharacterSet" => DB_ENCODING,
        "ReturnDatesAsStrings" => true,
        "Encrypt" => true,
        "TrustServerCertificate" => false,
        "LoginTimeout" => 30
        // "ConnectTimeout" => 30  ← ESTA LÍNEA NO DEBE EXISTIR
    );
    
    error_log("Intentando conectar a: " . DB_SERVER);
    
    $conn = sqlsrv_connect(DB_SERVER, $connectionOptions);
    
    if ($conn === false) {
        $errors = sqlsrv_errors();
        error_log("Error conexión Azure: " . print_r($errors, true));
        return false;
    }
    
    error_log("Conexión exitosa a Azure SQL");
    return $conn;
}

// Prueba simple - descomentar solo para probar
/*
$conn = conexionSQL();
if ($conn) {
    echo "✅ Conectado a Azure SQL\n";
    sqlsrv_close($conn);
} else {
    echo "❌ Error de conexión\n";
    print_r(sqlsrv_errors());
}
*/
?>