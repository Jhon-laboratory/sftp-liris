<?php
/**
 * Wrapper de conexión para mantener compatibilidad con el código existente
 * Incluye el archivo real de conexión y expone la función necesaria
 */

require_once __DIR__ . '/../conexion/conexion_mysqli.php';

/**
 * Clase Conexion para mantener compatibilidad con el código que espera una clase
 */
class Conexion {
    private $conn;
    
    /**
     * Método conectar que devuelve el recurso de conexión de sqlsrv
     */
    public function conectar() {
        $this->conn = conexionSQL();
        return $this->conn;
    }
    
    /**
     * Método para ejecutar consultas (útil para SQL Server)
     */
    public function query($sql, $params = []) {
        if (!$this->conn) {
            $this->conectar();
        }
        
        $stmt = sqlsrv_query($this->conn, $sql, $params);
        
        if ($stmt === false) {
            error_log("Error en query: " . print_r(sqlsrv_errors(), true));
            return false;
        }
        
        return $stmt;
    }
    
    /**
     * Método para obtener resultados como array asociativo
     */
    public function fetchAll($stmt) {
        $results = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Convertir objetos DateTime a string si es necesario
            array_walk_recursive($row, function(&$value) {
                if ($value instanceof DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                }
            });
            $results[] = $row;
        }
        return $results;
    }
    
    /**
     * Cerrar conexión
     */
    public function cerrar() {
        if ($this->conn) {
            sqlsrv_close($this->conn);
        }
    }
}
?>