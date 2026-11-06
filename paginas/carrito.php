<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Sesiones.php';
require_once __DIR__ . '/../includes/Funciones.php';
require_once __DIR__ . '/../clases/Carrito.php';

$gestorCarrito = new Carrito();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!verificarCSRF($token)) {
        establecerFlash('error', 'La sesión del formulario ha caducado o el token CSRF no es válido.');
        header('Location: carrito.php');
        exit;
    }

    $accion     = (string) ($_POST['accion'] ?? '');
    $productoId = isset($_POST['producto_id']) ? (int) $_POST['producto_id'] : 0;
    $cantidad   = isset($_POST['cantidad']) ? (int) $_POST['cantidad'] : 1;

    try {
        switch ($accion) {
            case 'agregar':
                $libro = $gestorCarrito->agregar($productoId, $cantidad);
                establecerFlash('exito', '«' . $libro['titulo'] . '» se ha añadido a tu carrito.');
                break;

            case 'actualizar':
                $libro = $gestorCarrito->actualizarCantidad($productoId, $cantidad);
                establecerFlash('info', 'Cantidad de «' . $libro['titulo'] . '» actualizada.');
                break;

            case 'eliminar':
                $gestorCarrito->eliminar($productoId);
                establecerFlash('info', 'Libro eliminado del carrito.');
                break;

            case 'vaciar':
                $gestorCarrito->vaciar();
                establecerFlash('info', 'Se ha vaciado el carrito de compra.');
                break;
        }
    } catch (InvalidArgumentException $e) {
        establecerFlash('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('[LibreShop Carrito Error] ' . $e->getMessage());
        establecerFlash('error', 'No se pudo actualizar el carrito en este momento.');
    }

    header('Location: carrito.php');
    exit;
}

$resumen = $gestorCarrito->obtenerResumen();
$lineas  = $resumen['lineas'];

$rutaBase     = '../';
$paginaActual = 'carrito';
$tituloPagina = 'Carrito de compra — LibreShop';

require_once __DIR__ . '/../includes/Cabecera.php';
?>

