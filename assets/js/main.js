// ========== CORRECCIÓN DE URL ==========
// Esto asegura que siempre uses la URL correcta
(function() {
    const currentUrl = window.location.href;
    
    // Si la URL contiene "C:/" o "file://", redirige automáticamente
    if (currentUrl.includes('C:/') || currentUrl.includes('file://')) {
        window.location.href = 'http://localhost/sftp-liris/index.php';
        return;
    }
})();

function navegarA(modulo) {
    const card = document.getElementById(modulo === 'ordenes-compra' ? 'ordenesCompra' : 'despachos');
    card.style.transform = 'scale(0.95)';
    card.style.opacity = '0.8';

    setTimeout(() => {
        if (modulo === 'ordenes-compra') {
            // 🔴 CAMBIO IMPORTANTE: Ruta absoluta
            window.location.href = "/sftp-liris/pages/recepcion_asn.html";
        } 
        else if (modulo === 'despachos') {
            // 🔴 CAMBIO IMPORTANTE: Ruta absoluta
            window.location.href = "/sftp-liris/pages/despachos1.html";
        }
    }, 200);
}

// ========== INICIALIZACIÓN ==========
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si estamos en la URL correcta
    const currentUrl = window.location.href;
    console.log('URL actual:', currentUrl);
    
    if (currentUrl.includes('C:/') || currentUrl.includes('file://')) {
        console.warn('⚠️ Estás accediendo directamente desde el sistema de archivos');
        console.warn('✅ Debes acceder usando: http://localhost/sftp-liris/index.php');
        
        // Mostrar mensaje de advertencia en la página
        const warningDiv = document.createElement('div');
        warningDiv.style.cssText = `
            position: fixed;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: #e74c3c;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            z-index: 9999;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        `;
        warningDiv.innerHTML = '⚠️ Usa http://localhost/sftp-liris/index.php en lugar de la ruta del explorador';
        document.body.appendChild(warningDiv);
        
        setTimeout(() => {
            warningDiv.remove();
        }, 8000);
    }
    
    // Efecto de animación al cargar
    const elementos = document.querySelectorAll('.animate-in');
    elementos.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Asignar eventos de clic a las tarjetas
    const ordenesCompra = document.getElementById('ordenesCompra');
    const despachos = document.getElementById('despachos');
    
    if (ordenesCompra) {
        ordenesCompra.addEventListener('click', function() {
            navegarA('ordenes-compra');
        });
    }
    
    if (despachos) {
        despachos.addEventListener('click', function() {
            navegarA('despachos');
        });
    }
    
    // Navegación por teclado (opcional)
    document.addEventListener('keydown', function(e) {
        if (e.key === '1' || e.key === 'Numpad1') {
            navegarA('ordenes-compra');
        } else if (e.key === '2' || e.key === 'Numpad2') {
            navegarA('despachos');
        }
    });
    
    // Mostrar atajo de teclado en hover
    const cards = document.querySelectorAll('.option-card');
    if (cards.length > 0) {
        cards[0].title = 'Presiona 1 para acceder rápidamente';
        cards[1].title = 'Presiona 2 para acceder rápidamente';
    }
});

// ========== FUNCIÓN PARA AGREGAR BOTÓN DE VOLVER ==========
function agregarBotonVolver() {
    if (document.querySelector('.back-button')) return;
    
    const botonVolver = document.createElement('button');
    botonVolver.className = 'back-button';
    botonVolver.innerHTML = '<i class="fas fa-arrow-left"></i> Volver al inicio';
    
    // Detectar la base URL para volver
    const pathParts = window.location.pathname.split('/');
    const projectFolder = pathParts[1];
    
    botonVolver.onclick = function() {
        window.location.href = `/${projectFolder}/index.php`;
    };
    document.body.appendChild(botonVolver);
}