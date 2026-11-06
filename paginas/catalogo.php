<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/Categoria.php';
require_once __DIR__ . '/../clases/Producto.php';

$gestorCategorias = new Categoria();
$gestorProductos  = new Producto();

$categorias          = $gestorCategorias->obtenerTodas(true);
$categoriaSeleccionada = trim((string) ($_GET['categoria'] ?? 'Todos'));
$busqueda            = trim((string) ($_GET['q'] ?? ''));
$orden               = trim((string) ($_GET['orden'] ?? 'recomendados'));

$ordenesValidos = [
    'recomendados' => 'Recomendados',
    'precio_asc'   => 'Precio: menor a mayor',
    'precio_desc'  => 'Precio: mayor a menor',
    'titulo_asc'   => 'Título (A–Z)',
    'recientes'    => 'Más recientes',
];
if (!isset($ordenesValidos[$orden])) {
    $orden = 'recomendados';
}

$productos = $gestorProductos->obtenerTodos([
    'estado'           => 'activos',
    'categoria_nombre' => $categoriaSeleccionada,
    'q'                => $busqueda,
    'orden'            => $orden,
]);

$rutaBase     = '../';
$paginaActual = 'catalogo';
$tituloPagina = 'Catálogo de libros — LibreShop';

require_once __DIR__ . '/../includes/Cabecera.php';
?>

<main class="page py-10 lg:py-14">
    <div class="mb-9 max-w-2xl">
        <p class="eyebrow">Nuestra colección</p>
        <h1 class="page-title">Catálogo de libros</h1>
        <p class="mt-3 text-slate-500">
            Explora nuestra selección y encuentra la historia que estabas buscando.
        </p>
    </div>

    <div class="mb-8 flex flex-col justify-between gap-4 border-y border-slate-200 py-5 md:flex-row md:items-center">
        <div class="flex flex-wrap gap-2">
            <?php
            $urlTodos = 'catalogo.php' . ($busqueda !== '' ? '?q=' . urlencode($busqueda) : '');
            ?>
            <a
                href="<?= e($urlTodos) ?>"
                class="filter-pill <?= $categoriaSeleccionada === 'Todos' || $categoriaSeleccionada === '' ? 'active' : '' ?>"
            >
                Todos
            </a>
            <?php foreach ($categorias as $cat): ?>
                <?php
                $nombreCat = (string) $cat['nombre'];
                $paramsCat = ['categoria' => $nombreCat];
                if ($busqueda !== '') {
                    $paramsCat['q'] = $busqueda;
                }
                if ($orden !== 'recomendados') {
                    $paramsCat['orden'] = $orden;
                }
                $urlCat = 'catalogo.php?' . http_build_query($paramsCat);
                ?>
                <a
                    href="<?= e($urlCat) ?>"
                    class="filter-pill <?= $categoriaSeleccionada === $nombreCat ? 'active' : '' ?>"
                >
                    <?= e($nombreCat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="catalogo.php" class="flex flex-wrap items-center gap-2 w-full md:w-auto">
            <?php if ($categoriaSeleccionada !== 'Todos' && $categoriaSeleccionada !== ''): ?>
                <input type="hidden" name="categoria" value="<?= e($categoriaSeleccionada) ?>" />
            <?php endif; ?>
            <label class="relative block flex-1 md:w-80">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                    <?= icono('search', 18) ?>
                </span>
                <input
                    type="search"
                    name="q"
                    value="<?= e($busqueda) ?>"
                    placeholder="Buscar por título, autor o ISBN..."
                    class="input pl-10"
                />
            </label>
            <button type="submit" class="btn-primary px-4">Buscar</button>
            <?php if ($busqueda !== '' || ($categoriaSeleccionada !== 'Todos' && $categoriaSeleccionada !== '')): ?>
                <a href="catalogo.php" class="btn-secondary px-3" title="Limpiar filtros">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
        <span>
            <strong class="text-[#142943]"><?= count($productos) ?></strong>
            <?= count($productos) === 1 ? 'libro encontrado' : 'libros encontrados' ?>
            <?php if ($categoriaSeleccionada !== 'Todos' && $categoriaSeleccionada !== ''): ?>
                en <strong class="text-[#1760DC]"><?= e($categoriaSeleccionada) ?></strong>
            <?php endif; ?>
            <?php if ($busqueda !== ''): ?>
                para «<strong class="text-[#142943]"><?= e($busqueda) ?></strong>»
            <?php endif; ?>
        </span>

        <form method="get" action="catalogo.php" class="flex items-center gap-2">
            <?php if ($categoriaSeleccionada !== 'Todos' && $categoriaSeleccionada !== ''): ?>
                <input type="hidden" name="categoria" value="<?= e($categoriaSeleccionada) ?>" />
            <?php endif; ?>
            <?php if ($busqueda !== ''): ?>
                <input type="hidden" name="q" value="<?= e($busqueda) ?>" />
            <?php endif; ?>
            <label for="select-orden" class="text-xs font-semibold text-slate-500">Orden:</label>
            <select
                id="select-orden"
                name="orden"
                onchange="this.form.submit()"
                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-[#142943]"
            >
                <?php foreach ($ordenesValidos as $claveOrden => $etiquetaOrden): ?>
                    <option value="<?= e($claveOrden) ?>" <?= $orden === $claveOrden ? 'selected' : '' ?>>
                        <?= e($etiquetaOrden) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (!empty($productos)): ?>
        <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
            <?php foreach ($productos as $libro): ?>
                <?= renderizarTarjetaProducto($libro, '../') ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="rounded-xl bg-slate-50 py-20 text-center text-slate-500">
            <p class="text-lg font-bold text-[#142943]">No encontramos libros con esos criterios.</p>
            <p class="mt-2 text-sm">Prueba con otro término de búsqueda o explora todas las categorías.</p>
            <a href="catalogo.php" class="btn-primary mt-6">Ver todo el catálogo</a>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
