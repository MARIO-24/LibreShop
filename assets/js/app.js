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

    // 6. Previsualización en tiempo real de la portada y gestión dinámica de imagen/personalización
    const inputTitulo = document.getElementById('prod-titulo');
    const inputAutor = document.getElementById('prod-autor');
    const inputSello = document.getElementById('prod-sello');
    const inputColor1 = document.getElementById('prod-color1');
    const inputColor2 = document.getElementById('prod-color2');
    const inputArchivo = document.getElementById('prod-imagen-archivo');
    const textoArchivo = document.getElementById('nombre-archivo-portada');
    const inputImagenActual = document.getElementById('input-imagen-actual');
    const inputEliminarImagen = document.getElementById('input-eliminar-imagen');
    const zonaSelectorImagen = document.getElementById('zona-selector-imagen');
    const panelImagenActiva = document.getElementById('panel-imagen-activa');
    const thumbImagenActiva = document.getElementById('thumb-imagen-activa');
    const estadoImagenActiva = document.getElementById('estado-imagen-activa');
    const nombreImagenActiva = document.getElementById('nombre-imagen-activa');
    const btnCambiarImagen = document.getElementById('btn-cambiar-imagen');
    const btnRetirarImagen = document.getElementById('btn-retirar-imagen');
    const textoBtnCambiar = document.getElementById('texto-btn-cambiar');
    const textoBtnRetirar = document.getElementById('texto-btn-retirar');
    const seccionPersonalizacion = document.getElementById('seccion-personalizacion-cubierta');
    const bloqueColores = document.getElementById('bloque-colores-cubierta');
    const bloqueExtra = document.getElementById('bloque-personalizacion-extra');
    const avisoImagen = document.getElementById('aviso-imagen-portada');
    const previewCover = document.getElementById('preview-book-cover');
    const previewImg = document.getElementById('preview-book-img');
    const previewEditorial = document.getElementById('preview-editorial-content');
    const previewAutor = document.getElementById('preview-book-autor');
    const previewSello = document.getElementById('preview-book-sello');
    const previewBadge = document.getElementById('preview-modo-badge');

    let urlObjetoTemporal = null;

    const limpiarUrlTemporal = () => {
        if (urlObjetoTemporal) {
            URL.revokeObjectURL(urlObjetoTemporal);
            urlObjetoTemporal = null;
        }
    };

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

    const activarModoEditorial = () => {
        if (seccionPersonalizacion) seccionPersonalizacion.classList.remove('hidden');
        if (bloqueColores) bloqueColores.classList.remove('hidden');
        if (bloqueExtra) bloqueExtra.classList.remove('hidden');

        if (zonaSelectorImagen) zonaSelectorImagen.classList.remove('hidden');
        if (panelImagenActiva) panelImagenActiva.classList.add('hidden');
        if (textoArchivo) {
            textoArchivo.textContent = 'Subir imagen de portada (JPG, PNG, WebP · máx. 3 MB)';
        }

        if (previewImg) {
            previewImg.classList.add('hidden');
            previewImg.removeAttribute('src');
        }
        if (previewEditorial) {
            previewEditorial.classList.remove('hidden');
        }
        if (previewBadge) {
            previewBadge.textContent = 'Cubierta editorial';
            previewBadge.className = 'mt-2 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[.08em] bg-slate-100 text-slate-600';
        }

        actualizarPreview();
    };

    const activarModoImagen = (urlPreview, nombreMostrar, textoEstado, esGuardada) => {
        if (seccionPersonalizacion) seccionPersonalizacion.classList.add('hidden');
        if (bloqueColores) bloqueColores.classList.add('hidden');
        if (bloqueExtra) bloqueExtra.classList.add('hidden');

        if (zonaSelectorImagen) zonaSelectorImagen.classList.add('hidden');
        if (panelImagenActiva) panelImagenActiva.classList.remove('hidden');

        if (thumbImagenActiva && urlPreview) {
            thumbImagenActiva.src = urlPreview;
        }
        if (estadoImagenActiva) {
            estadoImagenActiva.textContent = textoEstado;
        }
        if (nombreImagenActiva) {
            nombreImagenActiva.textContent = nombreMostrar;
        }
        if (textoBtnCambiar) {
            textoBtnCambiar.textContent = esGuardada ? 'Sustituir imagen' : 'Cambiar imagen';
        }
        if (textoBtnRetirar) {
            textoBtnRetirar.textContent = esGuardada ? 'Eliminar portada' : 'Retirar imagen';
        }

        if (previewImg && urlPreview) {
            previewImg.src = urlPreview;
            previewImg.classList.remove('hidden');
        }
        if (previewEditorial) {
            previewEditorial.classList.add('hidden');
        }
        if (previewBadge) {
            previewBadge.textContent = 'Imagen de portada';
            previewBadge.className = 'mt-2 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[.08em] bg-blue-50 text-[#1760DC]';
        }
    };

    [inputTitulo, inputAutor, inputSello, inputColor1, inputColor2].forEach((el) => {
        if (el) el.addEventListener('input', actualizarPreview);
    });

    if (inputArchivo) {
        inputArchivo.addEventListener('change', () => {
            if (avisoImagen) {
                avisoImagen.classList.add('hidden');
                avisoImagen.textContent = '';
            }

            if (inputArchivo.files && inputArchivo.files.length > 0) {
                const archivo = inputArchivo.files[0];
                const mimesValidos = ['image/jpeg', 'image/png', 'image/webp'];
                const ext = (archivo.name.split('.').pop() || '').toLowerCase();
                const extsValidas = ['jpg', 'jpeg', 'png', 'webp'];
                const maxBytes = 3 * 1024 * 1024;

                if (!mimesValidos.includes(archivo.type) || !extsValidas.includes(ext)) {
                    if (avisoImagen) {
                        avisoImagen.textContent = 'Atención: formato no permitido. Solo se aceptan imágenes JPG, PNG o WebP.';
                        avisoImagen.classList.remove('hidden');
                    }
                } else if (archivo.size > maxBytes) {
                    if (avisoImagen) {
                        avisoImagen.textContent = 'Atención: la imagen seleccionada supera el límite máximo de 3 MB.';
                        avisoImagen.classList.remove('hidden');
                    }
                }

                limpiarUrlTemporal();
                urlObjetoTemporal = URL.createObjectURL(archivo);
                if (inputEliminarImagen) {
                    inputEliminarImagen.value = '0';
                }

                const kb = Math.max(1, Math.round(archivo.size / 1024));
                activarModoImagen(
                    urlObjetoTemporal,
                    `${archivo.name} (${kb} KB)`,
                    'Nueva portada seleccionada',
                    false
                );
            } else {
                limpiarUrlTemporal();
                const tieneGuardada = inputImagenActual && inputImagenActual.value.trim() !== '';
                const marcadaEliminar = inputEliminarImagen && inputEliminarImagen.value === '1';

                if (tieneGuardada && !marcadaEliminar && panelImagenActiva) {
                    const urlInicial = panelImagenActiva.getAttribute('data-imagen-inicial') || '';
                    const nombreInicial = panelImagenActiva.getAttribute('data-nombre-inicial') || '';
                    activarModoImagen(urlInicial, nombreInicial, 'Portada actual guardada', true);
                } else {
                    activarModoEditorial();
                }
            }
        });
    }

    if (btnCambiarImagen && inputArchivo) {
        btnCambiarImagen.addEventListener('click', () => {
            inputArchivo.click();
        });
    }

    if (btnRetirarImagen) {
        btnRetirarImagen.addEventListener('click', () => {
            limpiarUrlTemporal();
            if (inputArchivo) {
                inputArchivo.value = '';
            }
            if (avisoImagen) {
                avisoImagen.classList.add('hidden');
                avisoImagen.textContent = '';
            }
            if (inputImagenActual && inputImagenActual.value.trim() !== '' && inputEliminarImagen) {
                inputEliminarImagen.value = '1';
            }
            activarModoEditorial();
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
