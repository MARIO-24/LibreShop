<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/Producto.php';

$gestorProductos = new Producto();
$idProducto      = isset($_GET['id']) ? (int) $_GET['id'] : 1;

$producto = $idProducto > 0 ? $gestorProductos->obtenerPorId($idProducto, true) : null;
if ($producto === null && !headers_sent()) {
    http_response_code(404);
}
$relacionados = [];
if ($producto !== null) {
    $relacionados = $gestorProductos->obtenerRelacionados(
        (int) $producto['categoria_id'],
        (int) $producto['id'],
        4
    );
}

$rutaBase     = '../';
$paginaActual = 'catalogo';
$tituloPagina = $producto !== null
    ? $producto['titulo'] . ' — LibreShop'
    : 'Libro no encontrado — LibreShop';

require_once __DIR__ . '/../includes/Cabecera.php';
?>

<main class="page py-8 lg:py-14">
    <a
        href="catalogo.php"
        class="mb-8 inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-[#1760DC] no-underline"
    >
        <?= icono('back', 17) ?> Volver al catálogo
    </a>

    <?php if ($producto === null): ?>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 py-20 text-center">
            <h1 class="text-2xl font-bold text-[#142943]">El libro solicitado no está disponible</h1>
            <p class="mt-2 text-sm text-slate-500">Puede que haya sido desactivado o que el enlace no sea válido.</p>
            <a href="catalogo.php" class="btn-primary mt-6">Explorar catálogo</a>
        </div>
    <?php else: ?>
        <?php
        $stockDisponible = (int) $producto['stock'];
        $hayStock        = $stockDisponible > 0;
        ?>
        <div class="grid gap-10 lg:grid-cols-[.85fr_1.15fr] lg:gap-20">
            <div class="grid min-h-[460px] place-items-center rounded-2xl bg-[#f2f4f6] p-10">
                <?= renderizarPortada($producto, 'h-[400px] max-h-[60vh] aspect-[.68]', '../') ?>
            </div>
            <div class="flex flex-col justify-center">
                <div class="flex flex-wrap items-center gap-3">
                    <a
                        href="catalogo.php?categoria=<?= urlencode((string) $producto['categoria_nombre']) ?>"
                        class="eyebrow no-underline"
                    >
                        <?= e($producto['categoria_nombre']) ?>
                    </a>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">
                        ISBN: <?= e($producto['isbn']) ?>
                    </span>
                </div>
                <h1 class="mt-2 text-[clamp(34px,5vw,52px)] font-bold leading-[1.06] tracking-[-.045em] text-[#142943]">
                    <?= e($producto['titulo']) ?>
                </h1>
                <p class="mt-3 text-lg text-slate-500">
                    de <span class="font-semibold text-slate-700"><?= e($producto['autor']) ?></span>
                </p>
                <div class="mt-5 flex items-center gap-3">
                    <?= renderizarValoracion((float) $producto['valoracion']) ?>
                    <span class="text-xs text-slate-400">128 valoraciones</span>
                </div>
                <div class="my-7 h-px bg-slate-200"></div>
                <p class="leading-7 text-slate-600"><?= nl2br(e($producto['descripcion'])) ?></p>
                <p class="mt-5 text-sm leading-6 text-slate-500">
                    Una edición cuidada, impresa en papel de alta calidad y pensada para acompañarte durante
                    muchas lecturas.
                </p>
                <div class="mt-8 flex items-center justify-between">
                    <strong class="text-3xl tracking-[-.03em] text-[#142943]">
                        <?= formatoPrecio((float) $producto['precio']) ?>
                    </strong>
                    <?php if ($hayStock): ?>
                        <span class="flex items-center gap-2 text-sm font-semibold text-emerald-700">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            En stock · <?= $stockDisponible ?> <?= $stockDisponible === 1 ? 'unidad' : 'unidades' ?>
                        </span>
                    <?php else: ?>
                        <span class="flex items-center gap-2 text-sm font-semibold text-red-600">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                            Temporalmente sin stock
                        </span>
                    <?php endif; ?>
                </div>

                <form method="post" action="carrito.php" class="mt-6 flex gap-3">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="agregar" />
                    <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>" />
                    <input
                        type="hidden"
                        id="input-cantidad"
                        name="cantidad"
                        value="1"
                        min="1"
                        max="<?= max(1, $stockDisponible) ?>"
                    />
                    <div class="flex h-12 items-center rounded-lg border border-slate-300 bg-white">
                        <button type="button" id="btn-qty-menos" class="h-full px-4 text-xl" aria-label="Reducir cantidad">
                            −
                        </button>
                        <span id="display-cantidad" class="w-8 text-center font-semibold">1</span>
                        <button type="button" id="btn-qty-mas" class="h-full px-4 text-xl" aria-label="Aumentar cantidad">
                            +
                        </button>
                    </div>
                    <button
                        type="submit"
                        class="btn-primary flex-1 justify-center"
                        <?= !$hayStock ? 'disabled style="opacity:.5;cursor:not-allowed;"' : '' ?>
                    >
                        <?= icono('cart', 19) ?>
                        <?= $hayStock ? 'Añadir al carrito' : 'Sin stock disponible' ?>
                    </button>
                </form>

                <div class="mt-6 grid grid-cols-2 gap-4 rounded-xl bg-slate-50 p-4 text-xs text-slate-500">
                    <span>
                        <strong class="block text-slate-700">Envío 24–48 h</strong>Gratis desde 35 €
                    </span>
                    <span>
                        <strong class="block text-slate-700">Devolución sencilla</strong>Hasta 30 días
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($relacionados)): ?>
            <section class="mt-16 border-t border-slate-200 pt-12">
                <div class="mb-7 flex items-end justify-between gap-4">
                    <div>
                        <p class="eyebrow">Más en <?= e($producto['categoria_nombre']) ?></p>
                        <h2 class="title">También te puede interesar</h2>
                    </div>
                    <a href="catalogo.php?categoria=<?= urlencode((string) $producto['categoria_nombre']) ?>" class="link-button">
                        Ver categoría <?= icono('arrow', 16) ?>
                    </a>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                    <?php foreach ($relacionados as $rel): ?>
                        <?= renderizarTarjetaProducto($rel, '../') ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
