<?php
require_once '../Conexion/conexion_mysqli.php';

class Auditoria {
    private $conn;
    
    public function __construct() {
        $this->conn = conexionSQL();
        if (!$this->conn) {
            throw new Exception('Error de conexión a la base de datos');
        }
    }
    
    /**
     * Registra una acción en la tabla de auditoría
     */
    public function registrarAccion($accion, $modulo, $valor_buscado, $numero_orden, $resultado = 'EXITO', $detalles = '') {
        // Verificar si hay sesión activa
        if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
            error_log('Intento de auditoría sin sesión activa');
            return false;
        }
        
        // Obtener datos del usuario desde la sesión
        $usuario_id = $_SESSION['id_user'] ?? null;
        $usuario_nombre = $_SESSION['nombre'] ?? '';
        $usuario_correo = $_SESSION['correo'] ?? '';
        
        // Si no hay usuario, no registrar
        if (!$usuario_id) {
            error_log('Intento de auditoría sin ID de usuario');
            return false;
        }
        
        // Obtener IP del cliente
        $ip_cliente = $this->getClientIP();
        
        // Obtener User Agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Preparar la consulta - usando GETDATE() de SQL Server
        $sql = "INSERT INTO DPL.externos.Auditoria_Acciones 
                (fecha_hora, usuario_id, usuario_nombre, usuario_correo, accion, modulo, 
                 valor_buscado, numero_orden, resultado, detalles, ip_cliente, user_agent) 
                VALUES (GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = array(
            $usuario_id,
            $usuario_nombre,
            $usuario_correo,
            $accion,
            $modulo,
            $valor_buscado,
            $numero_orden,
            $resultado,
            $detalles,
            $ip_cliente,
            $user_agent
        );
        
        $stmt = sqlsrv_query($this->conn, $sql, $params);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            error_log('Error SQL en auditoría: ' . print_r($errors, true));
            return false;
        }
        
        sqlsrv_free_stmt($stmt);
        return true;
    }
    
    /**
     * Obtiene la IP real del cliente
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                    'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 
                    'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Método helper para registrar envío SFTP
     */
    public function registrarEnvioSFTP($modulo, $valor_buscado, $numero_orden, $exito = true, $lineas = 0) {
        $resultado = $exito ? 'EXITO' : 'ERROR';
        $detalles = $exito ? "Líneas enviadas: $lineas" : "Error en envío";
        return $this->registrarAccion('ENVIO_SFTP', $modulo, $valor_buscado, $numero_orden, $resultado, $detalles);
    }
    
    /**
     * Método helper para registrar descarga
     */
    public function registrarDescarga($tipo, $modulo, $valor_buscado, $numero_orden, $exito = true, $lineas = 0) {
        $accion = $tipo === 'EXCEL' ? 'DESCARGA_EXCEL' : 'DESCARGA_TXT';
        $resultado = $exito ? 'EXITO' : 'ERROR';
        $detalles = $exito ? "Líneas: $lineas" : "Error en descarga";
        return $this->registrarAccion($accion, $modulo, $valor_buscado, $numero_orden, $resultado, $detalles);
    }
}
?>