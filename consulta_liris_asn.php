<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// ---------- CONFIGURACIONES DE SEGURIDAD ----------
define('MAX_REQUESTS_PER_MINUTE', 5);
define('REQUEST_TIMEOUT_SECONDS', 30);
define('API_WAIT_SECONDS', 10);
define('TOKEN_CACHE_TIME', 300);
define('DIAS_VENCIMIENTO_MAXIMO', 15); // Días máximos para vencimiento

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
        
        if (isset($rateData['last_request']) && 
            ($currentTime - $rateData['last_request']) < API_WAIT_SECONDS) {
            $waitTime = API_WAIT_SECONDS - ($currentTime - $rateData['last_request']);
            return [
                'allowed' => false,
                'wait_time' => $waitTime,
                'message' => "Debe esperar {$waitTime} segundos antes de realizar otra consulta"
            ];
        }
        
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

// Limpiar cache viejo
function cleanupOldCache() {
    $cacheDir = sys_get_temp_dir() . '/liris_cache/';
    if (!is_dir($cacheDir)) return;
    
    $files = glob($cacheDir . 'rate_*.json');
    $currentTime = time();
    $oneDayAgo = $currentTime - 86400;
    
    foreach ($files as $file) {
        if (filemtime($file) < $oneDayAgo) {
            unlink($file);
        }
    }
}

// ---------- VALIDACIÓN INICIAL ----------
if(!isset($_GET['valor']) || empty($_GET['valor'])){
    echo json_encode(["error" => "No se proporcionó número de Recepción"]);
    exit;
}

// Sanitizar input
$valor = trim($_GET['valor']);
if(!preg_match('/^[a-zA-Z0-9_\-]+$/', $valor)){
    echo json_encode(["error" => "Número de Recepción inválido"]);
    exit;
}

// Verificar rate limiting
$clientIP = getClientIP();
$rateCheck = checkRateLimit($clientIP);

if(!$rateCheck['allowed']){
    http_response_code(429);
    echo json_encode([
        "error" => $rateCheck['message'],
        "wait_time" => $rateCheck['wait_time']
    ]);
    exit;
}

// Limpiar cache viejo
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

// Formatear fecha de vencimiento
function formatearFechaVencimiento($fechaStr) {
    if(!$fechaStr || $fechaStr === '0000-00-00' || $fechaStr === 'N/A' || $fechaStr === '') return "N/A";
    
    try {
        $fecha = DateTime::createFromFormat(DateTime::ISO8601, $fechaStr);
        
        if (!$fecha) {
            $fecha = DateTime::createFromFormat('Y-m-d', $fechaStr);
        }
        
        if (!$fecha) {
            $fecha = new DateTime($fechaStr);
        }
        
        if (!$fecha) {
            return "N/A";
        }
        
        return $fecha->format('d/m/Y');
        
    } catch (Exception $e) {
        return "N/A";
    }
}

// Formatear precio (lottable09)
function formatearPrecio($precioStr) {
    if(!$precioStr || $precioStr === '' || $precioStr === ' ' || $precioStr === null) {
        return "0.00";
    }
    
    // Limpiar espacios
    $precioStr = trim($precioStr);
    
    // Convertir a float y formatear con 2 decimales
    $precioFloat = floatval($precioStr);
    return number_format($precioFloat, 2, '.', '');
}

// Verificar si la fecha de vencimiento es válida (no mayor a 15 días desde hoy)
function verificarVencimientoValido($fechaStr) {
    if(!$fechaStr || $fechaStr === 'N/A') return false;
    
    try {
        // Intentar diferentes formatos
        $fechaVencimiento = null;
        
        // Formato ISO (2025-03-24T11:00:00.000+00:00)
        if (strpos($fechaStr, 'T') !== false) {
            $fechaVencimiento = DateTime::createFromFormat(DateTime::ISO8601, $fechaStr);
        }
        
        // Formato YYYY-MM-DD
        if (!$fechaVencimiento && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaStr)) {
            $fechaVencimiento = DateTime::createFromFormat('Y-m-d', $fechaStr);
        }
        
        // Formato DD/MM/YYYY (ya formateado)
        if (!$fechaVencimiento && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fechaStr)) {
            $fechaVencimiento = DateTime::createFromFormat('d/m/Y', $fechaStr);
        }
        
        if (!$fechaVencimiento) {
            return false;
        }
        
        // Fecha actual
        $fechaActual = new DateTime();
        
        // Calcular diferencia en días
        $diferencia = $fechaActual->diff($fechaVencimiento);
        $diasDiferencia = (int)$diferencia->format('%r%a'); // %r para signo
        
        // Si la fecha de vencimiento es en el pasado (días negativos) o mayor a 15 días en el futuro
        return ($diasDiferencia >= 0 && $diasDiferencia <= DIAS_VENCIMIENTO_MAXIMO);
        
    } catch (Exception $e) {
        return false;
    }
}

