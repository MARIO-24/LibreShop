<?php
declare(strict_types=1);

require_once __DIR__ . '/Sesiones.php';
require_once __DIR__ . '/Funciones.php';

$rutaBase      = $rutaBase ?? '';
$tituloPagina  = $tituloPagina ?? 'LibreShop — Tienda online de libros';
$paginaActual  = $paginaActual ?? 'inicio';
$mostrarHeader = $mostrarHeader ?? true;

$cantidadCarrito = 0;
if (!empty($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $cant) {
        $cantidadCarrito += (int) $cant;
    }
}

$usuarioSesion = $_SESSION['usuario'] ?? null;
$nombreCorto   = is_array($usuarioSesion) && !empty($usuarioSesion['nombre'])
    ? explode(' ', trim((string) $usuarioSesion['nombre']))[0]
    : null;

$flash = obtenerFlash();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#142943" />
    <meta name="description" content="LibreShop, tu librería online. Descubre libros que inspiran, emocionan y transforman." />
    <title><?= e($tituloPagina) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />
    <link rel="stylesheet" href="<?= e($rutaBase) ?>assets/css/estilos.css" />
</head>
<body class="min-h-screen bg-white text-slate-700">
<?php if ($mostrarHeader): ?>
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur-md">
        <div class="page flex h-[74px] items-center justify-between">
            <?= renderizarLogo($rutaBase . 'index.php') ?>
            <nav class="hidden items-center gap-8 text-sm font-semibold text-slate-600 md:flex">
                <a href="<?= e($rutaBase) ?>index.php" class="nav-link <?= $paginaActual === 'inicio' ? 'text-[#1760DC]' : '' ?>">
                    Inicio
                </a>
                <a href="<?= e($rutaBase) ?>paginas/catalogo.php" class="nav-link <?= $paginaActual === 'catalogo' ? 'text-[#1760DC]' : '' ?>">
                    Categorías
                </a>
                <a href="<?= e($rutaBase) ?>paginas/pedidos.php" class="nav-link <?= $paginaActual === 'pedidos' ? 'text-[#1760DC]' : '' ?>">
                    Mis pedidos
                </a>
                <a href="<?= e($rutaBase) ?>paginas/administracion.php" class="nav-link hidden lg:block <?= $paginaActual === 'administracion' ? 'text-[#1760DC]' : '' ?>">
                    Administración
                </a>
            </nav>
            <div class="flex items-center gap-1 sm:gap-3">
                <a
                    href="<?= e($rutaBase) ?><?= $nombreCorto ? 'paginas/pedidos.php' : 'paginas/login.php' ?>"
                    class="header-action no-underline"
                    aria-label="Cuenta"
                >
                    <?= icono('user', 21) ?>
                    <span class="hidden lg:inline"><?= $nombreCorto ? e($nombreCorto) : 'Iniciar sesión' ?></span>
                </a>
                <?php if ($nombreCorto): ?>
                    <a
                        href="<?= e($rutaBase) ?>logout.php"
                        class="hidden text-xs font-semibold text-slate-400 hover:text-slate-700 lg:block no-underline"
                    >
                        Salir
                    </a>
                <?php endif; ?>
                <a
                    href="<?= e($rutaBase) ?>paginas/carrito.php"
                    class="relative grid h-10 w-10 place-items-center rounded-full text-[#142943] hover:bg-slate-100 no-underline"
                    aria-label="Carrito"
                >
                    <?= icono('cart', 22) ?>
                    <?php if ($cantidadCarrito > 0): ?>
                        <span class="absolute right-0 top-0 grid h-5 min-w-5 place-items-center rounded-full bg-[#1760DC] px-1 text-[10px] font-bold text-white">
                            <?= (int) $cantidadCarrito ?>
                        </span>
                    <?php endif; ?>
                </a>
                <button
                    type="button"
                    id="btn-menu-movil"
                    class="ml-1 grid h-10 w-10 place-items-center rounded-lg md:hidden"
                    aria-label="Menú"
                    aria-expanded="false"
                >
                    <span id="icono-menu-abrir"><?= icono('menu', 20) ?></span>
                    <span id="icono-menu-cerrar" class="hidden"><?= icono('close', 20) ?></span>
                </button>
            </div>
        </div>
        <nav id="nav-movil" class="hidden border-t border-slate-100 bg-white px-5 py-4 md:hidden">
            <a href="<?= e($rutaBase) ?>index.php" class="block w-full p-2.5 text-left text-[0.9rem] font-semibold text-slate-700 no-underline">Inicio</a>
            <a href="<?= e($rutaBase) ?>paginas/catalogo.php" class="block w-full p-2.5 text-left text-[0.9rem] font-semibold text-slate-700 no-underline">Catálogo y categorías</a>
            <a href="<?= e($rutaBase) ?>paginas/pedidos.php" class="block w-full p-2.5 text-left text-[0.9rem] font-semibold text-slate-700 no-underline">Mis pedidos</a>
            <a href="<?= e($rutaBase) ?>paginas/administracion.php" class="block w-full p-2.5 text-left text-[0.9rem] font-semibold text-slate-700 no-underline">Administración</a>
        </nav>
    </header>
<?php endif; ?>

<?php if ($flash !== null): ?>
    <div class="page pt-5">
        <div class="flex items-center justify-between gap-4 rounded-xl border px-4 py-3 text-sm font-medium <?= $flash['tipo'] === 'error' ? 'border-red-200 bg-red-50 text-red-700' : ($flash['tipo'] === 'info' ? 'border-blue-200 bg-blue-50 text-[#1760DC]' : 'border-emerald-200 bg-emerald-50 text-emerald-800') ?>">
            <div class="flex items-center gap-2.5">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full <?= $flash['tipo'] === 'error' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' ?>">
                    <?= $flash['tipo'] === 'error' ? icono('close', 14) : icono('check', 14) ?>
                </span>
                <span><?= e($flash['texto']) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-xs font-bold opacity-70 hover:opacity-100">
                Cerrar
            </button>
        </div>
    </div>
<?php endif; ?>
