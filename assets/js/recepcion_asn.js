// ========== VARIABLES GLOBALES ==========
let currentRecepcionData = null;
let sftpTimeout = null;
let filtroObservacionesActivo = false;
let datosDetalleOriginal = [];

// ========== FUNCIÓN PARA REGISTRAR AUDITORÍA ==========
async function registrarAuditoria(accion, resultado, detalles = '') {
    console.log('📝 Intentando registrar auditoría:', { accion, resultado, detalles });
    
    const numeroOrden = document.getElementById('numeroOrden')?.value || 
                        currentPedidoData?.informacion_pedido?.externorderkey || '';
    
    console.log('🔍 Número de orden:', numeroOrden);
    
    if (!numeroOrden) {
        console.log('⚠️ No hay orden para auditar');
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
    
    console.log('📤 Enviando datos:', data);
    
    try {
        const response = await fetch('../controller/guardar_auditoria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        console.log('📥 Respuesta status:', response.status);
        console.log('📥 Respuesta headers:', response.headers.get('content-type'));
        
        // Obtener el texto de la respuesta
        const textResponse = await response.text();
        console.log('📥 Respuesta texto COMPLETA:', textResponse);
        
        // Intentar parsear como JSON
        try {
            const result = JSON.parse(textResponse);
            console.log('✅ JSON válido:', result);
        } catch (jsonError) {
            console.error('❌ ERROR: La respuesta NO es JSON');
            console.error('Esto es lo que el servidor devolvió:');
            console.error(textResponse);
            
            // Mostrar alerta con el error para que lo veas inmediatamente
            alert('ERROR EN AUDITORÍA - Revisa la consola (F12) para ver el error PHP');
        }
        
    } catch (error) {
        console.error('❌ Error en fetch:', error);
    }
}

// ========== FUNCIONES DE NAVEGACIÓN ==========
function volverAlInicio() {
    window.location.href = '../index.php';
}

function irADespachos() {
    window.location.href = 'despachos1.html';
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

// ========== FUNCIONES AUXILIARES ==========
function mostrarBotonesFlotantes(status) {
    const floatingActions = document.getElementById('floatingActions');
    const actionEnviarInterfaz = document.getElementById('actionEnviarInterfaz');
    
    floatingActions.style.display = 'flex';
    
    // SOLO mostrar botón SFTP si status es 15 (Verificado y cerrado)
    if (status === '15') {
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
            const [dia, mes, anio] = fechaStr.split('/');
            return `${anio}${mes.padStart(2, '0')}${dia.padStart(2, '0')}`;
        }
        if (fechaStr.includes('-')) {
            const [anio, mes, dia] = fechaStr.split('-');
            return `${anio}${mes}${dia}`;
        }
        return fechaStr;
    } catch (e) {
        console.error('Error formateando fecha:', e);
        return '';
    }
}

function formatearFechaCreacionTXT(fechaStr) {
    if (!fechaStr || fechaStr === 'N/A' || fechaStr === '') return '19700101 00:00:00';
    
    try {
        const partes = fechaStr.split(' ');
        if (partes.length < 1) return '19700101 00:00:00';
        
        const fechaParte = partes[0];
        const horaParte = partes[1] || '00:00';
        
        const [dia, mesNombre, anio] = fechaParte.split('/');
        
        const meses = {
            'Enero': '01', 'Febrero': '02', 'Marzo': '03', 'Abril': '04',
            'Mayo': '05', 'Junio': '06', 'Julio': '07', 'Agosto': '08',
            'Septiembre': '09', 'Octubre': '10', 'Noviembre': '11', 'Diciembre': '12'
        };
        
        const mesNumero = meses[mesNombre] || '01';
        return `${anio}${mesNumero}${dia.padStart(2, '0')} ${horaParte}:00`;
        
    } catch (e) {
        console.error('Error formateando fecha creación:', e);
        return '19700101 00:00:00';
    }
}

// ========== VALIDACIÓN DE FECHAS DE VENCIMIENTO ==========
function validarFechaVencimiento(fechaStr) {
    if (!fechaStr || fechaStr === 'N/A' || fechaStr === '') {
        return { valida: false, motivo: 'SIN_FECHA' };
    }
    
    try {
        // Convertir fecha de formato DD/MM/YYYY a objeto Date
        let fechaDate;
        if (fechaStr.includes('/')) {
            const [dia, mes, anio] = fechaStr.split('/');
            fechaDate = new Date(anio, mes - 1, dia);
        } else if (fechaStr.includes('-')) {
            const [anio, mes, dia] = fechaStr.split('-');
            fechaDate = new Date(anio, mes - 1, dia);
        } else {
            return { valida: false, motivo: 'FORMATO_INVALIDO' };
        }
        
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        fechaDate.setHours(0, 0, 0, 0);
        
        // Calcular fecha mínima (hoy + 30 días)
        const fechaMinima = new Date(hoy);
        fechaMinima.setDate(hoy.getDate() + 30);
        
        // Calcular fecha máxima (hoy + 9 años)
        const fechaMaxima = new Date(hoy);
        fechaMaxima.setFullYear(hoy.getFullYear() + 9);
        
        if (fechaDate < hoy) {
            return { 
                valida: false, 
                motivo: 'FECHA_PASADA',
                diasRestantes: Math.floor((fechaDate - hoy) / (1000 * 60 * 60 * 24))
            };
        }
        
        if (fechaDate < fechaMinima) {
            return { 
                valida: false, 
                motivo: 'FECHA_MENOR_30_DIAS',
                diasRestantes: Math.floor((fechaDate - hoy) / (1000 * 60 * 60 * 24))
            };
        }
        
        if (fechaDate > fechaMaxima) {
            return { 
                valida: false, 
                motivo: 'FECHA_MAYOR_9_ANIOS',
                aniosDiferencia: (fechaDate.getFullYear() - hoy.getFullYear())
            };
        }
        
        return { valida: true, motivo: 'OK' };
        
    } catch (e) {
        console.error('Error validando fecha:', e);
        return { valida: false, motivo: 'ERROR_VALIDACION' };
    }
}

function obtenerDescripcionProblemaFecha(resultado) {
    if (resultado.valida) return '';
    
    switch(resultado.motivo) {
        case 'SIN_FECHA':
            return 'Sin fecha de vencimiento';
        case 'FECHA_PASADA':
            return `Fecha ya pasó (hace ${Math.abs(resultado.diasRestantes)} días)`;
        case 'FECHA_MENOR_30_DIAS':
            return `Vence en ${resultado.diasRestantes} días (mínimo 30)`;
        case 'FECHA_MAYOR_9_ANIOS':
            return `Vence a ${resultado.aniosDiferencia} años (máx 9)`;
        case 'FORMATO_INVALIDO':
            return 'Formato de fecha inválido';
        default:
            return 'Fecha no válida';
    }
}

// ========== FILTRO DE OBSERVACIONES ==========
function toggleFiltroObservaciones() {
    if (!currentRecepcionData || !currentRecepcionData.detalle_recepcion) {
        mostrarMensaje('warning', 'No hay datos para filtrar');
        return;
    }
    
    const btn = document.getElementById('btnFiltroObservaciones');
    const btnTexto = document.getElementById('btnFiltroTexto');
    const contador = document.getElementById('contadorObservaciones');
    
    if (filtroObservacionesActivo) {
        // Desactivar filtro
        filtroObservacionesActivo = false;
        btnTexto.textContent = 'Ver solo observaciones';
        btn.style.background = 'linear-gradient(135deg, #f39c12, #e67e22)';
        llenarTablaDetalles(currentRecepcionData.detalle_recepcion);
        mostrarMensaje('info', 'Mostrando todas las líneas');
    } else {
        // Activar filtro
        filtroObservacionesActivo = true;
        btnTexto.textContent = 'Mostrar todas';
        btn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
        
        const lineasConProblemas = currentRecepcionData.detalle_recepcion.filter(detalle => tieneProblemas(detalle));
        
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
    if (currentRecepcionData && currentRecepcionData.detalle_recepcion) {
        const lineasConProblemas = currentRecepcionData.detalle_recepcion.filter(detalle => tieneProblemas(detalle)).length;
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

// ========== ENVIAR DATOS A INTERFAZ SFTP (CON CRLF) ==========
async function enviarDatosInterfaz() {
    if (!currentRecepcionData || !currentRecepcionData.detalle_recepcion) {
        mostrarIndicadorSFTP('error', '❌ No hay datos para enviar');
        return;
    }

    const status = currentRecepcionData.informacion_recepcion.status;
    const externreceiptkey = currentRecepcionData.informacion_recepcion.externreceiptkey || '';
    const adddate = currentRecepcionData.informacion_recepcion.adddate || '';
    
    // Validar estado (SOLO 15)
    if (status !== '15') {
        const estadoTexto = currentRecepcionData.informacion_recepcion.status_texto || `Estado ${status}`;
        mostrarIndicadorSFTP('warning', `⚠️ Solo se pueden enviar recepciones con estado "Verificado y cerrado" (15). Estado actual: ${estadoTexto}`);
        await registrarAuditoria('ENVIO_SFTP', 'CANCELADO', `Estado incorrecto: ${status} - ${estadoTexto}`);
        return;
    }

    // Validar fechas de vencimiento
    let lineasConFechaValida = 0;
    let lineasConProblemasFecha = [];
    
    currentRecepcionData.detalle_recepcion.forEach((detalle, index) => {
        const resultado = validarFechaVencimiento(detalle.fecha_vencimiento);
        if (!resultado.valida) {
            lineasConProblemasFecha.push({
                linea: detalle.externlineno || detalle.receiptlinenumber,
                sku: detalle.sku,
                problema: obtenerDescripcionProblemaFecha(resultado)
            });
        } else {
            lineasConFechaValida++;
        }
    });

    const totalLineas = currentRecepcionData.detalle_recepcion.length;
    
    // Si hay problemas con fechas, mostrar advertencia detallada
    if (lineasConProblemasFecha.length > 0) {
        let mensajeAdvertencia = `⚠️ PROBLEMAS CON FECHAS DE VENCIMIENTO:\n\n`;
        lineasConProblemasFecha.slice(0, 5).forEach(p => {
            mensajeAdvertencia += `Línea ${p.linea} (${p.sku}): ${p.problema}\n`;
        });
        
        if (lineasConProblemasFecha.length > 5) {
            mensajeAdvertencia += `\n... y ${lineasConProblemasFecha.length - 5} más`;
        }
        
        mensajeAdvertencia += `\n\n¿Desea continuar con el envío?`;
        
        const confirmar = confirm(mensajeAdvertencia);
        if (!confirmar) {
            mostrarIndicadorSFTP('info', 'Envío cancelado por problemas en fechas');
            await registrarAuditoria('ENVIO_SFTP', 'CANCELADO', 
                `Cancelado por problemas en fechas (${lineasConProblemasFecha.length} fechas inválidas)`);
            return;
        }
    }

    const confirmar = confirm(
        `¿ENVIAR RECEPCIÓN A SFTP?\n\n` +
        `Recepción: ${externreceiptkey}\n` +
        `Estado: Verificado y cerrado (15)\n` +
        `Total líneas: ${totalLineas}\n` +
        `Fechas válidas: ${lineasConFechaValida}\n` +
        `Fechas con problemas: ${lineasConProblemasFecha.length}\n\n` +
        `¿Continuar?`
    );
    
    if (!confirmar) {
        mostrarIndicadorSFTP('info', 'Envío cancelado por el usuario');
        await registrarAuditoria('ENVIO_SFTP', 'CANCELADO', 'Cancelado por el usuario');
        return;
    }

    try {
        mostrarIndicadorSFTP('info', '📤 Generando archivo TXT para SFTP...');
        
        const btn = document.querySelector('#actionEnviarInterfaz .action-button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        const lines = [];
        const fechaCreacionTXT = formatearFechaCreacionTXT(adddate);
        
        if (!externreceiptkey || externreceiptkey === 'N/A') {
            throw new Error('No se encontró número externo válido');
        }
        
        currentRecepcionData.detalle_recepcion.forEach(detalle => {
            const fields = [
                externreceiptkey,
                detalle.sku || '',
                detalle.sku || '',
                detalle.externlineno || '',
                formatoDosDecimales(detalle.qtyexpected),
                formatoDosDecimales(detalle.qtyreceived),
                fechaCreacionTXT,
                formatearFechaTXT(detalle.fecha_vencimiento),
                formatoDosDecimales(detalle.precio || 0)
            ];
            lines.push(fields.join('|]'));
        });
        
        const txtContent = lines.join('\r\n');
        const nombreArchivo = `${externreceiptkey}.txt`;
        
        mostrarIndicadorSFTP('info', '🔗 Conectando con servidor SFTP...');
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000);
        
        const response = await fetch('../controller/enviarsftp.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                contenido: txtContent,
                nombre_archivo: nombreArchivo,
                orden: externreceiptkey,
                lineas: lines.length,
                fecha: new Date().toISOString(),
                tipo: 'recepcion',
                usuario: 'sistema_recepciones',
                fechas_validas: lineasConFechaValida,
                fechas_problema: lineasConProblemasFecha.length,
                formato_saltos: 'CRLF'
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
                `📊 ${lineasConFechaValida}/${totalLineas} fechas válidas\n` +
                `📝 Formato: CRLF (Windows)`, 10000);
            mostrarMensaje('success', `Envío SFTP exitoso (CRLF)`);
            await registrarAuditoria('ENVIO_SFTP', 'EXITO', 
                `Archivo ${nombreArchivo} enviado. Líneas: ${totalLineas}, Fechas válidas: ${lineasConFechaValida}`);
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
            mensaje = 'Timeout excedido (60s)';
        }
        mostrarIndicadorSFTP('error', `❌ ${mensaje}`);
        await registrarAuditoria('ENVIO_SFTP', 'ERROR', `${mensaje}: ${error.message}`);
    }
}

// ========== DESCARGAR EXCEL ==========
function descargarExcel() {
    if (!currentRecepcionData || !currentRecepcionData.detalle_recepcion) {
        mostrarMensaje('error', 'No hay datos para exportar');
        return;
    }

    // Registrar inicio de descarga
    registrarAuditoria('DESCARGA_EXCEL', 'INICIADO', 'Iniciando descarga de Excel');

    try {
        const btn = document.querySelector('#actionEnviarInterfaz + .action-item .action-button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        const csvData = [
            ['# Línea', 'Número Recepción', 'SKU', 'Línea Externa', 
             'Cantidad Esperada', 'Cantidad Recibida', 'Fecha Vencimiento', 
             'Validación Fecha', 'Precio', 'Estado']
        ];
        
        currentRecepcionData.detalle_recepcion.forEach(detalle => {
            const tieneFecha = detalle.fecha_vencimiento && detalle.fecha_vencimiento !== 'N/A';
            const resultadoFecha = validarFechaVencimiento(detalle.fecha_vencimiento);
            const validacionFecha = resultadoFecha.valida ? 'VÁLIDA' : obtenerDescripcionProblemaFecha(resultadoFecha);
            const estado = tieneFecha ? 'CON FECHA' : 'SIN FECHA';
            
            csvData.push([
                detalle.receiptlinenumber || '',
                detalle.receiptkey || '',
                detalle.sku || '',
                detalle.externlineno || '',
                formatoDosDecimales(detalle.qtyexpected),
                formatoDosDecimales(detalle.qtyreceived),
                detalle.fecha_vencimiento || 'N/A',
                validacionFecha,
                formatoDosDecimales(detalle.precio || 0),
                estado
            ]);
        });
        
        const csvContent = csvData.map(row => 
            row.map(cell => `"${cell}"`).join(',')
        ).join('\r\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.href = url;
        const nombreArchivo = `LIRIS_REC_${currentRecepcionData.informacion_recepcion.receiptkey}_${new Date().toISOString().slice(0,10)}.csv`;
        link.download = nombreArchivo;
        document.body.appendChild(link);
        link.click();
        
        setTimeout(async () => {
            btn.innerHTML = originalHTML;
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            await registrarAuditoria('DESCARGA_EXCEL', 'EXITO', 
                `Archivo ${nombreArchivo} generado con ${csvData.length-1} líneas`);
            mostrarMensaje('success', 'Archivo Excel descargado');
        }, 100);
        
    } catch (error) {
        console.error('Error:', error);
        registrarAuditoria('DESCARGA_EXCEL', 'ERROR', `Error: ${error.message}`);
        mostrarMensaje('error', 'Error al generar Excel');
        const btn = document.querySelector('#actionEnviarInterfaz + .action-item .action-button');
        btn.innerHTML = '<i class="fas fa-file-excel"></i>';
    }
}

// ========== DESCARGAR TXT (CON CRLF) ==========
function descargarTXT() {
    if (!currentRecepcionData || !currentRecepcionData.detalle_recepcion) {
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
        const externreceiptkey = currentRecepcionData.informacion_recepcion.externreceiptkey || '';
        const adddate = currentRecepcionData.informacion_recepcion.adddate || '';
        const fechaCreacionTXT = formatearFechaCreacionTXT(adddate);
        
        if (!externreceiptkey || externreceiptkey === 'N/A') {
            registrarAuditoria('DESCARGA_TXT', 'ERROR', 'No se encontró número externo válido');
            mostrarMensaje('error', 'No se encontró número externo válido');
            btn.innerHTML = originalHTML;
            return;
        }
        
        currentRecepcionData.detalle_recepcion.forEach(detalle => {
            const fields = [
                externreceiptkey,
                detalle.sku || '',
                detalle.sku || '',
                detalle.externlineno || '',
                formatoDosDecimales(detalle.qtyexpected),
                formatoDosDecimales(detalle.qtyreceived),
                fechaCreacionTXT,
                formatearFechaTXT(detalle.fecha_vencimiento),
                formatoDosDecimales(detalle.precio || 0)
            ];
            lines.push(fields.join('|]'));
        });
        
        const txtContent = lines.join('\r\n');
        const blob = new Blob([txtContent], { type: 'text/plain;charset=utf-8' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.href = url;
        link.download = `${externreceiptkey}.txt`;
        document.body.appendChild(link);
        link.click();
        
        setTimeout(async () => {
            btn.innerHTML = originalHTML;
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            await registrarAuditoria('DESCARGA_TXT', 'EXITO', 
                `Archivo ${externreceiptkey}.txt generado con ${lines.length} líneas`);
            mostrarMensaje('success', `Archivo ${externreceiptkey}.txt descargado (CRLF)`);
        }, 100);
        
    } catch (error) {
        console.error('Error generando TXT:', error);
        registrarAuditoria('DESCARGA_TXT', 'ERROR', `Error: ${error.message}`);
        mostrarMensaje('error', 'Error al generar archivo TXT');
        const btn = document.querySelector('#actionEnviarInterfaz + .action-item + .action-item .action-button');
        btn.innerHTML = '<i class="fas fa-file-alt"></i>';
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
    document.getElementById("asnHeader").innerHTML = '';
    document.getElementById("detalleBody").innerHTML = '';
    document.getElementById("totalEsperado").textContent = '0';
    document.getElementById("totalRecibido").textContent = '0';
    document.getElementById("totalLineas").textContent = '0';
    document.getElementById("lineasConFecha").textContent = '0';
    document.getElementById("lineasConProblemas").textContent = '0';
    currentRecepcionData = null;
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

function llenarInformacionRecepcion(info) {
    const container = document.getElementById("asnHeader");
    
    const campos = [
        { label: 'Número Recepción', value: info.receiptkey || 'N/A', clase: 'receiptkey' },
        { label: 'Número Externo', value: info.externreceiptkey || 'N/A' },
        { label: 'Cliente ID', value: info.storerkey || 'N/A' },
        { label: 'Estado', value: info.status_texto || info.status || 'N/A' },
        { label: 'Fecha Creación', value: info.adddate || 'N/A' },
        { label: 'Fecha Cierre', value: info.closeddate || 'N/A' },
        { label: 'Fecha Edición', value: info.editdate || 'N/A' }
    ];
    
    container.innerHTML = campos.map(campo => `
        <div class="asn-info">
            <span class="info-label">${campo.label}</span>
            <span class="info-value ${campo.clase || ''}">${campo.value}</span>
        </div>
    `).join('');
}

function determinarClaseVencimiento(fecha) {
    if (!fecha || fecha === 'N/A') return 'sin-fecha';
    
    const resultado = validarFechaVencimiento(fecha);
    if (!resultado.valida) return 'sin-fecha';
    
    return 'con-fecha';
}

function determinarClasePrecio(precio) {
    const precioNum = parseFloat(precio) || 0;
    return precioNum === 0 ? 'cero' : '';
}

function cantidadesCoinciden(esperada, recibida) {
    const esperadaNum = parseFloat(esperada) || 0;
    const recibidaNum = parseFloat(recibida) || 0;
    return esperadaNum === recibidaNum;
}

function tieneProblemas(detalle) {
    // Problema 1: Fechas inválidas según las reglas de negocio
    const resultadoFecha = validarFechaVencimiento(detalle.fecha_vencimiento);
    const fechaInvalida = !resultadoFecha.valida;
    
    // Problema 2: Cantidades no coinciden
    const cantidadesDiferentes = !cantidadesCoinciden(detalle.qtyexpected, detalle.qtyreceived);
    
    // Problema 3: Precio cero
    const precioCero = parseFloat(detalle.precio) === 0;
    
    return fechaInvalida || cantidadesDiferentes || precioCero;
}

function llenarTablaDetalles(detalles) {
    const tbody = document.getElementById("detalleBody");
    
    if (!detalles || detalles.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="no-data">
                    <i class="fas fa-database"></i>
                    <div>No se encontraron detalles de la recepción</div>
                </td>
            </tr>
        `;
        return;
    }
    
    detalles.sort((a, b) => {
        const aNum = parseInt(a.externlineno) || 0;
        const bNum = parseInt(b.externlineno) || 0;
        if (aNum === bNum) {
            const aRec = parseInt(a.receiptlinenumber) || 0;
            const bRec = parseInt(b.receiptlinenumber) || 0;
            return aRec - bRec;
        }
        return aNum - bNum;
    });
    
    tbody.innerHTML = detalles.map(detalle => {
        const claseVencimiento = determinarClaseVencimiento(detalle.fecha_vencimiento);
        const clasePrecio = determinarClasePrecio(detalle.precio);
        const coinciden = cantidadesCoinciden(detalle.qtyexpected, detalle.qtyreceived);
        const conProblemas = tieneProblemas(detalle);
        const claseFila = conProblemas ? 'con-problemas' : '';
        const claseQty = coinciden ? '' : 'diferencia';
        const precio = parseFloat(detalle.precio) || 0;
        
        // Tooltip para fecha si tiene problemas
        let fechaTooltip = '';
        if (detalle.fecha_vencimiento && detalle.fecha_vencimiento !== 'N/A') {
            const resultado = validarFechaVencimiento(detalle.fecha_vencimiento);
            if (!resultado.valida) {
                fechaTooltip = `title="${obtenerDescripcionProblemaFecha(resultado)}"`;
            }
        }
        
        return `
            <tr class="${claseFila}">
                <td class="receiptlinenumber">${detalle.receiptlinenumber || 'N/A'}</td>
                <td class="receiptkey">${detalle.receiptkey || 'N/A'}</td>
                <td class="sku">${detalle.sku || 'N/A'}</td>
                <td class="externlineno">${detalle.externlineno || 'N/A'}</td>
                <td class="qty">${detalle.qtyexpected || '0'}</td>
                <td class="qty ${claseQty}">${detalle.qtyreceived || '0'}</td>
                <td class="vencimiento ${claseVencimiento}" ${fechaTooltip}>
                    ${detalle.fecha_vencimiento || 'Sin Fecha'}
                </td>
                <td class="precio ${clasePrecio}">
                    ${precio.toFixed(2)}
                </td>
            </tr>
        `;
    }).join('');
}

function actualizarTotales(detalles) {
    let totalEsperado = 0;
    let totalRecibido = 0;
    let lineasConFecha = 0;
    let lineasConProblemas = 0;
    let fechasValidas = 0;
    let fechasInvalidas = 0;
    
    detalles.forEach(detalle => {
        const esperada = parseFloat(detalle.qtyexpected) || 0;
        const recibida = parseFloat(detalle.qtyreceived) || 0;
        
        totalEsperado += esperada;
        totalRecibido += recibida;
        
        if (detalle.fecha_vencimiento && detalle.fecha_vencimiento !== 'N/A') {
            lineasConFecha++;
            const resultado = validarFechaVencimiento(detalle.fecha_vencimiento);
            if (resultado.valida) {
                fechasValidas++;
            } else {
                fechasInvalidas++;
            }
        }
        
        if (tieneProblemas(detalle)) {
            lineasConProblemas++;
        }
    });
    
    const formatearNumero = (num) => num.toLocaleString('es-ES');
    
    document.getElementById("totalEsperado").textContent = formatearNumero(totalEsperado);
    document.getElementById("totalRecibido").textContent = formatearNumero(totalRecibido);
    document.getElementById("totalLineas").textContent = formatearNumero(detalles.length);
    document.getElementById("lineasConFecha").textContent = formatearNumero(lineasConFecha);
    document.getElementById("lineasConProblemas").textContent = formatearNumero(lineasConProblemas);
    
    return { 
        totalEsperado, 
        totalRecibido, 
        totalLineas: detalles.length,
        lineasConFecha,
        lineasConProblemas,
        fechasValidas,
        fechasInvalidas
    };
}

function mostrarResultados(informacion, detalles) {
    currentRecepcionData = {
        informacion_recepcion: informacion,
        detalle_recepcion: detalles
    };
    
    datosDetalleOriginal = [...detalles];
    
    llenarInformacionRecepcion(informacion);
    const estadisticas = actualizarTotales(detalles);
    llenarTablaDetalles(detalles);
    
    // Mostrar botones flotantes (SFTP solo si status 15)
    mostrarBotonesFlotantes(informacion.status);
    
    document.getElementById("resultSection").style.display = 'block';
    
    // Actualizar contador de observaciones
    actualizarContadorObservaciones();
    
    // Resetear filtro
    filtroObservacionesActivo = false;
    const btnTexto = document.getElementById('btnFiltroTexto');
    const btn = document.getElementById('btnFiltroObservaciones');
    if (btnTexto && btn) {
        btnTexto.textContent = 'Ver solo observaciones';
        btn.style.background = 'linear-gradient(135deg, #f39c12, #e67e22)';
    }
    
    // Mensaje con estadísticas de fechas
    let mensaje = `Recepción encontrada: ${estadisticas.totalLineas} líneas`;
    if (estadisticas.fechasInvalidas > 0) {
        mensaje += `, ⚠️ ${estadisticas.fechasInvalidas} fechas inválidas`;
    }
    if (estadisticas.lineasConProblemas > 0) {
        mensaje += `, ⚠️ ${estadisticas.lineasConProblemas} observaciones`;
        mostrarMensaje('warning', mensaje, 4000);
    } else {
        mensaje += `, ✅ Todo correcto`;
        mostrarMensaje('success', mensaje, 3000);
    }
    
    document.getElementById("resultSection").scrollIntoView({ behavior: 'smooth' });
}

// ========== BÚSQUEDA PRINCIPAL ==========
async function buscarRecepcion() {
    const numeroRecepcion = document.getElementById("numeroRecepcion").value.trim();
    
    if (!numeroRecepcion) {
        mostrarMensaje('warning', 'Ingrese un número de Recepción');
        return;
    }

    try {
        mostrarLoading();
        ocultarResultados();
        limpiarResultados();
        
        const response = await fetch(`../controller/consulta_liris_asn.php?valor=${encodeURIComponent(numeroRecepcion)}`);
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        ocultarLoading();
        
        if (data.error) {
            mostrarMensaje('error', data.error);
            return;
        }
        
        if (data.informacion_recepcion && data.detalle_recepcion) {
            mostrarResultados(data.informacion_recepcion, data.detalle_recepcion);
        }
        
    } catch (error) {
        console.error('Error:', error);
        ocultarLoading();
        mostrarMensaje('error', `Error: ${error.message}`);
    }
}
async function registrarAuditoria(accion, resultado, detalles = '') {
    console.log('📝 Intentando registrar auditoría:', { accion, resultado, detalles });
    
    const numeroRecepcion = document.getElementById('numeroRecepcion')?.value || 
                            currentRecepcionData?.informacion_recepcion?.externreceiptkey || '';
    
    console.log('🔍 Número de recepción:', numeroRecepcion);
    
    if (!numeroRecepcion) {
        console.log('⚠️ No hay recepción para auditar');
        return;
    }
    
    const data = {
        accion: accion,
        modulo: 'RECEPCION',
        valor_buscado: numeroRecepcion,
        numero_orden: numeroRecepcion,
        resultado: resultado,
        detalles: detalles
    };
    
    console.log('📤 Enviando datos:', data);
    
    try {
        const response = await fetch('../controller/guardar_auditoria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        console.log('📥 Respuesta status:', response.status);
        
        // Obtener el texto de la respuesta primero
        const textResponse = await response.text();
        console.log('📥 Respuesta texto:', textResponse.substring(0, 500)); // Primeros 500 caracteres
        
        // Intentar parsear como JSON
        try {
            const result = JSON.parse(textResponse);
            console.log('📥 Respuesta JSON:', result);
            
            if (!result.success) {
                console.error('❌ Error registrando auditoría:', result.error);
            } else {
                console.log('✅ Auditoría registrada exitosamente');
            }
        } catch (jsonError) {
            console.error('❌ La respuesta NO es JSON válido:', jsonError);
            console.error('Contenido de la respuesta:', textResponse);
            
            // Mostrar alerta con el error para que lo veas
            alert('Error en auditoría - Revisa consola para detalles');
        }
        
    } catch (error) {
        console.error('❌ Error en fetch:', error);
    }
}

// ========== INICIALIZACIÓN ==========
document.addEventListener('DOMContentLoaded', function() {
    const btnBuscar = document.getElementById("btnBuscar");
    const numeroRecepcion = document.getElementById("numeroRecepcion");
    
    if (btnBuscar) {
        btnBuscar.addEventListener("click", buscarRecepcion);
    }
    
    if (numeroRecepcion) {
        numeroRecepcion.addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                buscarRecepcion();
            }
        });
        
        numeroRecepcion.addEventListener("focus", function() {
            this.select();
        });
    }
    
    // Rotar placeholders
    const ejemplos = ["60820241326", "60820241327", "60820241328"];
    let ejemploIndex = 0;
    
    setInterval(() => {
        if (numeroRecepcion) {
            numeroRecepcion.placeholder = `🔍 Ingrese número de Recepción (ej: ${ejemplos[ejemploIndex]})`;
            ejemploIndex = (ejemploIndex + 1) % ejemplos.length;
        }
    }, 3000);
});