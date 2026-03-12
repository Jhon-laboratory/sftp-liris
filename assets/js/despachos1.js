// ========== VARIABLES GLOBALES ==========
let currentPedidoData = null;
let sftpTimeout = null;
let filtroObservacionesActivo = false;
let datosDetalleOriginal = [];

// ========== FUNCIÓN PARA REGISTRAR AUDITORÍA ==========
async function registrarAuditoria(accion, resultado, detalles = '') {
    // Solo registrar si hay datos actuales
    const numeroOrden = document.getElementById('numeroOrden')?.value || 
                        currentPedidoData?.informacion_pedido?.externorderkey || '';
    
    if (!numeroOrden) {
        console.log('No hay orden para auditar');
        return;
    }
    
    const data = {
        accion: accion,
        modulo: 'DESPACHO',
        valor_buscado: numeroOrden,
        numero_orden: numeroOrden,
        resultado: resultado,
        detalles: detalles
    };
    
    try {
        const response = await fetch('../controller/guardar_auditoria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (!result.success) {
            console.error('Error registrando auditoría:', result.error);
        }
    } catch (error) {
        console.error('Error registrando auditoría:', error);
    }
}

// ========== FUNCIONES DE NAVEGACIÓN ==========
function volverAlInicio() {
    window.location.href = '../index.php';
}

function irAVerificadasCerradas() {
    window.location.href = 'recepcion_asn.html';
}

// ========== INDICADOR SFTP ==========
function mostrarIndicadorSFTP(tipo, mensaje, duracion = 10000) {
    const indicator = document.getElementById('sftpIndicator');
    const icon = document.getElementById('sftpIcon');
    const message = document.getElementById('sftpMessage');
    
    if (sftpTimeout) clearTimeout(sftpTimeout);
    
    indicator.className = 'sftp-indicator';
    indicator.classList.add(tipo);
    
    switch(tipo) {
        case 'success':
            icon.className = 'fas fa-check-circle';
            break;
        case 'error':
            icon.className = 'fas fa-times-circle';
            break;
        case 'warning':
            icon.className = 'fas fa-exclamation-triangle';
            break;
        case 'info':
            icon.className = 'fas fa-info-circle';
            break;
    }
    
    message.textContent = mensaje;
    indicator.style.display = 'flex';
    
    sftpTimeout = setTimeout(() => {
        cerrarIndicadorSFTP();
    }, duracion);
}

function cerrarIndicadorSFTP() {
    const indicator = document.getElementById('sftpIndicator');
    indicator.style.display = 'none';
    if (sftpTimeout) {
        clearTimeout(sftpTimeout);
        sftpTimeout = null;
    }
}

// ========== FUNCIONES DE MAPEO DE CÓDIGOS ==========
function obtenerDescripcionEstado(codigo) {
    const estados = {
        '92': 'Parcialmente expedido',
        '95': 'Expedido al completo',
    };
    return estados[codigo] || `Estado ${codigo}`;
}

function obtenerDescripcionTipo(codigo) {
    const tipos = {
        '152': 'Despacho a Tienda',
        '153': 'Devolución de Proveedor',
        '155': 'Nota de crédito',
    };
    return tipos[codigo] || `Tipo ${codigo}`;
}

// ========== FUNCIONES AUXILIARES ==========
function mostrarBotonesFlotantes(status) {
    const floatingActions = document.getElementById('floatingActions');
    const actionEnviarInterfaz = document.getElementById('actionEnviarInterfaz');
    
    floatingActions.style.display = 'flex';
    
    if (status === '95') {
        actionEnviarInterfaz.style.display = 'flex';
    } else {
        actionEnviarInterfaz.style.display = 'none';
    }
}

function ocultarBotonesFlotantes() {
    document.getElementById('floatingActions').style.display = 'none';
}

function formatoDosDecimales(num) {
    const numFloat = parseFloat(num) || 0;
    return numFloat.toFixed(2);
}

