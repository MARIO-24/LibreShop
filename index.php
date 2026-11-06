<?php
declare(strict_types=1);

require_once __DIR__ . '/clases/Categoria.php';
require_once __DIR__ . '/clases/Producto.php';

$gestorCategorias = new Categoria();
$gestorProductos  = new Producto();

$categorias         = $gestorCategorias->obtenerTodas(true);
$productosDestacados = $gestorProductos->obtenerDestacados(4);
$libroDelMes        = $productosDestacados[0] ?? null;

$rutaBase     = '';
$paginaActual = 'inicio';
$tituloPagina = 'LibreShop — Tienda online de libros';

require_once __DIR__ . '/includes/Cabecera.php';
?>

<section class="overflow-hidden bg-[#f2f5f8]">
    <div class="page grid min-h-[520px] items-center gap-10 py-12 lg:grid-cols-[1.02fr_.98fr] lg:py-16">
        <div class="relative z-10 max-w-xl">
            <span class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1.5 text-xs font-bold uppercase tracking-[.13em] text-[#1760DC]">
                <span class="h-1.5 w-1.5 rounded-full bg-[#1760DC]"></span> Historias que dejan huella
            </span>
            <h1 class="mt-6 text-[clamp(42px,6vw,68px)] font-bold leading-[1.02] tracking-[-.055em] text-[#142943]">
                Tu próxima gran historia empieza aquí.
            </h1>
            <p class="mt-5 max-w-lg text-base leading-7 text-slate-600">
                Descubre libros que inspiran, emocionan y transforman. Una selección cuidada para cada
                tipo de lector.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="paginas/catalogo.php" class="btn-primary">
                    Explorar catálogo <?= icono('arrow', 17) ?>
                </a>
                <a href="paginas/catalogo.php?categoria=<?= urlencode($categorias[0]['nombre'] ?? 'Ficción') ?>" class="btn-secondary">
                    Ver novedades
                </a>
            </div>
            <div class="mt-9 flex items-center gap-7 text-xs font-semibold text-slate-500">
                <span class="flex items-center gap-2">
                    <span class="grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-700">
                        <?= icono('check', 14) ?>
                    </span>
                    Envío gratis +35 €
                </span>
                <span class="flex items-center gap-2">
                    <span class="grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-700">
                        <?= icono('check', 14) ?>
                    </span>
                    Compra segura
                </span>
            </div>
        </div>
        <div class="relative min-h-[370px] overflow-hidden rounded-[28px]">
            <img
                src="https://images.unsplash.com/photo-1566131807516-e3b3cd1a89d1?auto=format&fit=crop&w=1200&q=85"
                alt="Interior de una librería contemporánea"
                class="absolute inset-0 h-full w-full object-cover"
            />
            <div class="absolute inset-0 bg-gradient-to-tr"></div>
            <?php if ($libroDelMes !== null): ?>
                <a
                    href="paginas/detalle.php?id=<?= (int) $libroDelMes['id'] ?>"
                    class="absolute bottom-5 left-5 rounded-xl bg-white/95 p-4 shadow-xl backdrop-blur no-underline transition hover:-translate-y-1"
                >
                    <p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#1760DC]">
                        Libro del mes
                    </p>
                    <p class="mt-1 font-bold text-[#142943]"><?= e($libroDelMes['titulo']) ?></p>
                    <p class="text-xs text-slate-500">de <?= e($libroDelMes['autor']) ?> · <?= formatoPrecio((float) $libroDelMes['precio']) ?></p>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="page py-16 lg:py-20">
    <div class="mb-7 flex items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-[.18em] text-[#1760DC]">
                Encuentra tu lectura
            </p>
            <h2 class="title">Explora por categorías</h2>
        </div>
    </div>
    <div class="grid gap-4 md:grid-cols-3">
        <?php foreach ($categorias as $indice => $cat): ?>
            <?php
            $numero = str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT);
            $fondo  = e($cat['color_fondo'] ?? '#e9f1ff');
            ?>
            <a
                href="paginas/catalogo.php?categoria=<?= urlencode((string) $cat['nombre']) ?>"
                class="group relative overflow-hidden rounded-xl p-6 text-left transition hover:-translate-y-1 no-underline"
                style="background: <?= $fondo ?>"
            >
                <span class="absolute right-4 top-1 text-7xl font-black text-[#142943]/5">
                    <?= e($numero) ?>
                </span>
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-white text-[#1760DC] shadow-sm">
                    <?= icono('box', 20) ?>
                </span>
                <h3 class="mt-8 text-xl font-bold text-[#142943]"><?= e($cat['nombre']) ?></h3>
                <p class="mt-1 text-sm text-slate-500"><?= e($cat['descripcion']) ?></p>
                <span class="mt-5 flex items-center gap-1 text-sm font-bold text-[#1760DC]">
                    Explorar (<?= (int) ($cat['total_productos'] ?? 0) ?>) <?= icono('chevron', 15) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="bg-[#f7f8fa] py-16 lg:py-20">
    <div class="page">
        <div class="mb-7 flex items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[.18em] text-[#1760DC]">
                    Selección LibreShop
                </p>
                <h2 class="title">Libros destacados</h2>
            </div>
            <a href="paginas/catalogo.php" class="link-button">
                Ver todos <?= icono('arrow', 16) ?>
            </a>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
            <?php foreach ($productosDestacados as $libro): ?>
                <?= renderizarTarjetaProducto($libro, '') ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/Footer.php'; ?>
