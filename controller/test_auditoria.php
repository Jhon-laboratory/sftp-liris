<?php
// Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Prueba de auditoría</h1>";

// Iniciar sesión
session_start();
echo "<h2>Sesión:</h2>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

// Incluir archivos
echo "<h2>Incluyendo archivos:</h2>";
$ruta_conexion = '../Conexion/conexion_mysqli.php';
echo "Buscando: $ruta_conexion<br>";

if (file_exists($ruta_conexion)) {
    echo "✓ Archivo encontrado<br>";
    require_once $ruta_conexion;
    echo "✓ Incluido correctamente<br>";
    
    $conn = conexionSQL();
    if ($conn) {
        echo "✓ Conexión a BD exitosa<br>";
        
        // Probar inserción
        $sql = "INSERT INTO DPL.externos.Auditoria_Acciones 
                (fecha_hora, usuario_id, usuario_nombre, accion, modulo, resultado) 
                VALUES (GETDATE(), 1, 'TEST', 'TEST', 'TEST', 'TEST')";
        
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt) {
            echo "✓ Inserción de prueba exitosa<br>";
            sqlsrv_free_stmt($stmt);
        } else {
            echo "✗ Error en inserción:<br>";
            print_r(sqlsrv_errors());
        }
    } else {
        echo "✗ Error de conexión a BD<br>";
    }
} else {
    echo "✗ Archivo NO encontrado<br>";
}

echo "<h2>Ruta actual:</h2>";
echo __DIR__;
?>