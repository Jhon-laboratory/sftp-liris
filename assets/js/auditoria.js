// ========== VARIABLES GLOBALES ==========
let dataTable = null;
let filtrosActuales = {
    modulo: '',
    resultado: '',
    fecha_desde: '',
    fecha_hasta: '',
    busqueda: ''
};

// ========== FUNCIONES DE NAVEGACIÓN ==========
function volverAlInicio() {
    window.location.href = '../index.php';
}

function irADespachos() {
    window.location.href = 'despachos1.php';
}

function irARecepciones() {
    window.location.href = 'recepcion_asn.php';
}

// ========== INICIALIZAR DATATABLE ==========
function inicializarDataTable() {
    if (dataTable) {
        dataTable.destroy();
    }
    
    dataTable = $('#tablaAuditoria').DataTable({
        responsive: true,
        processing: true,
        serverSide: true,
        ajax: {
            url: '../controller/obtener_auditoria.php',
            type: 'POST',
            data: function(d) {
                return $.extend({}, d, filtrosActuales);
            },
            error: function(xhr, error, thrown) {
                console.error('Error en DataTables:', error);
                console.error('Respuesta del servidor:', xhr.responseText);
            }
        },
        columns: [
            { 
                data: 'fecha',
                render: function(data) {
                    return `<span class="fecha-columna"><i class="far fa-calendar-alt"></i> ${data}</span>`;
            }
            },
            { 
                data: 'nombre_usuario',
                render: function(data) {
                    return `<i class="fas fa-user"></i> ${data || 'Sistema'}`;
            }
            },
            { 
                data: 'accion',
                render: function(data) {
                    let icono = '';
                    if (data === 'ENVIO_SFTP') icono = '<i class="fas fa-paper-plane"></i>';
                    else if (data === 'DESCARGA_EXCEL') icono = '<i class="fas fa-file-excel"></i>';
                    else if (data === 'DESCARGA_TXT') icono = '<i class="fas fa-file-alt"></i>';
                    return `${icono} ${data}`;
            }
            },
            { 
                data: 'modulo',
                render: function(data) {
                    let clase = data === 'DESPACHO' ? 'badge-despacho' : 'badge-recepcion';
                    return `<span class="badge-modulo ${clase}">${data}</span>`;
            }
            },
            { data: 'numero_orden' },
            { 
                data: 'resultado',
                render: function(data) {
                    let clase = '';
                    if (data === 'EXITO') clase = 'badge-exito';
                    else if (data === 'ERROR') clase = 'badge-error';
                    else if (data === 'CANCELADO') clase = 'badge-cancelado';
                    else if (data === 'INICIADO') clase = 'badge-iniciado';
                    
                    return `<span class="badge ${clase}">${data}</span>`;
            }
            },
            { 
                data: 'detalles',
                render: function(data) {
                    if (!data || data === '-') return '-';
                    if (data.length < 50) return data;
                    
                    return `<div class="detalle-cell">
                        <span class="detalle-preview">${data.substring(0, 50)}...</span>
                        <span class="detalle-completo">${data}</span>
                    </div>`;
            }
            }
        ],
        order: [[0, 'desc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        initComplete: function() {
            console.log('DataTable inicializada correctamente');
            actualizarEstadisticas();
        }
    });
}

// ========== APLICAR FILTROS ==========
function aplicarFiltros() {
    filtrosActuales = {
        modulo: document.getElementById('filtroModulo').value,
        resultado: document.getElementById('filtroResultado').value,
        fecha_desde: document.getElementById('filtroFechaDesde').value,
        fecha_hasta: document.getElementById('filtroFechaHasta').value,
        busqueda: document.getElementById('filtroBusqueda').value
    };
    
    if (dataTable) {
        dataTable.ajax.reload(function() {
            actualizarEstadisticas();
        });
    }
}

// ========== LIMPIAR FILTROS ==========
function limpiarFiltros() {
    const hoy = new Date();
    const anio = hoy.getFullYear();
    const mes = String(hoy.getMonth() + 1).padStart(2, '0');
    const dia = String(hoy.getDate()).padStart(2, '0');
    const hoyStr = `${anio}-${mes}-${dia}`;
    
    document.getElementById('filtroModulo').value = '';
    document.getElementById('filtroResultado').value = '';
    document.getElementById('filtroFechaDesde').value = hoyStr;
    document.getElementById('filtroFechaHasta').value = hoyStr;
    document.getElementById('filtroBusqueda').value = '';
    
    filtrosActuales = {
        modulo: '',
        resultado: '',
        fecha_desde: hoyStr,
        fecha_hasta: hoyStr,
        busqueda: ''
    };
    
    if (dataTable) {
        dataTable.ajax.reload(function() {
            actualizarEstadisticas();
        });
    }
}

// ========== ACTUALIZAR ESTADÍSTICAS ==========
async function actualizarEstadisticas() {
    try {
        const response = await fetch('../controller/estadisticas_auditoria.php');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalRegistros').textContent = data.total || '0';
            document.getElementById('enviosExitosos').textContent = data.envios_exitosos || '0';
            document.getElementById('descargasHoy').textContent = data.descargas_hoy || '0';
            document.getElementById('errores').textContent = data.errores || '0';
        }
    } catch (error) {
        console.error('Error obteniendo estadísticas:', error);
    }
}

// ========== EXPORTAR A EXCEL ==========
function exportarExcel() {
    const filtros = encodeURIComponent(JSON.stringify(filtrosActuales));
    window.location.href = `../controller/exportar_auditoria.php?filtros=${filtros}`;
}

// ========== ACTUALIZAR CADA 30 SEGUNDOS ==========
let intervaloActualizacion = null;

function iniciarAutoActualizacion() {
    if (intervaloActualizacion) {
        clearInterval(intervaloActualizacion);
    }
    
    intervaloActualizacion = setInterval(() => {
        if (dataTable && document.visibilityState === 'visible') {
            dataTable.ajax.reload(null, false);
            actualizarEstadisticas();
        }
    }, 30000);
}

// ========== INICIALIZACIÓN ==========
document.addEventListener('DOMContentLoaded', function() {
    // Configurar fechas por defecto (hoy)
    const hoy = new Date();
    const anio = hoy.getFullYear();
    const mes = String(hoy.getMonth() + 1).padStart(2, '0');
    const dia = String(hoy.getDate()).padStart(2, '0');
    const hoyStr = `${anio}-${mes}-${dia}`;
    
    document.getElementById('filtroFechaDesde').value = hoyStr;
    document.getElementById('filtroFechaHasta').value = hoyStr;
    
    filtrosActuales.fecha_desde = hoyStr;
    filtrosActuales.fecha_hasta = hoyStr;
    
    // Inicializar DataTable
    inicializarDataTable();
    
    // Configurar event listeners para filtros
    document.getElementById('btnAplicarFiltros').addEventListener('click', aplicarFiltros);
    document.getElementById('btnLimpiarFiltros').addEventListener('click', limpiarFiltros);
    document.getElementById('btnExportarExcel').addEventListener('click', exportarExcel);
    
    // Iniciar auto-actualización
    iniciarAutoActualizacion();
    
    // Detener auto-actualización cuando la página no está visible
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            if (intervaloActualizacion) {
                clearInterval(intervaloActualizacion);
                intervaloActualizacion = null;
            }
        } else {
            iniciarAutoActualizacion();
        }
    });
});