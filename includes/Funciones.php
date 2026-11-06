<?php
declare(strict_types=1);

/**
 * Funciones auxiliares de interfaz, formato y seguridad XSS para LibreShop.
 * Reproducen fielmente los componentes visuales originales de Figma Make.
 */

/**
 * Escapa texto de forma segura para prevenir ataques XSS en salidas HTML.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Formatea una cantidad numérica en euros con coma decimal (ej. "19,90 €").
 */
function formatoPrecio(float|int|string $valor): string
{
    return number_format((float) $valor, 2, ',', '.') . ' €';
}

/**
 * Formatea una fecha SQL en español al estilo del diseño de Figma Make.
 */
function formatoFechaEspanol(?string $fechaSql, bool $formatoCorto = false): string
{
    $ts = $fechaSql !== null && $fechaSql !== '' ? strtotime($fechaSql) : time();
    if ($ts === false) {
        $ts = time();
    }

    $mesesLargos = [
        1  => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5  => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9  => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];
    $mesesCortos = [
        1  => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5  => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9  => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    $dia  = date('d', $ts);
    $mes  = (int) date('n', $ts);
    $anio = date('Y', $ts);

    if ($formatoCorto) {
        return sprintf('%s %s %s', $dia, $mesesCortos[$mes] ?? 'ene', $anio);
    }

    return sprintf('%d de %s de %s', (int) $dia, $mesesLargos[$mes] ?? 'enero', $anio);
}

/**
 * Renderiza la etiqueta visual del estado de un pedido diferenciando:
 * Pendiente, Enviado, Entregado y Cancelado.
 */
function renderizarEstadoPedido(string $estado): string
{
    $clases = match ($estado) {
        'Enviado'   => 'status sent',
        'Entregado' => 'status delivered',
        'Cancelado' => 'status cancelled',
        default     => 'status',
    };

    return '<em class="' . $clases . '">' . e($estado) . '</em>';
}

/**
 * Renderiza cualquiera de los iconos SVG vectoriales del diseño original de Figma Make.
 */
function icono(string $nombre, int $tamano = 20, bool $relleno = false): string
{
    $trazos = [
        'search'  => '<circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />',
        'cart'    => '<path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L20 8H7" /><circle cx="10" cy="20" r="1" /><circle cx="17" cy="20" r="1" />',
        'user'    => '<circle cx="12" cy="8" r="4" /><path d="M4.8 20a7.2 7.2 0 0 1 14.4 0" />',
        'chevron' => '<path d="m9 18 6-6-6-6" />',
        'arrow'   => '<path d="M5 12h14" /><path d="m13 6 6 6-6 6" />',
        'back'    => '<path d="M19 12H5" /><path d="m11 18-6-6 6-6" />',
        'star'    => '<path d="m12 2.5 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3.1-5.8 3.1 1.1-6.5-4.7-4.6 6.5-.9z" />',
        'trash'   => '<path d="M4 7h16M9 7V4h6v3m3 0-1 14H7L6 7m4 4v6m4-6v6" />',
        'check'   => '<path d="m5 12 4 4L19 6" />',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16" />',
        'close'   => '<path d="m6 6 12 12M18 6 6 18" />',
        'edit'    => '<path d="m4 20 4.2-1 10.7-10.7a2 2 0 0 0-2.8-2.8L5.4 16.2 4 20Z" /><path d="m14.5 7.1 2.8 2.8" />',
        'plus'    => '<path d="M12 5v14M5 12h14" />',
        'box'     => '<path d="m4 7 8-4 8 4-8 4-8-4Z" /><path d="m4 7 0 10 8 4 8-4V7M12 11v10" />',
        'upload'  => '<path d="M12 16V4m-4 4 4-4 4 4" /><path d="M5 15v5h14v-5" />',
        'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" />',
    ];

    $contenido = $trazos[$nombre] ?? '';
    $fill      = $relleno ? 'currentColor' : 'none';

    return sprintf(
        '<svg width="%d" height="%d" viewBox="0 0 24 24" fill="%s" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        $tamano,
        $tamano,
        $fill,
        $contenido
    );
}

/**
 * Renderiza el logotipo oficial de LibreShop idéntico al componente <Logo /> de Figma Make.
 */
function renderizarLogo(string $urlInicio = 'index.php'): string
{
    return sprintf(
        '<a href="%s" class="flex items-center gap-2.5 text-left no-underline">
            <span class="relative grid h-9 w-9 place-items-center rounded-lg bg-[#1760DC] text-white shadow-sm">
                <span class="absolute h-4 w-[2px] bg-white/60"></span>
                <span class="h-5 w-4 rounded-l-sm border-2 border-white border-r-0"></span>
                <span class="h-5 w-4 rounded-r-sm border-2 border-white border-l-0"></span>
            </span>
            <span class="text-[21px] font-bold tracking-[-.04em] text-[#142943]">
                Libre<span class="text-[#1760DC]">Shop</span>
            </span>
        </a>',
        e($urlInicio)
    );
}

/**
 * Renderiza la portada de un libro reproduciendo el componente <Cover /> de Figma Make.
 *
 * @param array<string, mixed> $producto
 */
function renderizarPortada(array $producto, string $clasesExtra = '', string $rutaBase = ''): string
{
    $color1 = e($producto['color_primario'] ?? '#0d223c');
    $color2 = e($producto['color_secundario'] ?? '#d89d4a');
    $autor  = e($producto['autor'] ?? 'LibreShop');
    $sello  = e($producto['sello_editorial'] ?? 'LIBRO');
    $titulo = e($producto['titulo'] ?? 'Libro');
    $imagen = trim((string) ($producto['imagen'] ?? ''));

    $urlImagen = '';
    if ($imagen !== '') {
        if (preg_match('#^https?://#i', $imagen)) {
            $urlImagen = $imagen;
        } else {
            $urlImagen = $rutaBase . ltrim($imagen, '/');
        }
    }

    if ($urlImagen !== '') {
        return sprintf(
            '<div class="book-cover relative isolate overflow-hidden rounded-[3px] bg-slate-100 shadow-[8px_10px_22px_rgba(15,31,52,.22)] %s">
                <img src="%s" alt="Portada de %s" class="absolute inset-0 h-full w-full object-cover" loading="lazy" />
                <div class="absolute inset-y-0 left-0 w-[7%%] bg-black/20"></div>
            </div>',
            e($clasesExtra),
            e($urlImagen),
            $titulo
        );
    }

    return sprintf(
        '<div class="book-cover relative isolate overflow-hidden rounded-[3px] shadow-[8px_10px_22px_rgba(15,31,52,.22)] %s" style="background: linear-gradient(145deg, %s, %s)">
            <div class="absolute inset-y-0 left-0 w-[7%%] bg-black/15"></div>
            <div class="absolute -right-8 top-[14%%] h-28 w-28 rounded-full border border-white/35"></div>
            <div class="absolute -right-3 top-[22%%] h-16 w-16 rounded-full border border-white/20"></div>
            <div class="relative flex h-full flex-col px-[13%%] py-[14%%] text-white">
                <span class="text-[8px] font-semibold uppercase tracking-[.22em] opacity-80">%s</span>
                <strong class="mt-auto max-w-[90%%] text-[clamp(15px,2vw,24px)] font-bold leading-[.94] tracking-[-.06em]">%s</strong>
                <span class="mt-2 h-px w-7 bg-white/70"></span>
                <span class="mt-2 text-[7px] uppercase tracking-[.2em] opacity-80">LibreShop editorial</span>
            </div>
        </div>',
        e($clasesExtra),
        $color1,
        $color2,
        $autor,
        $sello
    );
}

/**
 * Renderiza el bloque de estrellas de valoración idéntico a <Rating /> de Figma Make.
 */
function renderizarValoracion(float|int|string $valor): string
{
    $puntuacion    = round((float) $valor, 1);
    $redondeado    = (int) round($puntuacion);
    $estrellasHtml = '';

    for ($n = 1; $n <= 5; $n++) {
        $estrellasHtml .= icono('star', 14, $n <= $redondeado);
    }

    return sprintf(
        '<div class="flex items-center gap-1 text-amber-400" aria-label="%s de 5">%s<span class="ml-1 text-xs font-semibold text-slate-500">%s</span></div>',
        e((string) $puntuacion),
        $estrellasHtml,
        e((string) $puntuacion)
    );
}

/**
 * Renderiza la tarjeta de un libro idéntica a <ProductCard /> de Figma Make,
 * con formulario POST protegido con CSRF para añadir directamente al carrito.
 *
 * @param array<string, mixed> $producto
 */
function renderizarTarjetaProducto(array $producto, string $rutaBase = ''): string
{
    $id          = (int) ($producto['id'] ?? 0);
    $titulo      = e($producto['titulo'] ?? '');
    $autor       = e($producto['autor'] ?? '');
    $categoria   = e($producto['categoria_nombre'] ?? 'Catálogo');
    $precioTexto = formatoPrecio((float) ($producto['precio'] ?? 0));
    $valoracion  = (float) ($producto['valoracion'] ?? 4.8);
    $stock       = (int) ($producto['stock'] ?? 0);
    $urlDetalle  = e($rutaBase . 'paginas/detalle.php?id=' . $id);
    $urlCarrito  = e($rutaBase . 'paginas/carrito.php');
    $portadaHtml = renderizarPortada($producto, 'h-full aspect-[.68]', $rutaBase);
    $ratingHtml  = renderizarValoracion($valoracion);
    $iconoCarro  = icono('cart', 17);
    $csrfHtml    = function_exists('campoCSRF') ? campoCSRF() : '';

    if ($stock > 0) {
        $botonAccion = <<<HTML
        <form method="post" action="{$urlCarrito}" class="m-0">
            {$csrfHtml}
            <input type="hidden" name="accion" value="agregar" />
            <input type="hidden" name="producto_id" value="{$id}" />
            <input type="hidden" name="cantidad" value="1" />
            <button
                type="submit"
                class="grid h-9 w-9 place-items-center rounded-lg bg-[#1760DC] text-white transition hover:bg-[#114fb9]"
                aria-label="Añadir {$titulo}"
                title="Añadir «{$titulo}» al carrito"
            >
                {$iconoCarro}
            </button>
        </form>
HTML;
    } else {
        $botonAccion = <<<HTML
        <span
            class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase text-slate-400"
            title="Sin stock disponible"
        >
            Agotado
        </span>
HTML;
    }

    return <<<HTML
    <article class="group flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 transition duration-300 hover:-translate-y-1 hover:border-blue-200 hover:shadow-xl hover:shadow-slate-200/70">
        <a href="{$urlDetalle}" class="grid aspect-[4/3] place-items-center rounded-lg bg-[#f3f5f7] p-4 no-underline">
            {$portadaHtml}
        </a>
        <div class="flex flex-1 flex-col pt-4">
            <span class="mb-1 text-[10px] font-bold uppercase tracking-[.14em] text-[#1760DC]">
                {$categoria}
            </span>
            <a href="{$urlDetalle}" class="text-left no-underline">
                <h3 class="line-clamp-1 font-bold tracking-[-.02em] text-[#142943] group-hover:text-[#1760DC]">
                    {$titulo}
                </h3>
            </a>
            <p class="mt-0.5 text-sm text-slate-500">{$autor}</p>
            <div class="mt-2">
                {$ratingHtml}
            </div>
            <div class="mt-auto flex items-end justify-between gap-3 pt-4">
                <strong class="text-lg text-[#142943]">{$precioTexto}</strong>
                {$botonAccion}
            </div>
        </div>
    </article>
HTML;
}
