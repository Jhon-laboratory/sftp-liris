<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    // No está logueado, redirigir al login
    header('Location: https://ransa-seguro.com/login/');
    exit;
}

// Si llegó aquí, el usuario está logueado
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta Pedidos LIRIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SheetJS -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
    <!-- CSS Global -->
    <link rel="stylesheet" href="../assets/css/estilo.css">
</head>
<body>
    <!-- Botones flotantes -->
    <div class="floating-buttons">
        <button class="floating-button left" onclick="volverAlInicio()">
            <i class="fas fa-arrow-left"></i>
            <span>Volver al inicio</span>
        </button>
        <button class="floating-button right" onclick="irAVerificadasCerradas()">
            <i class="fas fa-check-circle"></i>
            <span>ASN Recepciones</span>
        </button>
    </div>

    <!-- Indicador SFTP -->
    <div class="sftp-indicator" id="sftpIndicator">
        <i id="sftpIcon"></i>
        <span id="sftpMessage"></span>
        <button class="close-btn" onclick="cerrarIndicadorSFTP()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Botones flotantes verticales -->
    <div class="floating-actions" id="floatingActions">
        <div class="action-item" id="actionEnviarInterfaz" style="display: none;">
            <button class="action-button primary" onclick="enviarDatosInterfaz()">
                <i class="fas fa-paper-plane"></i>
                <div class="tooltip">Enviar datos a Interfaz SFTP</div>
            </button>
            <div class="action-label primary">Enviar Integración</div>
        </div>
        
        <div class="action-item">
            <button class="action-button secondary" onclick="descargarExcel()">
                <i class="fas fa-file-excel"></i>
                <div class="tooltip">Descargar Excel (XLSX)</div>
            </button>
            <div class="action-label secondary">Descargar Excel</div>
        </div>
        
        <div class="action-item">
            <button class="action-button tertiary" onclick="descargarTXT()">
                <i class="fas fa-file-alt"></i>
                <div class="tooltip">Descargar TXT formateado</div>
            </button>
            <div class="action-label tertiary">Descargar TXT</div>
        </div>
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header-top">
            <div class="logo-container">
                <img src="https://tse2.mm.bing.net/th/id/OIP.Ckl7mNDKlUqm6056On3FIwAAAA?w=380&h=125&rs=1&pid=ImgDetMain&o=7&rm=3" 
                     class="logo" 
                     alt="Logo Servientrega"
                     loading="lazy">
            </div>
            
            <div class="title-section">
                <h1 class="title">Consulta Pedidos LIRIS</h1>
                <p class="subtitle">Sistema de consulta y envío SFTP</p>
            </div>
            
            <div class="search-bar">
                <input type="text" 
                       id="numeroOrden" 
                       class="search-input" 
                       placeholder="🔍 Busque por Orden Externa"
                       required>
                <button id="btnBuscar" class="search-btn">
                    <i class="fas fa-search"></i> Consultar
                </button>
            </div>
        </div>

        <!-- Mensajes -->
        <div class="message-container" id="messageContainer"></div>

        <!-- Resultados -->
        <div class="result-section" id="resultSection">
            <!-- Tarjetas de totales -->
            <h2 class="section-title">
                <i class="fas fa-chart-bar"></i> Resumen del Pedido
            </h2>
            
            <div class="stats-cards" id="statsCards">
                <div class="stat-card">
                    <span class="stat-label"><i class="fas fa-box"></i> Total Cantidad Original</span>
                    <span class="stat-value" id="totalOriginal">0</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label"><i class="fas fa-shipping-fast"></i> Total Cantidad Enviada</span>
                    <span class="stat-value" id="totalEnviado">0</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label"><i class="fas fa-list"></i> Total Líneas</span>
                    <span class="stat-value" id="totalLineas">0</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label"><i class="fas fa-barcode"></i> Líneas con LPN</span>
                    <span class="stat-value" id="lineasConLPN">0</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label"><i class="fas fa-exclamation-triangle"></i> Líneas con Observaciones</span>
                    <span class="stat-value" id="lineasConProblemas">0</span>
                </div>
            </div>

            <!-- Información del pedido -->
            <h2 class="section-title">
                <i class="fas fa-info-circle"></i> Información del Pedido
            </h2>
            
            <div class="pedido-header" id="pedidoHeader"></div>

            <!-- Detalle del pedido con filtro -->
            <h2 class="section-title" style="display: flex; align-items: center; justify-content: space-between;">
                <span><i class="fas fa-table"></i> Detalle del Pedido</span>
                <button id="btnFiltroObservaciones" class="search-btn" onclick="toggleFiltroObservaciones()">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="btnFiltroTexto">Ver solo observaciones</span>
                    <span id="contadorObservaciones">0</span>
                </button>
            </h2>
            
            <div class="detalle-table-container">
                <table class="detalle-table" id="detalleTable">
                    <thead>
                        <tr>
                            <th># Línea</th>
                            <th>Orden Externa</th>
                            <th>SKU</th>
                            <th>Línea Externa</th>
                            <th>Cantidad Original</th>
                            <th>Cantidad Enviada</th>
                            <th>LPN (ID)</th>
                            <th>Fecha Despacho</th>
                        </tr>
                    </thead>
                    <tbody id="detalleBody"></tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Sistema de consulta Ransa &copy; 2026 </p>
            <p style="font-size: 12px; margin-top: 5px; color: #95a5a6;">
                <i class="fas fa-shield-alt"></i> Sistema seguro y confiable
                
            </p>
        </div>
    </div>

    <!-- JavaScript específico de despachos1 -->
    <script src="../assets/js/despachos1.js"></script>
</body>
</html>