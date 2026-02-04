<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// ---------- CONFIGURACIONES DE SEGURIDAD ----------
define('MAX_REQUESTS_PER_MINUTE', 5); // Máximo 5 consultas por minuto por IP
define('REQUEST_TIMEOUT_SECONDS', 30); // Timeout para llamadas API
define('API_WAIT_SECONDS', 10); // Espera mínima de 10 segundos entre consultas
define('TOKEN_CACHE_TIME', 300); // Cachear token por 5 minutos (300 segundos)
define('LPN_PREDETERMINADO', 'DP00000000572181'); // LPN predeterminado para líneas sin LPN

// ---------- FUNCIONES DE SEGURIDAD Y CONTROL ----------

// Obtener IP del cliente
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER)) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// Control de tasa de solicitudes (Rate Limiting)
function checkRateLimit($ip) {
    $cacheDir = sys_get_temp_dir() . '/liris_cache/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $rateFile = $cacheDir . 'rate_' . md5($ip) . '.json';
    $currentTime = time();
    
    if (file_exists($rateFile)) {
        $rateData = json_decode(file_get_contents($rateFile), true);
        
        // Verificar si hay que esperar 10 segundos
        if (isset($rateData['last_request']) && 
            ($currentTime - $rateData['last_request']) < API_WAIT_SECONDS) {
            $waitTime = API_WAIT_SECONDS - ($currentTime - $rateData['last_request']);
            return [
                'allowed' => false,
                'wait_time' => $waitTime,
                'message' => "Debe esperar {$waitTime} segundos antes de realizar otra consulta"
            ];
        }
        
        // Verificar límite por minuto
        if (isset($rateData['requests_per_minute'])) {
            $minuteAgo = $currentTime - 60;
            $recentRequests = array_filter($rateData['requests_per_minute'], function($time) use ($minuteAgo) {
                return $time > $minuteAgo;
            });
            
            if (count($recentRequests) >= MAX_REQUESTS_PER_MINUTE) {
                return [
                    'allowed' => false,
                    'wait_time' => 60,
                    'message' => "Límite de consultas excedido. Espere 1 minuto"
                ];
            }
            
            $recentRequests[] = $currentTime;
            $rateData['requests_per_minute'] = $recentRequests;
        } else {
            $rateData['requests_per_minute'] = [$currentTime];
        }
    } else {
        $rateData = [
            'requests_per_minute' => [$currentTime],
            'last_request' => $currentTime
        ];
    }
    
    $rateData['last_request'] = $currentTime;
    file_put_contents($rateFile, json_encode($rateData));
    
    return ['allowed' => true];
}

// Cache de tokens con sistema de archivos
function getCachedToken() {
    $cacheFile = sys_get_temp_dir() . '/liris_cache/token_cache.json';
    
    if (file_exists($cacheFile)) {
        $tokenData = json_decode(file_get_contents($cacheFile), true);
        
        // Verificar si el token sigue siendo válido (menos de 5 minutos)
        if (isset($tokenData['timestamp']) && 
            isset($tokenData['token']) &&
            (time() - $tokenData['timestamp']) < TOKEN_CACHE_TIME) {
            return $tokenData['token'];
        }
    }
    
    return false;
}

function cacheToken($token) {
    $cacheDir = sys_get_temp_dir() . '/liris_cache/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $tokenData = [
        'token' => $token,
        'timestamp' => time()
    ];
    
    file_put_contents($cacheDir . 'token_cache.json', json_encode($tokenData));
}

// Limpiar cache viejo (ejecutar periódicamente)
function cleanupOldCache() {
    $cacheDir = sys_get_temp_dir() . '/liris_cache/';
    if (!is_dir($cacheDir)) return;
    
    $files = glob($cacheDir . 'rate_*.json');
    $currentTime = time();
    $oneDayAgo = $currentTime - 86400; // 24 horas
    
    foreach ($files as $file) {
        if (filemtime($file) < $oneDayAgo) {
            unlink($file);
        }
    }
}