function formatearFechaTXT(fechaStr) {
    if (!fechaStr || fechaStr === 'N/A') return '';
    
    try {
        if (fechaStr.includes('/')) {
            const partes = fechaStr.split(' ');
            const fechaParte = partes[0];
            const horaParte = partes[1] || '00:00';
            
            const [dia, mesNombre, anio] = fechaParte.split('/');
            const meses = {
                'Enero': '01', 'Febrero': '02', 'Marzo': '03', 'Abril': '04',
                'Mayo': '05', 'Junio': '06', 'Julio': '07', 'Agosto': '08',
                'Septiembre': '09', 'Octubre': '10', 'Noviembre': '11', 'Diciembre': '12'
            };
            
            const mesNumero = meses[mesNombre] || '01';
            const [hora, minuto] = horaParte.split(':');
            
            return `${anio}${mesNumero}${dia.padStart(2, '0')} ${hora.padStart(2, '0')}:${minuto.padStart(2, '0')}:00`;
        }
        
        return fechaStr;
    } catch (e) {
        console.error('Error formateando fecha:', e);
        return '';
    }
}

// ========== FILTRO DE OBSERVACIONES ==========
function toggleFiltroObservaciones() {
    if (!currentPedidoData || !currentPedidoData.detalle_pedido) {
        mostrarMensaje('warning', 'No hay datos para filtrar');
        return;
    }
    
    const btn = document.getElementById('btnFiltroObservaciones');
    const btnTexto = document.getElementById('btnFiltroTexto');
    const contador = document.getElementById('contadorObservaciones');
    
    if (filtroObservacionesActivo) {
        filtroObservacionesActivo = false;
        btnTexto.textContent = 'Ver solo observaciones';
        btn.style.background = 'linear-gradient(135deg, #f31212, #e62222e7)';
        llenarTablaDetalles(currentPedidoData.detalle_pedido);
        mostrarMensaje('info', 'Mostrando todas las líneas');
    } else {
        filtroObservacionesActivo = true;
        btnTexto.textContent = 'Mostrar todas';
        btn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
        
        const lineasConProblemas = currentPedidoData.detalle_pedido.filter(detalle => tieneProblemas(detalle));
        
        if (lineasConProblemas.length === 0) {
            mostrarMensaje('success', 'No hay líneas con observaciones');
            filtroObservacionesActivo = false;
            btnTexto.textContent = 'Ver solo observaciones';
            btn.style.background = 'linear-gradient(135deg, #f39c12, #e67e22)';
            return;
        }
        
        llenarTablaDetalles(lineasConProblemas);
        mostrarMensaje('warning', `Mostrando ${lineasConProblemas.length} líneas con observaciones`);
    }
    
    actualizarContadorObservaciones();
}

function actualizarContadorObservaciones() {
    const contador = document.getElementById('contadorObservaciones');
    if (currentPedidoData && currentPedidoData.detalle_pedido) {
        const lineasConProblemas = currentPedidoData.detalle_pedido.filter(detalle => tieneProblemas(detalle)).length;
        contador.textContent = lineasConProblemas;
        
        if (lineasConProblemas > 0) {
            contador.style.background = '#e74c3c';
            contador.style.color = 'white';
        } else {
            contador.style.background = 'white';
            contador.style.color = '#e67e22';
        }
    } else {
        contador.textContent = '0';
    }
}

