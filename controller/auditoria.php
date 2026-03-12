<?php
require_once '../conexion/conexion_mysqli.php';

class Auditoria {
    private $conn;
    
    public function __construct() {
        error_log("Auditoria::__construct() - Iniciando conexión");
        $this->conn = conexionSQL();
        if (!$this->conn) {
            $error = "Error de conexión a la base de datos";
            error_log($error);
            throw new Exception($error);
        }
        error_log("Auditoria::__construct() - Conexión exitosa");
    }
    
    /**
     * Registra una acción en la tabla de auditoría
     */
    public function registrarAccion($accion, $modulo, $valor_buscado, $numero_orden, $resultado = 'EXITO', $detalles = '') {
        error_log("Auditoria::registrarAccion - Inicio");
        
        // Verificar si hay sesión activa
        if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
            error_log('Auditoria::registrarAccion - Intento sin sesión activa');
            return false;
        }
        
        // Obtener datos del usuario desde la sesión
        $usuario_id = $_SESSION['id_user'] ?? null;
        $usuario_nombre = $_SESSION['nombre'] ?? '';
        $usuario_correo = $_SESSION['correo'] ?? '';
        
        error_log("Auditoria::registrarAccion - Usuario ID: $usuario_id, Nombre: $usuario_nombre, Correo: $usuario_correo");
        
        // Si no hay usuario, no registrar
        if (!$usuario_id) {
            error_log('Auditoria::registrarAccion - Sin ID de usuario');
            return false;
        }
        
        // Obtener IP del cliente
        $ip_cliente = $this->getClientIP();
        error_log("Auditoria::registrarAccion - IP Cliente: $ip_cliente");
        
        // Obtener User Agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        error_log("Auditoria::registrarAccion - User Agent: " . substr($user_agent, 0, 50) . "...");
        
        // Preparar la consulta
        $sql = "INSERT INTO DPL.externos.Auditoria_Acciones 
                (fecha_hora, usuario_id, usuario_nombre, usuario_correo, accion, modulo, 
                 valor_buscado, numero_orden, resultado, detalles, ip_cliente, user_agent) 
                VALUES (GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        error_log("Auditoria::registrarAccion - SQL: " . $sql);
        
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
        
        error_log("Auditoria::registrarAccion - Parámetros: " . print_r($params, true));
        
        $stmt = sqlsrv_query($this->conn, $sql, $params);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            error_log('Auditoria::registrarAccion - Error SQL: ' . print_r($errors, true));
            return false;
        }
        
        sqlsrv_free_stmt($stmt);
        error_log("Auditoria::registrarAccion - Registro exitoso");
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
}
?>