// Función para obtener texto del estado
function obtenerTextoEstado($status) {
    $estados = [
        '11' => 'Cerrado',
        '15' => 'Verificado y cerrado'
    ];
    
    return $estados[$status] ?? "Estado $status";
}

// Función para quitar ceros iniciales de cualquier campo numérico
function limpiarCerosIniciales($valor) {
    if (empty($valor)) return '0';
    
    $valor = trim($valor);
    // Quitar ceros iniciales
    $numLimpio = ltrim($valor, '0');
    // Si queda vacío, poner "0"
    return $numLimpio === '' ? '0' : $numLimpio;
}

// Función especial para receiptlinenumber (puede tener múltiples números)
function limpiarReceiptlinenumber($receiptlinenumber) {
    if (empty($receiptlinenumber)) return '0';
    
    // Si hay múltiples números separados por coma (1,21,35)
    if (strpos($receiptlinenumber, ',') !== false) {
        $numeros = explode(',', $receiptlinenumber);
        $numerosLimpios = array_map('limpiarCerosIniciales', $numeros);
        return implode(',', $numerosLimpios);
    } else {
        // Si es un solo número
        return limpiarCerosIniciales($receiptlinenumber);
    }
}

function obtenerTokenInfor() {
    $cachedToken = getCachedToken();
    if($cachedToken !== false){
        return $cachedToken;
    }
    
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

// ---------- OBTENER TOKEN ----------
$token = obtenerTokenInfor();

if (!$token) {
    http_response_code(500);
    echo json_encode(["error" => "No se pudo generar token para Infor"]);
    exit;
}

// ---------- CONSULTA PRINCIPAL ----------
try {
    $urlInfor = "https://mingle-ionapi.inforcloudsuite.com/RANSA_PRD/WM/wmwebservice_rest/RANSA_PRD_RANSA_PRD_SCE_PRD_0_wmwhse3/advancedshipnotice/$valor";
    $resultInfor = consultarApiInforGET($urlInfor, $token);
    
    if($resultInfor['http_code'] !== 200 || !$resultInfor['data'] || isset($resultInfor['data']['fault']['faultstring'])) {
        $errorMsg = "No se encontraron datos en Infor para este número";
        if (isset($resultInfor['data']['fault']['faultstring'])) {
            $errorMsg .= ": " . $resultInfor['data']['fault']['faultstring'];
        }
        echo json_encode(["error" => $errorMsg]);
        exit;
    }
    
    $datosInfor = $resultInfor['data'];
    
    if (!is_array($datosInfor)) {
        echo json_encode(["error" => "Estructura de datos inválida de la API"]);
        exit;
    }
    
    // Campos principales
    $receiptkey = $datosInfor['receiptkey'] ?? '';
    $externreceiptkey = $datosInfor['externreceiptkey'] ?? '';
    $storerkey = $datosInfor['storerkey'] ?? '';
    $status = $datosInfor['status'] ?? '';
    
    if (empty($receiptkey) && empty($externreceiptkey)) {
        echo json_encode(["error" => "La recepción no tiene número válido"]);
        exit;
    }
    
    if ($storerkey !== 'LIRIS') {
        echo json_encode(["error" => "No corresponde a cliente LIRIS. Cliente: $storerkey"]);
        exit;
    }
    
    if (!in_array($status, ['11', '15'])) {
        $textoEstado = obtenerTextoEstado($status);
        echo json_encode(["error" => "La recepción no tiene un estado válido para consulta. Estado: $textoEstado"]);
        exit;
    }
    
    // ---------- PROCESAR DETALLES ----------
    $detallesRecepcion = [];
    $lineasProcesadas = 0;
    $lineasVencimientoInvalido = 0;
    $lineasConsolidadas = []; // Para consolidar por externlineno
    
    // Buscar receiptdetails
    if (isset($datosInfor['receiptdetails']) && is_array($datosInfor['receiptdetails'])) {
        foreach ($datosInfor['receiptdetails'] as $detalle) {
            $receiptlinenumber = $detalle['receiptlinenumber'] ?? '';
            $externlineno = $detalle['externlineno'] ?? '';
            
            if (empty($receiptlinenumber) || empty($externlineno)) {
                continue;
            }
            
            $sku = $detalle['sku'] ?? '';
            $qtyexpected = floatval($detalle['qtyexpected'] ?? 0);
            $qtyreceived = floatval($detalle['qtyreceived'] ?? 0);
            $lottable05 = $detalle['lottable05'] ?? '';
            $tolot = $detalle['tolot'] ?? '';
            $lottable09 = $detalle['lottable09'] ?? ''; // PRECIO
            
            // Usar el externlineno tal como viene de la API (ej: "0000446")
            // Luego lo limpiaremos cuando presentemos los datos
            $externlinenoOriginal = $externlineno;
            
            // Consolidar por externlineno
            if (!isset($lineasConsolidadas[$externlinenoOriginal])) {
                // Primera vez que vemos esta línea externa
                $lineasConsolidadas[$externlinenoOriginal] = [
                    'receiptlinenumbers' => [$receiptlinenumber],
                    'receiptkey' => $receiptkey,
                    'externreceiptkey' => $externreceiptkey,
                    'sku' => $sku,
                    'externlineno' => $externlinenoOriginal, // Guardamos el original
                    'qtyexpected' => $qtyexpected,
                    'qtyreceived' => $qtyreceived,
                    'lottable05' => $lottable05,
                    'tolot' => $tolot,
                    'lottable09' => $lottable09, // PRECIO
                    'count' => 1
                ];
            } else {
                // Ya existe esta línea externa, sumar cantidades
                $lineasConsolidadas[$externlinenoOriginal]['qtyexpected'] += $qtyexpected;
                $lineasConsolidadas[$externlinenoOriginal]['qtyreceived'] += $qtyreceived;
                $lineasConsolidadas[$externlinenoOriginal]['receiptlinenumbers'][] = $receiptlinenumber;
                $lineasConsolidadas[$externlinenoOriginal]['count']++;
                
                // Tomar el lottable05 del registro que tenga valor (si el actual es vacío/null)
                if ((empty($lineasConsolidadas[$externlinenoOriginal]['lottable05']) || $lineasConsolidadas[$externlinenoOriginal]['lottable05'] === 'N/A') 
                    && (!empty($lottable05) && $lottable05 !== 'N/A')) {
                    $lineasConsolidadas[$externlinenoOriginal]['lottable05'] = $lottable05;
                }
                
                // Tomar el tolot del registro que tenga valor (si el actual es vacío/null)
                if ((empty($lineasConsolidadas[$externlinenoOriginal]['tolot']) || $lineasConsolidadas[$externlinenoOriginal]['tolot'] === 'N/A') 
                    && (!empty($tolot) && $tolot !== 'N/A')) {
                    $lineasConsolidadas[$externlinenoOriginal]['tolot'] = $tolot;
                }
                
                // Tomar el precio (lottable09) del registro que tenga valor (si el actual es vacío/null)
                if ((empty($lineasConsolidadas[$externlinenoOriginal]['lottable09']) || $lineasConsolidadas[$externlinenoOriginal]['lottable09'] === '' || $lineasConsolidadas[$externlinenoOriginal]['lottable09'] === ' ') 
                    && (!empty($lottable09) && $lottable09 !== '' && $lottable09 !== ' ')) {
                    $lineasConsolidadas[$externlinenoOriginal]['lottable09'] = $lottable09;
                }
            }
            
            $lineasProcesadas++;
        }
        
        // Convertir las líneas consolidadas a formato final
        foreach ($lineasConsolidadas as $externlinenoOriginal => $linea) {
            $lottable05 = $linea['lottable05'];
            $fechaVencimiento = formatearFechaVencimiento($lottable05);
            $vencimientoValido = verificarVencimientoValido($lottable05);
            $precio = formatearPrecio($linea['lottable09']); // Formatear precio
            
            if (!$vencimientoValido && $fechaVencimiento !== 'N/A') {
                $lineasVencimientoInvalido++;
            }
            
            // Crear un receiptlinenumber combinado si hay múltiples
            $receiptlinenumberFinal = $linea['receiptlinenumbers'][0];
            if (count($linea['receiptlinenumbers']) > 1) {
                $receiptlinenumberFinal = implode(',', $linea['receiptlinenumbers']);
            }
            
            // Limpiar ceros iniciales del receiptlinenumber
            $receiptlinenumberFinal = limpiarReceiptlinenumber($receiptlinenumberFinal);
            
            // Limpiar ceros iniciales del externlineno para mostrar solo el número entero
            $externlinenoLimpio = limpiarCerosIniciales($externlinenoOriginal);
            
            $detallesRecepcion[] = [
                "receiptlinenumber" => $receiptlinenumberFinal,
                "receiptkey" => $linea['receiptkey'],
                "externreceiptkey" => $linea['externreceiptkey'],
                "sku" => $linea['sku'],
                "externlineno" => $externlinenoLimpio, // Usamos el limpiado
                "externlineno_original" => $externlinenoOriginal, // Guardamos el original por si acaso
                "qtyexpected" => $linea['qtyexpected'],
                "qtyreceived" => $linea['qtyreceived'],
                "lottable05" => $linea['lottable05'],
                "fecha_vencimiento" => $fechaVencimiento,
                "vencimiento_valido" => $vencimientoValido,
                "tolot" => $linea['tolot'],
                "precio" => $precio // NUEVO: Campo precio
            ];
        }
    }
    // Buscar asndetails (si no hay receiptdetails)
    else if (isset($datosInfor['asndetails']) && is_array($datosInfor['asndetails'])) {
        $lineasConsolidadas = []; // Reiniciar para asndetails
        
        foreach ($datosInfor['asndetails'] as $detalle) {
            $asnlinenumber = $detalle['asnlinenumber'] ?? '';
            $externlineno = $detalle['externlineno'] ?? '';
            
            if (empty($asnlinenumber) || empty($externlineno)) {
                continue;
            }
            
            $sku = $detalle['sku'] ?? '';
            $qtyexpected = floatval($detalle['qtyexpected'] ?? 0);
            $qtyreceived = floatval($detalle['qtyreceived'] ?? 0);
            $lottable05 = $detalle['lottable05'] ?? '';
            $tolot = $detalle['tolot'] ?? '';
            $lottable09 = $detalle['lottable09'] ?? ''; // PRECIO
            
            // Usar el externlineno tal como viene de la API
            $externlinenoOriginal = $externlineno;
            
            // Consolidar por externlineno
            if (!isset($lineasConsolidadas[$externlinenoOriginal])) {
                $lineasConsolidadas[$externlinenoOriginal] = [
                    'asnlinenumbers' => [$asnlinenumber],
                    'receiptkey' => $datosInfor['asnnumber'] ?? $receiptkey,
                    'externreceiptkey' => $externreceiptkey,
                    'sku' => $sku,
                    'externlineno' => $externlinenoOriginal, // Guardamos el original
                    'qtyexpected' => $qtyexpected,
                    'qtyreceived' => $qtyreceived,
                    'lottable05' => $lottable05,
                    'tolot' => $tolot,
                    'lottable09' => $lottable09, // PRECIO
                    'count' => 1
                ];
            } else {
                $lineasConsolidadas[$externlinenoOriginal]['qtyexpected'] += $qtyexpected;
                $lineasConsolidadas[$externlinenoOriginal]['qtyreceived'] += $qtyreceived;
                $lineasConsolidadas[$externlinenoOriginal]['asnlinenumbers'][] = $asnlinenumber;
                $lineasConsolidadas[$externlinenoOriginal]['count']++;
                
                if ((empty($lineasConsolidadas[$externlinenoOriginal]['lottable05']) || $lineasConsolidadas[$externlinenoOriginal]['lottable05'] === 'N/A') 
                    && (!empty($lottable05) && $lottable05 !== 'N/A')) {
                    $lineasConsolidadas[$externlinenoOriginal]['lottable05'] = $lottable05;
                }
                
                if ((empty($lineasConsolidadas[$externlinenoOriginal]['tolot']) || $lineasConsolidadas[$externlinenoOriginal]['tolot'] === 'N/A') 
                    && (!empty($tolot) && $tolot !== 'N/A')) {
                    $lineasConsolidadas[$externlinenoOriginal]['tolot'] = $tolot;
                }
                
                // Tomar el precio (lottable09) del registro que tenga valor
                if ((empty($lineasConsolidadas[$externlinenoOriginal]['lottable09']) || $lineasConsolidadas[$externlinenoOriginal]['lottable09'] === '' || $lineasConsolidadas[$externlinenoOriginal]['lottable09'] === ' ') 
                    && (!empty($lottable09) && $lottable09 !== '' && $lottable09 !== ' ')) {
                    $lineasConsolidadas[$externlinenoOriginal]['lottable09'] = $lottable09;
                }
            }
            
            $lineasProcesadas++;
        }
        
        // Convertir las líneas consolidadas a formato final
        foreach ($lineasConsolidadas as $externlinenoOriginal => $linea) {
            $lottable05 = $linea['lottable05'];
            $fechaVencimiento = formatearFechaVencimiento($lottable05);
            $vencimientoValido = verificarVencimientoValido($lottable05);
            $precio = formatearPrecio($linea['lottable09']); // Formatear precio
            
            if (!$vencimientoValido && $fechaVencimiento !== 'N/A') {
                $lineasVencimientoInvalido++;
            }
            
            // Crear un asnlinenumber combinado si hay múltiples
            $asnlinenumberFinal = $linea['asnlinenumbers'][0];
            if (count($linea['asnlinenumbers']) > 1) {
                $asnlinenumberFinal = implode(',', $linea['asnlinenumbers']);
            }
            
            // Limpiar ceros iniciales del asnlinenumber
            $asnlinenumberFinal = limpiarReceiptlinenumber($asnlinenumberFinal);
            
            // Limpiar ceros iniciales del externlineno para mostrar solo el número entero
            $externlinenoLimpio = limpiarCerosIniciales($externlinenoOriginal);
            
            $detallesRecepcion[] = [
                "receiptlinenumber" => $asnlinenumberFinal,
                "receiptkey" => $linea['receiptkey'],
                "externreceiptkey" => $linea['externreceiptkey'],
                "sku" => $linea['sku'],
                "externlineno" => $externlinenoLimpio, // Usamos el limpiado
                "externlineno_original" => $externlinenoOriginal, // Guardamos el original por si acaso
                "qtyexpected" => $linea['qtyexpected'],
                "qtyreceived" => $linea['qtyreceived'],
                "lottable05" => $linea['lottable05'],
                "fecha_vencimiento" => $fechaVencimiento,
                "vencimiento_valido" => $vencimientoValido,
                "tolot" => $linea['tolot'],
                "precio" => $precio // NUEVO: Campo precio
            ];
        }
    }
    
    // Si no hay detalles en receiptdetails ni asndetails, buscar en receipt
    if (empty($detallesRecepcion)) {
        $urlReceipt = "https://mingle-ionapi.inforcloudsuite.com/RANSA_PRD/WM/wmwebservice_rest/RANSA_PRD_RANSA_PRD_SCE_PRD_0_wmwhse3/receipts/$valor";
        $resultReceipt = consultarApiInforGET($urlReceipt, $token);
        
        if($resultReceipt['http_code'] === 200 && $resultReceipt['data'] && isset($resultReceipt['data']['receiptdetails'])) {
            $datosReceipt = $resultReceipt['data'];
            $lineasConsolidadas = []; // Reiniciar para receipt
            
            foreach ($datosReceipt['receiptdetails'] as $detalle) {
                $receiptlinenumber = $detalle['receiptlinenumber'] ?? '';
                $externlineno = $detalle['externlineno'] ?? '';
                
                if (empty($receiptlinenumber) || empty($externlineno)) {
                    continue;
                }
                
                $sku = $detalle['sku'] ?? '';
                $qtyexpected = floatval($detalle['qtyexpected'] ?? 0);
                $qtyreceived = floatval($detalle['qtyreceived'] ?? 0);
                $lottable05 = $detalle['lottable05'] ?? '';
                $tolot = $detalle['tolot'] ?? '';
                $lottable09 = $detalle['lottable09'] ?? ''; // PRECIO
                
                // Usar el externlineno tal como viene de la API
                $externlinenoOriginal = $externlineno;
                
                // Consolidar por externlineno
                if (!isset($lineasConsolidadas[$externlinenoOriginal])) {
                    $lineasConsolidadas[$externlinenoOriginal] = [
                        'receiptlinenumbers' => [$receiptlinenumber],
                        'receiptkey' => $datosReceipt['receiptkey'] ?? $receiptkey,
                        'externreceiptkey' => $datosReceipt['externreceiptkey'] ?? $externreceiptkey,
                        'sku' => $sku,
                        'externlineno' => $externlinenoOriginal, // Guardamos el original
                        'qtyexpected' => $qtyexpected,
                        'qtyreceived' => $qtyreceived,
                        'lottable05' => $lottable05,
                        'tolot' => $tolot,
                        'lottable09' => $lottable09, // PRECIO
                        'count' => 1
                    ];
                } else {
                    $lineasConsolidadas[$externlinenoOriginal]['qtyexpected'] += $qtyexpected;
                    $lineasConsolidadas[$externlinenoOriginal]['qtyreceived'] += $qtyreceived;
                    $lineasConsolidadas[$externlinenoOriginal]['receiptlinenumbers'][] = $receiptlinenumber;
                    $lineasConsolidadas[$externlinenoOriginal]['count']++;
                    
                    if ((empty($lineasConsolidadas[$externlinenoOriginal]['lottable05']) || $lineasConsolidadas[$externlinenoOriginal]['lottable05'] === 'N/A') 
                        && (!empty($lottable05) && $lottable05 !== 'N/A')) {
                        $lineasConsolidadas[$externlinenoOriginal]['lottable05'] = $lottable05;
                    }
                    
                    if ((empty($lineasConsolidadas[$externlinenoOriginal]['tolot']) || $lineasConsolidadas[$externlinenoOriginal]['tolot'] === 'N/A') 
                        && (!empty($tolot) && $tolot !== 'N/A')) {
                        $lineasConsolidadas[$externlinenoOriginal]['tolot'] = $tolot;
                    }
                    
                    // Tomar el precio (lottable09) del registro que tenga valor
                    if ((empty($lineasConsolidadas[$externlinenoOriginal]['lottable09']) || $lineasConsolidadas[$externlinenoOriginal]['lottable09'] === '' || $lineasConsolidadas[$externlinenoOriginal]['lottable09'] === ' ') 
                        && (!empty($lottable09) && $lottable09 !== '' && $lottable09 !== ' ')) {
                        $lineasConsolidadas[$externlinenoOriginal]['lottable09'] = $lottable09;
                    }
                }
                
                $lineasProcesadas++;
            }
            
            // Convertir las líneas consolidadas a formato final
            foreach ($lineasConsolidadas as $externlinenoOriginal => $linea) {
                $lottable05 = $linea['lottable05'];
                $fechaVencimiento = formatearFechaVencimiento($lottable05);
                $vencimientoValido = verificarVencimientoValido($lottable05);
                $precio = formatearPrecio($linea['lottable09']); // Formatear precio
                
                if (!$vencimientoValido && $fechaVencimiento !== 'N/A') {
                    $lineasVencimientoInvalido++;
                }
                
                // Crear un receiptlinenumber combinado si hay múltiples
                $receiptlinenumberFinal = $linea['receiptlinenumbers'][0];
                if (count($linea['receiptlinenumbers']) > 1) {
                    $receiptlinenumberFinal = implode(',', $linea['receiptlinenumbers']);
                }
                
                // Limpiar ceros iniciales del receiptlinenumber
                $receiptlinenumberFinal = limpiarReceiptlinenumber($receiptlinenumberFinal);
                
                // Limpiar ceros iniciales del externlineno para mostrar solo el número entero
                $externlinenoLimpio = limpiarCerosIniciales($externlinenoOriginal);
                
                $detallesRecepcion[] = [
                    "receiptlinenumber" => $receiptlinenumberFinal,
                    "receiptkey" => $linea['receiptkey'],
                    "externreceiptkey" => $linea['externreceiptkey'],
                    "sku" => $linea['sku'],
                    "externlineno" => $externlinenoLimpio, // Usamos el limpiado
                    "externlineno_original" => $externlinenoOriginal, // Guardamos el original por si acaso
                    "qtyexpected" => $linea['qtyexpected'],
                    "qtyreceived" => $linea['qtyreceived'],
                    "lottable05" => $linea['lottable05'],
                    "fecha_vencimiento" => $fechaVencimiento,
                    "vencimiento_valido" => $vencimientoValido,
                    "tolot" => $linea['tolot'],
                    "precio" => $precio // NUEVO: Campo precio
                ];
            }
        } else {
            echo json_encode(["error" => "No se encontraron líneas de detalle en la recepción"]);
            exit;
        }
    }
    
    // Ordenar los detalles por externlineno (numéricamente)
    usort($detallesRecepcion, function($a, $b) {
        $aNum = intval($a['externlineno']);
        $bNum = intval($b['externlineno']);
        if ($aNum === $bNum) {
            // Ordenar por receiptlinenumber (numéricamente el primer número)
            $aRec = intval(explode(',', $a['receiptlinenumber'])[0]);
            $bRec = intval(explode(',', $b['receiptlinenumber'])[0]);
            return $aRec - $bRec;
        }
        return $aNum - $bNum;
    });
    
    // ---------- PREPARAR DETALLES FINALES ----------
    $detallesFinal = [];
    $lineasConsolidadasFinal = 0; // Contador de líneas consolidadas
    
    foreach ($detallesRecepcion as $detalle) {
        if (empty($detalle['sku']) || empty($detalle['externlineno'])) {
            continue;
        }
        
        // Contar si esta línea fue consolidada (tiene múltiples receiptlinenumbers)
        $receiptlinenumber = $detalle['receiptlinenumber'];
        if (strpos($receiptlinenumber, ',') !== false) {
            $lineasConsolidadasFinal++;
        }
        
        // Ahora externlineno ya está limpio (sin ceros iniciales)
        $detallesFinal[] = [
            "receiptlinenumber" => $detalle['receiptlinenumber'],
            "receiptkey" => $detalle['receiptkey'],
            "externreceiptkey" => $detalle['externreceiptkey'],
            "sku" => $detalle['sku'],
            "externlineno" => $detalle['externlineno'], // Ya está limpio
            "qtyexpected" => $detalle['qtyexpected'],
            "qtyreceived" => $detalle['qtyreceived'],
            "lottable05" => $detalle['lottable05'],
            "fecha_vencimiento" => $detalle['fecha_vencimiento'],
            "vencimiento_valido" => $detalle['vencimiento_valido'],
            "tolot" => $detalle['tolot'],
            "precio" => $detalle['precio'] // NUEVO: Campo precio
        ];
    }
    
    // ---------- PREPARAR RESPUESTA ----------
    $resultadosCompletos = [
        'informacion_recepcion' => [
            "receiptkey" => $receiptkey,
            "externreceiptkey" => $externreceiptkey,
            "storerkey" => $storerkey,
            "status" => $status,
            "status_texto" => obtenerTextoEstado($status),
            "adddate" => isset($datosInfor['adddate']) ? formatearFechaInfor($datosInfor['adddate']) : '',
            "closeddate" => isset($datosInfor['closeddate']) ? formatearFechaInfor($datosInfor['closeddate']) : '',
            "editdate" => isset($datosInfor['editdate']) ? formatearFechaInfor($datosInfor['editdate']) : '',
            "total_lineas" => count($detallesFinal),
            "lineas_vencimiento_invalido" => $lineasVencimientoInvalido,
            "lineas_consolidadas" => $lineasConsolidadasFinal // Información de líneas consolidadas
        ],
        'detalle_recepcion' => $detallesFinal,
        'error' => null
    ];
    
    if (empty($detallesFinal)) {
        $resultadosCompletos['error'] = "No se encontraron detalles válidos";
    }
    
    echo json_encode($resultadosCompletos);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno del servidor: " . $e->getMessage()]);
    exit;
}
?>