// ========== DESCARGAR EXCEL ==========
function descargarExcel() {
    if (!currentPedidoData || !currentPedidoData.detalle_pedido) {
        mostrarMensaje('error', 'No hay datos para exportar');
        return;
    }

    // Registrar inicio de descarga
    registrarAuditoria('DESCARGA_EXCEL', 'INICIADO', 'Iniciando descarga de Excel');

    try {
        const btn = document.querySelector('#actionEnviarInterfaz .action-button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const wb = XLSX.utils.book_new();
        const ws_data = [];
        
        const encabezados = [
            '# Línea', 'Orden Externa', 'SKU', 'Línea Externa',
            'Cantidad Original', 'Cantidad Enviada', 'LPN (ID)',
            'Fecha Despacho', 'Estado'
        ];
        ws_data.push(encabezados);
        
        currentPedidoData.detalle_pedido.forEach(detalle => {
            const tieneLPN = detalle.id && detalle.id !== '';
            const estado = tieneLPN ? 'CON LPN' : 'SIN LPN';
            const numeroLinea = parseInt(detalle.orderlinenumber) || '';
            
            const fila = [
                numeroLinea,
                detalle.externorderkey || '',
                detalle.sku || '',
                detalle.externlineno || '',
                formatoDosDecimales(detalle.originalqty),
                formatoDosDecimales(detalle.shippedqty),
                detalle.id || '',
                detalle.actualshipdate || '',
                estado
            ];
            ws_data.push(fila);
        });
        
        const ws = XLSX.utils.aoa_to_sheet(ws_data);
        
        const wscols = [
            {wch: 8}, {wch: 20}, {wch: 20}, {wch: 15},
            {wch: 18}, {wch: 18}, {wch: 25}, {wch: 20}, {wch: 12}
        ];
        ws['!cols'] = wscols;
        
        const ordenKey = currentPedidoData.informacion_pedido.externorderkey || 'pedido';
        const fecha = new Date().toISOString().slice(0, 10);
        XLSX.utils.book_append_sheet(wb, ws, 'Detalle');
        XLSX.writeFile(wb, `LIRIS_${ordenKey}_${fecha}.xlsx`);
        
        setTimeout(async () => {
            btn.innerHTML = originalHTML;
            await registrarAuditoria('DESCARGA_EXCEL', 'EXITO', `Archivo LIRIS_${ordenKey}_${fecha}.xlsx generado`);
            mostrarMensaje('success', 'Archivo Excel descargado correctamente');
        }, 100);
        
    } catch (error) {
        console.error('Error generando Excel:', error);
        registrarAuditoria('DESCARGA_EXCEL', 'ERROR', `Error: ${error.message}`);
        mostrarMensaje('error', 'Error al generar archivo Excel');
        const btn = document.querySelector('#actionEnviarInterfaz .action-button');
        btn.innerHTML = '<i class="fas fa-file-excel"></i>';
    }
}

// ========== DESCARGAR TXT ==========
function descargarTXT() {
    if (!currentPedidoData || !currentPedidoData.detalle_pedido) {
        mostrarMensaje('error', 'No hay datos para exportar');
        return;
    }

    // Registrar inicio de descarga
    registrarAuditoria('DESCARGA_TXT', 'INICIADO', 'Iniciando descarga de TXT');

    try {
        const btn = document.querySelector('#actionEnviarInterfaz + .action-item + .action-item .action-button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        const lines = [];
        const externorderkey = currentPedidoData.informacion_pedido.externorderkey || '';
        
        if (!externorderkey) {
            registrarAuditoria('DESCARGA_TXT', 'ERROR', 'No se encontró orden externa');
            mostrarMensaje('error', 'No se encontró orden externa');
            btn.innerHTML = originalHTML;
            return;
        }
        
        let lineasFiltradas = 0;
        let lineasIncluidas = 0;
        
        currentPedidoData.detalle_pedido.forEach(detalle => {
            const shippedqty = parseFloat(detalle.shippedqty) || 0;
            
            if (shippedqty === 0) {
                lineasFiltradas++;
                return;
            }
            
            lineasIncluidas++;
            const fields = [
                externorderkey,
                detalle.sku || '',
                detalle.sku || '',
                detalle.orderlinenumber || '',
                formatoDosDecimales(detalle.originalqty),
                formatoDosDecimales(detalle.shippedqty),
                formatearFechaTXT(detalle.actualshipdate),
                detalle.id || ''
            ];
            lines.push(fields.join('|]'));
        });
        
        if (lines.length === 0) {
            registrarAuditoria('DESCARGA_TXT', 'CANCELADO', 'No hay líneas con cantidad enviada > 0');
            mostrarMensaje('warning', 'No hay líneas con cantidad enviada > 0');
            btn.innerHTML = originalHTML;
            return;
        }
        
        const txtContent = lines.join('\r\n');
        const blob = new Blob([txtContent], { type: 'text/plain;charset=utf-8' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.href = url;
        link.download = `${externorderkey}.txt`;
        document.body.appendChild(link);
        link.click();
        
        setTimeout(async () => {
            btn.innerHTML = originalHTML;
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            await registrarAuditoria('DESCARGA_TXT', 'EXITO', 
                `Archivo ${externorderkey}.txt generado con ${lineasIncluidas} líneas (${lineasFiltradas} filtradas)`);
            mostrarMensaje('success', `Archivo ${externorderkey}.txt descargado`);
        }, 100);
        
    } catch (error) {
        console.error('Error generando TXT:', error);
        registrarAuditoria('DESCARGA_TXT', 'ERROR', `Error: ${error.message}`);
        mostrarMensaje('error', 'Error al generar archivo TXT');
        const btn = document.querySelector('#actionEnviarInterfaz + .action-item + .action-item .action-button');
        btn.innerHTML = '<i class="fas fa-file-alt"></i>';
    }
}

// ========== ENVIAR DATOS A SFTP ==========
async function enviarDatosInterfaz() {
    if (!currentPedidoData || !currentPedidoData.detalle_pedido) {
        mostrarIndicadorSFTP('error', '❌ No hay datos para enviar');
        return;
    }

    const status = currentPedidoData.informacion_pedido.status;
    const externorderkey = currentPedidoData.informacion_pedido.externorderkey || '';
    
    if (!externorderkey) {
        mostrarIndicadorSFTP('error', '❌ No se encontró orden externa');
        return;
    }
    
    if (status !== '95') {
        mostrarIndicadorSFTP('warning', `⚠️ Solo pedidos con estado 95. Actual: ${status}`);
        await registrarAuditoria('ENVIO_SFTP', 'CANCELADO', `Estado incorrecto: ${status}`);
        return;
    }

    let totalLineas = currentPedidoData.detalle_pedido.length;
    let lineasConEnvio = 0;
    let lineasSinEnvio = 0;
    
    currentPedidoData.detalle_pedido.forEach(detalle => {
        const shippedqty = parseFloat(detalle.shippedqty) || 0;
        if (shippedqty === 0) {
            lineasSinEnvio++;
        } else {
            lineasConEnvio++;
        }
    });
    
    const confirmar = confirm(
        `¿ENVIAR PEDIDO A SFTP?\n\n` +
        `Orden: ${externorderkey}.txt\n` +
        `Estado: ${status}\n` +
        `Líneas con envío: ${lineasConEnvio}\n` +
        `Líneas filtradas: ${lineasSinEnvio}\n\n` +
        `¿Continuar?`
    );
    
    if (!confirmar) {
        mostrarIndicadorSFTP('info', 'Envío cancelado');
        await registrarAuditoria('ENVIO_SFTP', 'CANCELADO', 'Cancelado por el usuario');
        return;
    }

    try {
        mostrarIndicadorSFTP('info', `📤 Generando archivo...`);
        
        const btn = document.querySelector('#actionEnviarInterfaz .action-button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        const lines = [];
        let lineasIncluidas = 0;
        
        currentPedidoData.detalle_pedido.forEach(detalle => {
            const shippedqty = parseFloat(detalle.shippedqty) || 0;
            if (shippedqty === 0) return;
            
            lineasIncluidas++;
            const fields = [
                externorderkey,
                detalle.sku || '',
                detalle.sku || '',
                detalle.orderlinenumber || '',
                formatoDosDecimales(detalle.originalqty),
                formatoDosDecimales(detalle.shippedqty),
                formatearFechaTXT(detalle.actualshipdate),
                detalle.id || ''
            ];
            lines.push(fields.join('|]'));
        });
        
        if (lines.length === 0) {
            mostrarIndicadorSFTP('warning', '⚠️ No hay líneas con envío > 0');
            btn.innerHTML = originalHTML;
            await registrarAuditoria('ENVIO_SFTP', 'CANCELADO', 'No hay líneas con envío > 0');
            return;
        }
        
        const txtContent = lines.join('\r\n');
        const nombreArchivo = `${externorderkey}.txt`;
        
        mostrarIndicadorSFTP('info', `🔗 Enviando a SFTP...`);
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000);
        
        const response = await fetch('../Controller/enviarsftp.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                contenido: txtContent,
                nombre_archivo: nombreArchivo,
                orden: externorderkey,
                lineas: lines.length,
                fecha: new Date().toISOString(),
                usuario: 'sistema_despachos'
            }),
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            throw new Error(`Error HTTP ${response.status}`);
        }
        
        const resultado = await response.json();
        btn.innerHTML = originalHTML;
        
        if (resultado.success) {
            mostrarIndicadorSFTP('success', 
                `✅ Archivo ${nombreArchivo} enviado a SFTP\n` +
                `📊 ${lineasIncluidas} líneas incluidas`, 10000);
            mostrarMensaje('success', `Envío SFTP exitoso`);
            await registrarAuditoria('ENVIO_SFTP', 'EXITO', 
                `Archivo ${nombreArchivo} enviado con ${lineasIncluidas} líneas`);
        } else {
            mostrarIndicadorSFTP('error', `❌ Error: ${resultado.error || 'Desconocido'}`);
            await registrarAuditoria('ENVIO_SFTP', 'ERROR', resultado.error || 'Error desconocido');
        }
        
    } catch (error) {
        console.error('Error:', error);
        const btn = document.querySelector('#actionEnviarInterfaz .action-button');
        btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        
        let mensaje = 'Error de conexión';
        if (error.name === 'AbortError') {
            mensaje = 'Timeout excedido';
        }
        mostrarIndicadorSFTP('error', `❌ ${mensaje}`);
        await registrarAuditoria('ENVIO_SFTP', 'ERROR', `${mensaje}: ${error.message}`);
    }
}

// ========== FUNCIONES DE INTERFAZ ==========
function mostrarMensaje(tipo, mensaje, duracion = 3000) {
    const container = document.getElementById("messageContainer");
    
    let icono = '';
    let clase = '';
    
    switch(tipo) {
        case 'error':
            icono = '<i class="fas fa-exclamation-circle"></i>';
            clase = 'error';
            break;
        case 'warning':
            icono = '<i class="fas fa-exclamation-triangle"></i>';
            clase = 'warning';
            break;
        case 'success':
            icono = '<i class="fas fa-check-circle"></i>';
            clase = 'success';
            break;
        case 'info':
            icono = '<i class="fas fa-info-circle"></i>';
            clase = 'info';
            break;
    }
    
    container.innerHTML = `
        <div class="message ${clase} visible">
            ${icono}
            <span>${mensaje}</span>
        </div>
    `;
    
    setTimeout(() => {
        container.innerHTML = '';
    }, duracion);
}

function mostrarLoading() {
    const boton = document.getElementById("btnBuscar");
    boton.disabled = true;
    boton.classList.add("loading");
    boton.innerHTML = '<i class="fas fa-spinner"></i> Consultando...';
}

function ocultarLoading() {
    const boton = document.getElementById("btnBuscar");
    boton.disabled = false;
    boton.classList.remove("loading");
    boton.innerHTML = '<i class="fas fa-search"></i> Consultar';
}

function ocultarResultados() {
    document.getElementById("resultSection").style.display = 'none';
    ocultarBotonesFlotantes();
}

function limpiarResultados() {
    document.getElementById("pedidoHeader").innerHTML = '';
    document.getElementById("detalleBody").innerHTML = '';
    document.getElementById("totalOriginal").textContent = '0';
    document.getElementById("totalEnviado").textContent = '0';
    document.getElementById("totalLineas").textContent = '0';
    document.getElementById("lineasConLPN").textContent = '0';
    document.getElementById("lineasConProblemas").textContent = '0';
    currentPedidoData = null;
    datosDetalleOriginal = [];
    
    const contador = document.getElementById('contadorObservaciones');
    if (contador) {
        contador.textContent = '0';
        contador.style.background = 'white';
        contador.style.color = '#e67e22';
    }
    
    const btnTexto = document.getElementById('btnFiltroTexto');
    const btn = document.getElementById('btnFiltroObservaciones');
    if (btnTexto && btn) {
        btnTexto.textContent = 'Ver solo observaciones';
        btn.style.background = 'linear-gradient(135deg, #f39c12, #e67e22)';
    }
    filtroObservacionesActivo = false;
}

function llenarInformacionPedido(info) {
    const container = document.getElementById("pedidoHeader");
    
    const estadoDescripcion = obtenerDescripcionEstado(info.status);
    const tipoDescripcion = obtenerDescripcionTipo(info.type);
    
    const campos = [
        { label: 'Orden Interna', value: info.orderkey || 'N/A', clase: 'orderkey' },
        { label: 'Orden Externa', value: info.externorderkey || 'N/A', clase: 'externorderkey' },
        { label: 'Cliente', value: info.ccompany || 'N/A', clase: 'cliente' },
        { label: 'Cliente ID', value: info.storerkey || 'N/A' },
        { 
            label: 'Estado', 
            value: `<span class="codigo">${info.status || 'N/A'}</span> - ${estadoDescripcion}`, 
            clase: 'estado' 
        },
        { 
            label: 'Tipo', 
            value: `<span class="codigo">${info.type || 'N/A'}</span> - ${tipoDescripcion}`, 
            clase: 'tipo' 
        },
        { label: 'Fecha Pedido', value: info.adddate || 'N/A' },
        { label: 'Ciudad', value: info.ccity || 'N/A' }
    ];
    
    container.innerHTML = campos.map(campo => `
        <div class="pedido-info">
            <span class="info-label">${campo.label}</span>
            <span class="info-value ${campo.clase || ''}">${campo.value}</span>
        </div>
    `).join('');
}

function determinarClaseLPN(id) {
    return id && id !== '' ? 'con-lpn' : 'sin-lpn';
}

function cantidadesCoinciden(original, enviado) {
    const originalNum = parseFloat(original) || 0;
    const enviadoNum = parseFloat(enviado) || 0;
    return originalNum === enviadoNum;
}

function tieneProblemas(detalle) {
    const sinLPN = !detalle.id || detalle.id === '';
    const cantidadesDiferentes = !cantidadesCoinciden(detalle.originalqty, detalle.shippedqty);
    return sinLPN || cantidadesDiferentes;
}

function llenarTablaDetalles(detalles) {
    const tbody = document.getElementById("detalleBody");
    
    if (!detalles || detalles.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="no-data">
                    <i class="fas fa-database"></i>
                    <div>No se encontraron detalles del pedido</div>
                </td>
            </tr>
        `;
        return;
    }
    
    detalles.sort((a, b) => (parseInt(a.orderlinenumber) || 0) - (parseInt(b.orderlinenumber) || 0));
    
    tbody.innerHTML = detalles.map(detalle => {
        const claseLpn = determinarClaseLPN(detalle.id);
        const coinciden = cantidadesCoinciden(detalle.originalqty, detalle.shippedqty);
        const conProblemas = tieneProblemas(detalle);
        const claseFila = conProblemas ? 'con-problemas' : '';
        const claseQty = coinciden ? '' : 'diferencia';
        const numeroLinea = parseInt(detalle.orderlinenumber) || detalle.orderlinenumber;
        
        return `
            <tr class="${claseFila}">
                <td class="orderlinenumber">${numeroLinea}</td>
                <td class="externorderkey">${detalle.externorderkey || 'N/A'}</td>
                <td class="sku">${detalle.sku || 'N/A'}</td>
                <td class="externlineno">${detalle.externlineno || 'N/A'}</td>
                <td class="qty">${detalle.originalqty || '0'}</td>
                <td class="qty ${claseQty}">${detalle.shippedqty || '0'}</td>
                <td class="id-lpn ${claseLpn}">${detalle.id || ''}</td>
                <td class="actualshipdate">${detalle.actualshipdate || 'N/A'}</td>
            </tr>
        `;
    }).join('');
}

function actualizarTotales(detalles) {
    let totalOriginal = 0;
    let totalEnviado = 0;
    let lineasConLpn = 0;
    let lineasConProblemas = 0;
    
    detalles.forEach(detalle => {
        const original = parseFloat(detalle.originalqty) || 0;
        const enviado = parseFloat(detalle.shippedqty) || 0;
        
        totalOriginal += original;
        totalEnviado += enviado;
        
        if (detalle.id && detalle.id !== '') {
            lineasConLpn++;
        }
        
        if (tieneProblemas(detalle)) {
            lineasConProblemas++;
        }
    });
    
    const formatearNumero = (num) => num.toLocaleString('es-ES');
    
    document.getElementById("totalOriginal").textContent = formatearNumero(totalOriginal);
    document.getElementById("totalEnviado").textContent = formatearNumero(totalEnviado);
    document.getElementById("totalLineas").textContent = formatearNumero(detalles.length);
    document.getElementById("lineasConLPN").textContent = formatearNumero(lineasConLpn);
    document.getElementById("lineasConProblemas").textContent = formatearNumero(lineasConProblemas);
    
    return { totalOriginal, totalEnviado, totalLineas: detalles.length, lineasConLpn, lineasConProblemas };
}

function mostrarResultados(informacion, detalles) {
    currentPedidoData = {
        informacion_pedido: informacion,
        detalle_pedido: detalles
    };
    
    datosDetalleOriginal = [...detalles];
    
    llenarInformacionPedido(informacion);
    const estadisticas = actualizarTotales(detalles);
    llenarTablaDetalles(detalles);
    mostrarBotonesFlotantes(informacion.status);
    document.getElementById("resultSection").style.display = 'block';
    
    actualizarContadorObservaciones();
    
    filtroObservacionesActivo = false;
    const btnTexto = document.getElementById('btnFiltroTexto');
    const btn = document.getElementById('btnFiltroObservaciones');
    if (btnTexto && btn) {
        btnTexto.textContent = 'Ver solo observaciones';
        btn.style.background = 'linear-gradient(135deg, #f39c12, #e67e22)';
    }
    
    let mensaje = `Pedido encontrado: ${estadisticas.totalLineas} líneas`;
    if (estadisticas.lineasConProblemas > 0) {
        mensaje += `, ⚠️ ${estadisticas.lineasConProblemas} observaciones`;
        mostrarMensaje('warning', mensaje, 3000);
    } else {
        mensaje += `, ✅ Todo correcto`;
        mostrarMensaje('success', mensaje, 2000);
    }
    
    document.getElementById("resultSection").scrollIntoView({ behavior: 'smooth' });
}

// ========== BÚSQUEDA PRINCIPAL ==========
async function buscarPedido() {
    const numeroOrden = document.getElementById("numeroOrden").value.trim();
    
    if (!numeroOrden) {
        mostrarMensaje('warning', 'Busque por Orden Externa');
        return;
    }

    try {
        mostrarLoading();
        ocultarResultados();
        limpiarResultados();
        
        const response = await fetch(`../controller/consulta_liris.php?valor=${encodeURIComponent(numeroOrden)}`);
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        ocultarLoading();
        
        if (data.error) {
            mostrarMensaje('error', data.error);
            return;
        }
        
        if (data.informacion_pedido && data.detalle_pedido) {
            mostrarResultados(data.informacion_pedido, data.detalle_pedido);
            
            if (data.informacion_pedido.lineas_con_lpn_predeterminado > 0) {
                const infoMsg = `${data.informacion_pedido.lineas_con_lpn_predeterminado} líneas con LPN predeterminado`;
                mostrarIndicadorSFTP('info', infoMsg, 5000);
            }
        }
        
    } catch (error) {
        console.error('Error:', error);
        ocultarLoading();
        mostrarMensaje('error', `Error: ${error.message}`);
    }
}

// ========== INICIALIZACIÓN ==========
document.addEventListener('DOMContentLoaded', function() {
    const btnBuscar = document.getElementById("btnBuscar");
    const numeroOrden = document.getElementById("numeroOrden");
    
    if (btnBuscar) {
        btnBuscar.addEventListener("click", buscarPedido);
    }
    
    if (numeroOrden) {
        numeroOrden.addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                buscarPedido();
            }
        });
        
        numeroOrden.addEventListener("focus", function() {
            this.select();
        });
    }
    
    // Rotar placeholders
    const ejemplos = ["00636825_091", "DP.09.03.2026.melip", "00635735_091"];
    let ejemploIndex = 0;
    
    setInterval(() => {
        if (numeroOrden) {
            numeroOrden.placeholder = `🔍 Busque por Orden Externa (ej: ${ejemplos[ejemploIndex]})`;
            ejemploIndex = (ejemploIndex + 1) % ejemplos.length;
        }
    }, 3000);
});