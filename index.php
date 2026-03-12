<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    // No está logueado, redirigir al login
    header('Location: http://9.234.192.192/login/');
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIRIS - Sistema de Consultas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Cargar CSS global -->
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body class="index-page">
    <div class="container">
        <!-- Header con logos -->
        <div class="header-top animate-in">
            <div class="logo-container">
                <img src="https://tse2.mm.bing.net/th/id/OIP.Ckl7mNDKlUqm6056On3FIwAAAA?w=380&h=125&rs=1&pid=ImgDetMain&o=7&rm=3" 
                     class="logo" 
                     alt="Logo Servientrega"
                     loading="lazy">
                <!-- <img src="https://crepier.com.ec/media/logo/default/LOGO-WEB.png"  -->
                 <!--     class="logo" -->
                <!--      alt="Logo Cliente"-->
                 <!--     loading="lazy">-->
            </div>
            
            <div class="title-section">
                <h1 class="title">Sistema LIRIS</h1>
                <p class="subtitle">Plataforma Integrada de Gestión y Consultas</p>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="main-container">
            <!-- Grid de opciones -->
            <div class="options-grid">
                <!-- Opción 1: Órdenes de Compra -->
                <div class="option-card animate-in delay-1" id="ordenesCompra" onclick="navegarA('ordenes-compra')">
                    <div class="option-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h2 class="option-title">Recepciones/ASN</h2>
                    <p class="option-description">
                        Consulta y gestión de órdenes de compra del sistema LIRIS
                    </p>
                </div>

                <!-- Opción 2: Despachos -->
                <div class="option-card animate-in delay-2" id="despachos" onclick="navegarA('despachos')">
                    <div class="option-icon">
                        <i class="fas fa-truck-loading"></i>
                    </div>
                    <h2 class="option-title">Despachos</h2>
                    <p class="option-description">
                        Seguimiento y control de despachos y entregas
                    </p>
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <div class="footer animate-in delay-2">
            <p><i class="fas fa-shield-alt"></i> Sistema seguro y confiable <i class="fas fa-lock"></i></p>
            <p>Sistema RANSA &copy; 2026 - Todos los derechos reservados</p>
            <p style="font-size: 12px; margin-top: 8px; color: #95a5a6;">
                <i class="fas fa-info-circle"></i> Versión 2.0 | Última actualización: Marzo 2026
            </p>
        </div>
    </div>

    <!-- Cargar JavaScript específico de main -->
    <script src="assets/js/main.js"></script>
</body>
</html>