// ---------- VALIDACIÓN INICIAL ----------
if(!isset($_GET['valor']) || empty($_GET['valor'])){
    echo json_encode(["error" => "No se proporcionó número de orden"]);
    exit;
}

// Sanitizar input - VERSIÓN CORREGIDA
$valor = trim($_GET['valor']);
// Permite: letras, números, guiones, guiones bajos, puntos y el signo de porcentaje
if(!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $valor)){
    echo json_encode(["error" => "Número de orden inválido"]);
    exit;
}

// Verificar rate limiting
$clientIP = getClientIP();
$rateCheck = checkRateLimit($clientIP);

if(!$rateCheck['allowed']){
    http_response_code(429); // Too Many Requests
    echo json_encode([
        "error" => $rateCheck['message'],
        "wait_time" => $rateCheck['wait_time']
    ]);
    exit;
}

// Limpiar cache viejo (solo 1 de cada 100 veces para no afectar performance)
if(rand(1, 100) === 1){
    cleanupOldCache();
}

// ---------- FUNCIONES AUXILIARES ----------
function formatearFechaInfor($fechaIso){
    if(!$fechaIso) return "";
    try {
        $fecha = DateTime::createFromFormat(DateTime::ISO8601, $fechaIso);
        
        if (!$fecha) {
            $fecha = new DateTime($fechaIso);
        }
        
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        $dia = $fecha->format('d');
        $mes = $meses[(int)$fecha->format('m')];
        $anio = $fecha->format('Y');
        $hora = $fecha->format('H:i');
        
        return "$dia/$mes/$anio $hora";
        
    } catch (Exception $e) {
        return $fechaIso;
    }
}

function obtenerTokenInfor() {
    // Intentar obtener token del cache primero
    $cachedToken = getCachedToken();
    if($cachedToken !== false){
        return $cachedToken;
    }
    
    // Generar nuevo token
    $urlToken = "https://mingle-sso.inforcloudsuite.com:443/RANSA_PRD/as/token.oauth2";
    $dataToken = [
        "grant_type" => "password",
        "username" => "RANSA_PRD#MOKINRdXbbD00lZK_lHS_yZbVA0LzN00UB4nSN5kWrsbQ-lohV8eqjuau329XpqRFWc7Njaro_GmYJg1Sv9eyQ",
        "password" => "xWU0qhiUWucTns-GQWPLAG9DGwIFpezHmEr1Opslt3FMZ6MZ39jkSjg_2JjRNVmgUkzLPbPvsyOSgGrJE1sAGg",
        "client_id" => "RANSA_PRD~pjoQpgw_5-hD4-u0xG3tlmUWyhrVnq7uwSuvbgo6dZg",
        "client_secret" => "fQSXR0FtOVgGBSBSj9CAcMrQonRZXOAb0sQQLncClxD2AKVnPMKqx2JnPkmRC6AF1nN-_ANZCokwAe6woFnxYQ"
    ];

    $chToken = curl_init($urlToken);
    curl_setopt($chToken, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chToken, CURLOPT_POST, true);
    curl_setopt($chToken, CURLOPT_POSTFIELDS, http_build_query($dataToken));
    curl_setopt($chToken, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($chToken, CURLOPT_TIMEOUT, REQUEST_TIMEOUT_SECONDS);
    
    $responseToken = curl_exec($chToken);
    $httpCode = curl_getinfo($chToken, CURLINFO_HTTP_CODE);
    curl_close($chToken);
    
    if($httpCode === 200){
        $tokenData = json_decode($responseToken, true);
        if(isset($tokenData['access_token'])){
            cacheToken($tokenData['access_token']);
            return $tokenData['access_token'];
        }
    }
    
    return false;
}

function consultarApiInforGET($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, REQUEST_TIMEOUT_SECONDS);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "x-infor-tenantID: RANSA_PRD",
        "Accept: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['http_code' => $httpCode, 'data' => json_decode($response, true)];
}

