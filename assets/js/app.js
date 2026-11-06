/**
 * LibreShop — Interacciones de interfaz en cliente (assets/js/app.js)
 * Gestiona el menú móvil, selectores de cantidad, modales de administración
 * y previsualización en vivo de portadas de libros.
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. Menú de navegación móvil
    const btnMenu = document.getElementById('btn-menu-movil');
    const navMovil = document.getElementById('nav-movil');
    const iconoAbrir = document.getElementById('icono-menu-abrir');
    const iconoCerrar = document.getElementById('icono-menu-cerrar');

    if (btnMenu && navMovil) {
        btnMenu.addEventListener('click', () => {
            const estaOculto = navMovil.classList.contains('hidden');
            navMovil.classList.toggle('hidden', !estaOculto);
            btnMenu.setAttribute('aria-expanded', estaOculto ? 'true' : 'false');
            if (iconoAbrir && iconoCerrar) {
                iconoAbrir.classList.toggle('hidden', estaOculto);
                iconoCerrar.classList.toggle('hidden', !estaOculto);
            }
        });
    }

    // 2. Selector de cantidad en página de detalle (- / +)
    const btnMenos = document.getElementById('btn-qty-menos');
    const btnMas = document.getElementById('btn-qty-mas');
    const inputQty = document.getElementById('input-cantidad');
    const displayQty = document.getElementById('display-cantidad');

    if (btnMenos && btnMas && inputQty && displayQty) {
        const actualizarCantidad = (delta) => {
            const min = parseInt(inputQty.getAttribute('min') || '1', 10);
            const max = parseInt(inputQty.getAttribute('max') || '99', 10);
            let actual = parseInt(inputQty.value || '1', 10);
            actual = Math.max(min, Math.min(max, actual + delta));
            inputQty.value = String(actual);
            displayQty.textContent = String(actual);
        };

        btnMenos.addEventListener('click', () => actualizarCantidad(-1));
        btnMas.addEventListener('click', () => actualizarCantidad(1));
    }

    // 3. Modales en el panel de administración
    document.querySelectorAll('[data-abrir-modal]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const idModal = boton.getAttribute('data-abrir-modal');
            const modal = idModal ? document.getElementById(idModal) : null;
            if (modal) {
                modal.classList.remove('hidden');
            }
        });
    });

    document.querySelectorAll('[data-cerrar-modal]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const idModal = boton.getAttribute('data-cerrar-modal');
            const modal = idModal ? document.getElementById(idModal) : null;
            if (modal) {
                modal.classList.add('hidden');
            }
        });
    });

    // 4. Previsualización en tiempo real de la portada en el formulario de producto
    const inputTitulo = document.getElementById('prod-titulo');
    const inputAutor = document.getElementById('prod-autor');
    const inputSello = document.getElementById('prod-sello');
    const inputColor1 = document.getElementById('prod-color1');
    const inputColor2 = document.getElementById('prod-color2');
    const inputArchivo = document.getElementById('prod-imagen-archivo');
    const textoArchivo = document.getElementById('nombre-archivo-portada');
    const previewCover = document.getElementById('preview-book-cover');
    const previewAutor = document.getElementById('preview-book-autor');
    const previewSello = document.getElementById('preview-book-sello');

    const actualizarPreview = () => {
        if (!previewCover) return;
        const c1 = inputColor1 ? inputColor1.value : '#0d223c';
        const c2 = inputColor2 ? inputColor2.value : '#d89d4a';
        previewCover.style.background = `linear-gradient(145deg, ${c1}, ${c2})`;

        if (previewAutor && inputAutor) {
            previewAutor.textContent = inputAutor.value.trim() || 'LibreShop';
        }
        if (previewSello) {
            const selloManual = inputSello ? inputSello.value.trim() : '';
            const tituloTexto = inputTitulo ? inputTitulo.value.trim() : '';
            previewSello.textContent = (selloManual || tituloTexto.split(/\s+/).slice(0, 2).join(' ') || 'NUEVO LIBRO').toUpperCase();
        }
    };

    [inputTitulo, inputAutor, inputSello, inputColor1, inputColor2].forEach((el) => {
        if (el) el.addEventListener('input', actualizarPreview);
    });

    if (inputArchivo && textoArchivo) {
        inputArchivo.addEventListener('change', () => {
            if (inputArchivo.files && inputArchivo.files.length > 0) {
                textoArchivo.textContent = 'Archivo seleccionado: ' + inputArchivo.files[0].name;
            } else {
                textoArchivo.textContent = 'Subir imagen de portada (JPG, PNG, WebP · máx. 3 MB)';
            }
        });
    }

    // 5. Confirmación antes de acciones destructivas
    document.querySelectorAll('form[data-confirmar]').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            const mensaje = formulario.getAttribute('data-confirmar') || '¿Estás seguro de realizar esta acción?';
            if (!window.confirm(mensaje)) {
                evento.preventDefault();
            }
        });
    });
});
