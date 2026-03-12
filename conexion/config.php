<?php
/**
 * Archivo de configuración del sistema
 */

// Definir el entorno (development, production)
define('ENVIRONMENT', 'development'); // Cambiar a 'production' en servidor real

// Configuración según entorno
if (ENVIRONMENT === 'development') {
    // Desarrollo local
    define('DB_SERVER', 'localhost');
    define('DB_NAME', 'IT');
    define('DB_USER', 'sa');
    define('DB_PASSWORD', 'tu_password_local');
    define('BASE_URL', 'http://localhost/sftp-liris');
} else {
    // Producción
    define('DB_SERVER', '192.168.1.100'); // IP real del servidor
    define('DB_NAME', 'IT');
    define('DB_USER', 'usuario_produccion');
    define('DB_PASSWORD', 'contraseña_produccion');
    define('BASE_URL', 'http://9.234.192.192/sftp-liris');
}

// Configuración de zona horaria
date_default_timezone_set('America/La_Paz'); // Ajusta según tu ubicación

// Configuración de errores
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', '/var/log/php_errors.log');
}
?>