<?php
require_once __DIR__ . '/conexion.php';

echo "<h2>Prueba de Conexión SQL Server Azure</h2>";

try {
    $db = new Conexion();
    $conn = $db->conectar();
    
    if ($conn) {
        echo "<p style='color:green'>✅ Conexión exitosa a SQL Server Azure</p>";
        
        // 🔴 CORRECCIÓN: Usar el nombre correcto de la tabla
        $sql = "SELECT TOP 5 * FROM externos.Auditoria_Acciones ORDER BY fecha_hora DESC";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt) {
            echo "<p style='color:green'>✅ Consulta ejecutada correctamente</p>";
            
            if (sqlsrv_has_rows($stmt)) {
                echo "<p>📊 Últimos 5 registros en Auditoria_Acciones:</p>";
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse; font-size: 12px;'>";
                echo "<tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Módulo</th>
                        <th>N° Orden</th>
                        <th>Resultado</th>
                      </tr>";
                
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>" . ($row['id'] ?? '') . "</td>";
                    echo "<td>" . ($row['fecha_hora'] ? $row['fecha_hora']->format('Y-m-d H:i:s') : '') . "</td>";
                    echo "<td>" . ($row['usuario_nombre'] ?? '') . "</td>";
                    echo "<td>" . ($row['accion'] ?? '') . "</td>";
                    echo "<td>" . ($row['modulo'] ?? '') . "</td>";
                    echo "<td>" . ($row['numero_orden'] ?? '') . "</td>";
                    echo "<td>" . ($row['resultado'] ?? '') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>⚠️ La tabla Auditoria_Acciones está vacía</p>";
            }
            
            sqlsrv_free_stmt($stmt);
        } else {
            echo "<p style='color:red'>❌ Error en consulta: </p>";
            print_r(sqlsrv_errors());
        }
        
        $db->cerrar();
    } else {
        echo "<p style='color:red'>❌ Error de conexión</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Excepción: " . $e->getMessage() . "</p>";
}

echo "<h3>Información del sistema:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "sqlsrv disponible: " . (extension_loaded('sqlsrv') ? '✅ Sí' : '❌ No') . "<br>";
?>