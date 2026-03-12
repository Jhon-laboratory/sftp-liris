<?php
// Mostrar todos los errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test de Auditoría</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<h1>🔍 Test de Auditoría - Diagnóstico Completo</h1>";

// Información del sistema
echo "<h2>📌 Información del Sistema</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Servidor: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";
echo "IP Cliente: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n";
echo "Ruta actual: " . __DIR__ . "\n";
echo "</pre>";

// Verificar sesión
echo "<h2>🔐 Estado de Sesión</h2>";
session_start();
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "logueado: " . (isset($_SESSION['logueado']) ? ($_SESSION['logueado'] ? 'true' : 'false') : 'No existe') . "\n";
echo "id_user: " . ($_SESSION['id_user'] ?? 'No existe') . "\n";
echo "nombre: " . ($_SESSION['nombre'] ?? 'No existe') . "\n";
echo "correo: " . ($_SESSION['correo'] ?? 'No existe') . "\n";
echo "</pre>";

// Verificar archivo de conexión
echo "<h2>📁 Verificando Archivo de Conexión</h2>";
$ruta_conexion = dirname(__DIR__) . '/Conexion/conexion_mysqli.php';
echo "Buscando en: " . $ruta_conexion . "<br>";

if (file_exists($ruta_conexion)) {
    echo "✅ Archivo encontrado<br>";
    require_once $ruta_conexion;
    echo "✅ Archivo incluido correctamente<br>";
    
    // Verificar función
    if (function_exists('conexionSQL')) {
        echo "✅ Función conexionSQL() existe<br>";
        
        // Probar conexión
        echo "<h3>🔌 Probando conexión a Azure SQL</h3>";
        $conn = conexionSQL();
        
        if ($conn) {
            echo "✅ <span class='success'>Conexión exitosa a Azure SQL</span><br>";
            
            // Verificar que la tabla existe
            $sql = "SELECT TOP 1 * FROM DPL.externos.Auditoria_Acciones";
            $stmt = sqlsrv_query($conn, $sql);
            
            if ($stmt) {
                echo "✅ La tabla Auditoria_Acciones existe<br>";
                sqlsrv_free_stmt($stmt);
                
                // Probar inserción
                echo "<h3>📝 Probando inserción</h3>";
                $sql_insert = "INSERT INTO DPL.externos.Auditoria_Acciones 
                              (fecha_hora, usuario_id, usuario_nombre, usuario_correo, 
                               accion, modulo, valor_buscado, numero_orden, resultado, detalles) 
                              VALUES (GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $params = [
                    $_SESSION['id_user'] ?? 1,
                    $_SESSION['nombre'] ?? 'Test',
                    $_SESSION['correo'] ?? 'test@test.com',
                    'TEST',
                    'RECEPCION',
                    'TEST123',
                    'TEST123',
                    'EXITO',
                    'Prueba de inserción'
                ];
                
                $stmt_insert = sqlsrv_query($conn, $sql_insert, $params);
                
                if ($stmt_insert) {
                    echo "✅ Inserción de prueba exitosa<br>";
                    sqlsrv_free_stmt($stmt_insert);
                } else {
                    echo "❌ Error en inserción:<br>";
                    print_r(sqlsrv_errors());
                }
                
            } else {
                echo "❌ Error al verificar tabla:<br>";
                print_r(sqlsrv_errors());
            }
            
            sqlsrv_close($conn);
        } else {
            echo "❌ <span class='error'>Error de conexión a Azure SQL</span><br>";
            echo "<pre>";
            print_r(sqlsrv_errors());
            echo "</pre>";
        }
    } else {
        echo "❌ La función conexionSQL() NO existe<br>";
    }
} else {
    echo "❌ Archivo NO encontrado<br>";
    
    // Intentar crear el archivo
    echo "<h3>🛠 Intentando crear archivo de conexión</h3>";
    $dir_conexion = dirname(__DIR__) . '/Conexion';
    if (!is_dir($dir_conexion)) {
        if (mkdir($dir_conexion, 0755, true)) {
            echo "✅ Carpeta Creada: $dir_conexion<br>";
        } else {
            echo "❌ No se pudo crear la carpeta<br>";
        }
    }
    
    // Mostrar contenido del directorio
    echo "<h3>📂 Contenido de " . dirname(__DIR__) . ":</h3>";
    $dir = dirname(__DIR__);
    if (is_dir($dir)) {
        echo "<pre>";
        print_r(scandir($dir));
        echo "</pre>";
    }
}

// Información de extensiones
echo "<h2>🔧 Extensiones PHP</h2>";
echo "<pre>";
echo "sqlsrv: " . (extension_loaded('sqlsrv') ? '✅ Cargada' : '❌ No cargada') . "\n";
echo "pdo_sqlsrv: " . (extension_loaded('pdo_sqlsrv') ? '✅ Cargada' : '❌ No cargada') . "\n";
echo "</pre>";

echo "</body></html>";
?>