<main class="page py-10 lg:py-14">
    <p class="eyebrow">Tu selección</p>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="page-title">Carrito de compra</h1>
        <?php if (!empty($lineas)): ?>
            <form
                method="post"
                action="carrito.php"
                data-confirmar="¿Deseas vaciar todos los libros del carrito?"
                class="m-0"
            >
                <?= campoCSRF() ?>
                <input type="hidden" name="accion" value="vaciar" />
                <button
                    type="submit"
                    class="flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-red-600"
                >
                    <?= icono('trash', 15) ?> Vaciar carrito
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($resumen['avisos'])): ?>
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
            <?php foreach ($resumen['avisos'] as $aviso): ?>
                <p><?= e($aviso) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($lineas)): ?>
        <div class="mt-10 rounded-2xl border border-slate-200 py-20 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-blue-50 text-[#1760DC]">
                <?= icono('cart', 26) ?>
            </span>
            <h2 class="mt-5 text-xl font-bold text-[#142943]">Tu carrito está vacío</h2>
            <p class="mt-2 text-sm text-slate-500">
                Hay muchas historias esperando a ser descubiertas.
            </p>
            <a href="catalogo.php" class="btn-primary mt-6">
                Explorar catálogo
            </a>
        </div>
    <?php else: ?>
        <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_340px]">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="hidden grid-cols-[1fr_110px_120px_100px_40px] gap-4 bg-slate-50 px-5 py-3 text-[10px] font-bold uppercase tracking-[.12em] text-slate-500 md:grid">
                    <span>Producto</span>
                    <span>Precio</span>
                    <span>Cantidad</span>
                    <span>Subtotal</span>
                    <span></span>
                </div>

                <?php foreach ($lineas as $linea): ?>
                    <?php
                    $p               = $linea['producto'];
                    $idProd          = (int) $linea['producto_id'];
                    $cant            = (int) $linea['cantidad'];
                    $stockMax        = (int) $linea['stock_disponible'];
                    $precioUnitTexto = formatoPrecio((float) $linea['precio_unitario']);
                    $subtotalTexto   = formatoPrecio((float) $linea['subtotal']);
                    ?>
                    <div class="grid grid-cols-[70px_1fr_auto] items-center gap-4 border-t border-slate-100 p-4 first:border-0 md:grid-cols-[1fr_110px_120px_100px_40px]">
                        <div class="flex items-center gap-4">
                            <a href="detalle.php?id=<?= $idProd ?>" class="no-underline shrink-0">
                                <?= renderizarPortada($p, 'h-20 aspect-[.68] shrink-0', '../') ?>
                            </a>
                            <div>
                                <a href="detalle.php?id=<?= $idProd ?>" class="block text-sm font-bold text-[#142943] hover:text-[#1760DC] no-underline">
                                    <?= e($linea['titulo']) ?>
                                </a>
                                <span class="block text-xs text-slate-500"><?= e($linea['autor']) ?></span>
                                <span class="mt-1 block text-xs font-semibold text-[#142943] md:hidden">
                                    <?= $precioUnitTexto ?> · Subtotal: <?= $subtotalTexto ?>
                                </span>
                            </div>
                        </div>

                        <span class="hidden text-sm font-semibold md:block"><?= $precioUnitTexto ?></span>

                        <div class="flex h-9 items-center rounded-md border border-slate-300 bg-white">
                            <form method="post" action="carrito.php" class="m-0 h-full">
                                <?= campoCSRF() ?>
                                <input type="hidden" name="accion" value="actualizar" />
                                <input type="hidden" name="producto_id" value="<?= $idProd ?>" />
                                <input type="hidden" name="cantidad" value="<?= max(1, $cant - 1) ?>" />
                                <button
                                    type="submit"
                                    class="h-full px-2.5 text-sm font-bold"
                                    aria-label="Disminuir cantidad"
                                    <?= $cant <= 1 ? 'disabled style="opacity:.35;cursor:not-allowed;"' : '' ?>
                                >
                                    −
                                </button>
                            </form>

                            <span class="w-6 text-center text-sm font-semibold"><?= $cant ?></span>

                            <form method="post" action="carrito.php" class="m-0 h-full">
                                <?= campoCSRF() ?>
                                <input type="hidden" name="accion" value="actualizar" />
                                <input type="hidden" name="producto_id" value="<?= $idProd ?>" />
                                <input type="hidden" name="cantidad" value="<?= min($stockMax, $cant + 1) ?>" />
                                <button
                                    type="submit"
                                    class="h-full px-2.5 text-sm font-bold"
                                    aria-label="Aumentar cantidad"
                                    title="<?= $cant >= $stockMax ? 'Stock máximo alcanzado (' . $stockMax . ')' : 'Añadir una unidad más' ?>"
                                    <?= $cant >= $stockMax ? 'disabled style="opacity:.35;cursor:not-allowed;"' : '' ?>
                                >
                                    +
                                </button>
                            </form>
                        </div>

                        <strong class="hidden text-sm text-[#142943] md:block"><?= $subtotalTexto ?></strong>

                        <form method="post" action="carrito.php" class="m-0">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="accion" value="eliminar" />
                            <input type="hidden" name="producto_id" value="<?= $idProd ?>" />
                            <button
                                type="submit"
                                class="text-slate-400 hover:text-red-500"
                                aria-label="Eliminar <?= e($linea['titulo']) ?>"
                                title="Eliminar del carrito"
                            >
                                <?= icono('trash', 18) ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <aside class="rounded-xl bg-[#142943] p-6 text-white">
                <h2 class="text-lg font-bold">Resumen del pedido</h2>
                <div class="mt-6 space-y-3 border-b border-white/15 pb-5 text-sm text-slate-300">
                    <p class="flex justify-between">
                        <span>Subtotal (<?= (int) $resumen['total_articulos'] ?> <?= (int) $resumen['total_articulos'] === 1 ? 'libro' : 'libros' ?>)</span>
                        <span><?= formatoPrecio((float) $resumen['subtotal']) ?></span>
                    </p>
                    <p class="flex justify-between">
                        <span>Envío</span>
                        <span class="text-emerald-300">
                            <?= (float) $resumen['envio'] === 0.0 ? 'Gratis' : formatoPrecio((float) $resumen['envio']) ?>
                        </span>
                    </p>
                </div>
                <p class="flex justify-between py-5 text-lg font-bold">
                    <span>Total</span>
                    <span><?= formatoPrecio((float) $resumen['total']) ?></span>
                </p>
                <a
                    href="checkout.php"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#1760DC] px-5 py-3 text-sm font-bold text-white hover:bg-blue-500 no-underline"
                >
                    Realizar pedido <?= icono('arrow', 16) ?>
                </a>
                <a
                    href="catalogo.php"
                    class="mt-3 block w-full py-2 text-center text-sm font-semibold text-slate-300 hover:text-white no-underline"
                >
                    Seguir comprando
                </a>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
