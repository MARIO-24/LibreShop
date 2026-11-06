/**
 * LibreShop — Interacciones de interfaz en cliente (assets/js/app.js)
 * Gestiona el menú móvil, selectores de cantidad, selección de método de pago,
 * prevención de doble envío en checkout, modales de administración y vista previa de portadas.
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

    // 3. Selector de método de pago en Checkout (Tarjeta / PayPal)
    const botonesPago = document.querySelectorAll('[data-metodo-pago]');
    const inputMetodoPago = document.getElementById('input-metodo-pago');

    if (botonesPago.length > 0 && inputMetodoPago) {
        botonesPago.forEach((btn) => {
            btn.addEventListener('click', () => {
                const metodo = btn.getAttribute('data-metodo-pago') || 'Tarjeta';
                inputMetodoPago.value = metodo;

                botonesPago.forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }

    // 4. Prevención de doble clic al enviar el formulario de checkout
    const formCheckout = document.getElementById('form-checkout');
    const btnSubmitCheckout = document.getElementById('btn-confirmar-pedido');
    if (formCheckout && btnSubmitCheckout) {
        formCheckout.addEventListener('submit', () => {
            if (formCheckout.checkValidity()) {
                btnSubmitCheckout.setAttribute('disabled', 'disabled');
                btnSubmitCheckout.style.opacity = '0.75';
            }
        });
    }

    // 5. Modales en el panel de administración
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

    // 6. Previsualización en tiempo real de la portada en el formulario de producto
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

    // 7. Confirmación antes de acciones destructivas
    document.querySelectorAll('form[data-confirmar]').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            const mensaje = formulario.getAttribute('data-confirmar') || '¿Estás seguro de realizar esta acción?';
            if (!window.confirm(mensaje)) {
                evento.preventDefault();
            }
        });
    });
});
