<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Sesiones.php';
require_once __DIR__ . '/../includes/Funciones.php';
require_once __DIR__ . '/../clases/Pedido.php';

requerirAutenticacion('login.php');

$gestorPedidos = new Pedido();
$usuarioActual = obtenerUsuarioActual();
$usuarioId     = (int) ($usuarioActual['id'] ?? 0);

$pedidos = $gestorPedidos->obtenerPorUsuario($usuarioId);

// Si el cliente solicita ver el desglose de un pedido concreto (?detalle=ID),
// verificamos en servidor que ese pedido pertenezca a su usuario_id.
$pedidoDetalle = null;
if (isset($_GET['detalle'])) {
    $idSolicitado  = (int) $_GET['detalle'];
    $pedidoDetalle = $gestorPedidos->obtenerPorIdYUsuario($idSolicitado, $usuarioId, false);
    if ($pedidoDetalle === null) {
        establecerFlash('error', 'No tienes permiso para consultar ese pedido o no existe.');
        header('Location: pedidos.php');
        exit;
    }
}

$rutaBase     = '../';
$paginaActual = 'pedidos';
$tituloPagina = 'Mis pedidos — LibreShop';

require_once __DIR__ . '/../includes/Cabecera.php';
?>

<main class="page py-10 lg:py-14">
    <p class="eyebrow">Tu biblioteca</p>
    <h1 class="page-title">Mis pedidos</h1>
    <p class="mt-3 text-slate-500">Consulta el estado y los detalles de tus compras.</p>

    <?php if ($pedidoDetalle !== null): ?>
        <section class="mt-8 rounded-2xl border border-blue-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <span class="meta">Detalle del pedido seleccionado</span>
                    <h2 class="text-xl font-bold text-[#142943]">
                        Pedido <?= e($pedidoDetalle['codigo']) ?>
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Realizado el <?= e(formatoFechaEspanol((string) $pedidoDetalle['fecha_pedido'])) ?> ·
                        Método de pago: <strong><?= e($pedidoDetalle['metodo_pago'] ?? 'Tarjeta') ?></strong>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <?= renderizarEstadoPedido((string) $pedidoDetalle['estado']) ?>
                    <a href="pedidos.php" class="btn-secondary px-3 py-1.5 text-xs">Cerrar detalle</a>
                </div>
            </div>

            <div class="mt-4 rounded-lg bg-slate-50 p-3.5 text-xs text-slate-600">
                <span class="meta">Dirección de entrega</span>
                <strong class="text-sm text-[#142943]"><?= e($pedidoDetalle['direccion_envio']) ?></strong>
            </div>

            <div class="mt-4 divide-y divide-slate-100">
                <?php foreach ($pedidoDetalle['lineas'] as $linea): ?>
                    <div class="flex items-center gap-4 py-3 first:pt-0">
                        <?= renderizarPortada($linea, 'h-14 aspect-[.68] shrink-0', '../') ?>
                        <div class="flex-1 min-w-0">
                            <a
                                href="detalle.php?id=<?= (int) $linea['producto_id'] ?>"
                                class="block truncate text-sm font-bold text-[#142943] hover:text-[#1760DC] no-underline"
                            >
                                <?= e($linea['titulo']) ?>
                            </a>
                            <span class="block text-xs text-slate-500">
                                <?= e($linea['autor']) ?> · ISBN: <?= e($linea['isbn']) ?>
                            </span>
                            <span class="block text-xs text-slate-400">
                                Cantidad: <?= (int) $linea['cantidad'] ?> × <?= formatoPrecio((float) $linea['precio_unitario']) ?>
                            </span>
                        </div>
                        <strong class="text-sm text-[#142943]"><?= formatoPrecio((float) $linea['subtotal']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-4">
                <a href="confirmacion.php?id=<?= (int) $pedidoDetalle['id'] ?>" class="link-button text-xs">
                    Ver comprobante completo <?= icono('arrow', 14) ?>
                </a>
                <p class="text-base font-bold text-[#142943]">
                    Importe total: <?= formatoPrecio((float) $pedidoDetalle['total']) ?>
                </p>
            </div>
        </section>
    <?php endif; ?>

    <?php if (empty($pedidos)): ?>
        <div class="mt-10 rounded-2xl border border-slate-200 py-20 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-blue-50 text-[#1760DC]">
                <?= icono('box', 26) ?>
            </span>
            <h2 class="mt-5 text-xl font-bold text-[#142943]">Aún no has realizado ningún pedido</h2>
            <p class="mt-2 text-sm text-slate-500">
                Cuando completes tu primera compra, aparecerá aquí con su estado de envío y desglose.
            </p>
            <a href="catalogo.php" class="btn-primary mt-6">
                Explorar catálogo
            </a>
        </div>
    <?php else: ?>
        <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="hidden grid-cols-5 bg-slate-50 px-6 py-3 text-[10px] font-bold uppercase tracking-[.12em] text-slate-500 md:grid">
                <span>Pedido</span>
                <span>Fecha</span>
                <span>Importe</span>
                <span>Estado</span>
                <span></span>
            </div>
            <?php foreach ($pedidos as $p): ?>
                <?php $idPed = (int) $p['id']; ?>
                <div class="grid gap-3 border-t border-slate-100 p-5 first:border-0 md:grid-cols-5 md:items-center md:px-6">
                    <div>
                        <strong class="block text-sm text-[#142943]"><?= e($p['codigo']) ?></strong>
                        <span class="text-xs text-slate-400"><?= (int) $p['total_unidades'] ?> <?= (int) $p['total_unidades'] === 1 ? 'ejemplar' : 'ejemplares' ?></span>
                    </div>
                    <span class="text-sm text-slate-500"><?= e(formatoFechaEspanol((string) $p['fecha_pedido'], true)) ?></span>
                    <strong class="text-sm text-[#142943]"><?= formatoPrecio((float) $p['total']) ?></strong>
                    <span>
                        <?= renderizarEstadoPedido((string) $p['estado']) ?>
                    </span>
                    <div class="flex items-center gap-3">
                        <a
                            href="pedidos.php?detalle=<?= $idPed ?>"
                            class="flex items-center gap-1 text-sm font-bold text-[#1760DC] no-underline"
                        >
                            Ver detalles <?= icono('chevron', 15) ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