function consultarApiInforPOST($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, REQUEST_TIMEOUT_SECONDS);
    
    $headers = [
        "Authorization: Bearer $token",
        "x-infor-tenantID: RANSA_PRD",
        "Accept: application/json",
        "Content-Type: application/json"
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['http_code' => $httpCode, 'data' => json_decode($response, true)];
}

// ---------- OBTENER TOKEN ----------
$token = obtenerTokenInfor();

if (!$token) {
    http_response_code(500);
    echo json_encode(["error" => "No se pudo generar token para Infor"]);
    exit;
}

// ---------- PRIMERA CONSULTA: OBTENER INFORMACIÓN DEL PEDIDO ----------
try {
    $urlInfor = "https://mingle-ionapi.inforcloudsuite.com/RANSA_PRD/WM/wmwebservice_rest/RANSA_PRD_RANSA_PRD_SCE_PRD_0_wmwhse3/shipments/externorderkey/$valor";
    $resultInfor = consultarApiInforGET($urlInfor, $token);
    
    if($resultInfor['http_code'] !== 200 || !$resultInfor['data'] || isset($resultInfor['data']['fault']['faultstring'])) {
        $errorMsg = "No se encontraron datos en Infor para esta orden";
        if (isset($resultInfor['data']['fault']['faultstring'])) {
            $errorMsg .= ": " . $resultInfor['data']['fault']['faultstring'];
        }
        echo json_encode(["error" => $errorMsg]);
        exit;
    }
    
    $datosInfor = $resultInfor['data'];
    
    // Validar estructura de datos
    if (!is_array($datosInfor)) {
        echo json_encode(["error" => "Estructura de datos inválida de la API"]);
        exit;
    }
    
    // Validaciones requeridas
    $storerkey = $datosInfor['storerkey'] ?? '';
    $status = $datosInfor['status'] ?? '';
    $orderkey = $datosInfor['orderkey'] ?? '';
    $externorderkey = $datosInfor['externorderkey'] ?? '';
    
    // Validación cruzada de datos
    if (empty($orderkey)) {
        echo json_encode(["error" => "La orden no tiene orderkey válido"]);
        exit;
    }
    
    if (empty($externorderkey)) {
        echo json_encode(["error" => "La orden no tiene externorderkey válido"]);
        exit;
    }
    
    // Verificar si es cliente LIRIS
    if ($storerkey !== 'LIRIS') {
        echo json_encode(["error" => "No corresponde a cliente LIRIS. Cliente: $storerkey"]);
        exit;
    }
    
    // Verificar si el status es 95
    if (!in_array($status, ['95', '92'])) {
        echo json_encode(["error" => "Orden no está expedida por completo. Estado: $status"]);
        exit;
    }
    
    // ---------- PROCESAR orderdetails ----------
    $detallesOrden = [];
    $lineasProcesadas = 0;
    $lineaNumero = 1; // Iniciar contador para # Línea
    
    if (isset($datosInfor['orderdetails']) && is_array($datosInfor['orderdetails'])) {
        foreach ($datosInfor['orderdetails'] as $detalle) {
            $orderlinenumber = $detalle['orderlinenumber'] ?? '';
            
            if (empty($orderlinenumber)) {
                continue;
            }
            
            $sku = $detalle['sku'] ?? '';
            $externlineno = $detalle['externlineno'] ?? '';
            $originalqty = $detalle['originalqty'] ?? 0;
            $shippedqty = $detalle['shippedqty'] ?? 0;
            
            // Usar número de línea secuencial (1, 2, 3...)
            $numeroLineaSecuencial = $lineaNumero;
            
            $detallesOrden[$numeroLineaSecuencial] = [
                "orderlinenumber" => $numeroLineaSecuencial, // Usar número secuencial
                "externorderkey" => $externorderkey,
                "sku" => $sku,
                "externlineno" => $externlineno,
                "originalqty" => $originalqty,
                "shippedqty" => $shippedqty,
                "actualshipdate" => isset($detalle['actualshipdate']) ? formatearFechaInfor($detalle['actualshipdate']) : '',
                "id" => "", // Inicialmente vacío
                "orderlinenumber_original" => $orderlinenumber // Guardar original para consulta
            ];
            
            $lineasProcesadas++;
            $lineaNumero++;
        }
    }
    
    if ($lineasProcesadas == 0) {
        echo json_encode(["error" => "No se encontraron líneas de detalle en la orden"]);
        exit;
    }
    
    // ---------- SEGUNDA CONSULTA: OBTENER ID (LPN) ----------
    $idsEncontrados = 0;
    if (!empty($orderkey)) {
        $urlPickDetails = "https://mingle-ionapi.inforcloudsuite.com/RANSA_PRD/WM/wmwebservice_rest/RANSA_PRD_RANSA_PRD_SCE_PRD_0_wmwhse3/pickdetails/$orderkey/findpicksbyorderkey";
        
        $resultPickDetails = consultarApiInforPOST($urlPickDetails, $token);
        
        if($resultPickDetails['http_code'] === 200 && $resultPickDetails['data']) {
            $datosPick = $resultPickDetails['data'];
            
            if (is_array($datosPick)) {
                foreach ($datosPick as $item) {
                    $orderlinenumber = $item['orderlinenumber'] ?? '';
                    $id = $item['id'] ?? '';
                    
                    if (!empty($id) && $id !== ' ' && $id !== '  ') {
                        // Buscar en los detalles por el orderlinenumber original
                        foreach ($detallesOrden as $key => $detalle) {
                            if ($detalle['orderlinenumber_original'] == $orderlinenumber) {
                                $detallesOrden[$key]['id'] = $id;
                                $idsEncontrados++;
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // ---------- APLICAR LPN PREDETERMINADO A LÍNEAS SIN LPN ----------
    $lineasConLPNPredeterminado = 0;
    foreach ($detallesOrden as $key => $detalle) {
        if (empty($detalle['id'])) {
            $detallesOrden[$key]['id'] = LPN_PREDETERMINADO;
            $lineasConLPNPredeterminado++;
        }
    }
    
    // ---------- PREPARAR DETALLES FINALES ----------
    $detallesFinal = [];
    foreach ($detallesOrden as $numeroLineaSecuencial => $detalle) {
        if (empty($detalle['sku']) || empty($detalle['externlineno'])) {
            continue;
        }
        
        $detallesFinal[] = [
            "orderlinenumber" => $detalle['orderlinenumber'], // Número secuencial (1, 2, 3...)
            "externorderkey" => $detalle['externorderkey'],
            "sku" => $detalle['sku'],
            "externlineno" => $detalle['externlineno'],
            "originalqty" => $detalle['originalqty'],
            "shippedqty" => $detalle['shippedqty'],
            "id" => $detalle['id'],
            "actualshipdate" => $detalle['actualshipdate']
        ];
    }
    
    // ---------- PREPARAR RESPUESTA ----------
    $resultadosCompletos = [
        'informacion_pedido' => [
            "orderkey" => $orderkey,
            "externorderkey" => $externorderkey,
            "storerkey" => $storerkey,
            "status" => $status,
            "adddate" => isset($datosInfor['adddate']) ? formatearFechaInfor($datosInfor['adddate']) : '',
            "ccompany" => $datosInfor['ccompany'] ?? '',
            "ccity" => $datosInfor['ccity'] ?? '',
            "type" => $datosInfor['type'] ?? '',
            "total_lineas" => $lineasProcesadas,
            "lineas_con_id" => $idsEncontrados,
            "lineas_con_lpn_predeterminado" => $lineasConLPNPredeterminado
        ],
        'detalle_pedido' => $detallesFinal,
        'error' => null
    ];
    
    if (empty($detallesFinal)) {
        $resultadosCompletos['error'] = "No se encontraron detalles del pedido válidos";
    }
    
    echo json_encode($resultadosCompletos);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno del servidor: " . $e->getMessage()]);
    exit;
}
?>