<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de Acciones - LIRIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <!-- CSS Global (donde están los estilos de auditoría) -->
    <link rel="stylesheet" href="../assets/css/estilo.css">
</head>
<body>
    <!-- Botones flotantes en las esquinas -->
    <div class="floating-buttons">
        <button class="floating-button left" onclick="volverAlInicio()">
            <i class="fas fa-arrow-left"></i>
            <span>Volver al inicio</span>
        </button>
        <button class="floating-button right" onclick="irADespachos()">
            <i class="fas fa-shipping-fast"></i>
            <span>Despachos</span>
        </button>
        <button class="floating-button right" onclick="irARecepciones()" style="margin-right: 120px;">
            <i class="fas fa-box"></i>
            <span>Recepciones</span>
        </button>
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
                <h1 class="title">Historial de Sucesos
                </h1>
                <p class="subtitle">Registro de actividades del sistema</p>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="auditoria-stats">
            <div class="stat-auditoria">
                <div class="numero" id="totalRegistros">0</div>
                <div class="etiqueta">Total Registros</div>
            </div>
            <div class="stat-auditoria">
                <div class="numero" id="enviosExitosos">0</div>
                <div class="etiqueta">Envíos SFTP Exitosos</div>
            </div>
            <div class="stat-auditoria">
                <div class="numero" id="descargasHoy">0</div>
                <div class="etiqueta">Descargas Hoy</div>
            </div>
            <div class="stat-auditoria">
                <div class="numero" id="errores">0</div>
                <div class="etiqueta">Errores</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-container">
            <div class="filtro-item">
                <label><i class="fas fa-cubes"></i> Módulo</label>
                <select id="filtroModulo">
                    <option value="">Todos</option>
                    <option value="DESPACHO">Despachos</option>
                    <option value="RECEPCION">Recepciones</option>
                </select>
            </div>
            
            <div class="filtro-item">
                <label><i class="fas fa-tag"></i> Resultado</label>
                <select id="filtroResultado">
                    <option value="">Todos</option>
                    <option value="EXITO">Éxito</option>
                    <option value="ERROR">Error</option>
                    <option value="CANCELADO">Cancelado</option>
                    <option value="INICIADO">Iniciado</option>
                </select>
            </div>
            
            <div class="filtro-item">
                <label><i class="far fa-calendar-alt"></i> Fecha Desde</label>
                <input type="date" id="filtroFechaDesde">
            </div>
            
            <div class="filtro-item">
                <label><i class="far fa-calendar-alt"></i> Fecha Hasta</label>
                <input type="date" id="filtroFechaHasta">
            </div>
            
            <div class="filtro-item">
                <label><i class="fas fa-search"></i> Búsqueda</label>
                <input type="text" id="filtroBusqueda" placeholder="Orden, SKU, detalles...">
            </div>
            
            <div class="filtro-actions">
                <button class="btn-filtro" id="btnAplicarFiltros">
                    <i class="fas fa-filter"></i> Aplicar
                </button>
                <button class="btn-filtro btn-filtro-secondary" id="btnLimpiarFiltros">
                    <i class="fas fa-undo-alt"></i> Limpiar
                </button>
                <button class="btn-filtro btn-filtro-secondary" id="btnExportarExcel">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>

        <!-- Tabla de Auditoría -->
        <div class="auditoria-container">
            <table id="tablaAuditoria" class="table-auditoria display responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th><i class="far fa-clock"></i> Fecha</th>
                        <th><i class="fas fa-user"></i> Usuario</th>
                        <th><i class="fas fa-bolt"></i> Acción</th>
                        <th><i class="fas fa-cube"></i> Módulo</th>
                        <th><i class="fas fa-hashtag"></i> N° Orden</th>
                        <th><i class="fas fa-check-circle"></i> Resultado</th>
                        <th><i class="fas fa-align-left"></i> Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Se llena dinámicamente con DataTables -->
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Sistema Liris - Ransa &copy; 2026</p>
            <p style="font-size: 12px; margin-top: 5px; color: #95a5a6;">
                <i class="fas fa-shield-alt"></i> Registro de actividades del sistema
                <i class="fas fa-history" style="margin-left: 15px;"></i> Actualización automática cada 30s
            </p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="../assets/js/auditoria.js"></script>
</body>
</html>