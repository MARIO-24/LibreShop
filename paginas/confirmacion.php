<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Sesiones.php';
require_once __DIR__ . '/../includes/Funciones.php';
require_once __DIR__ . '/../clases/Pedido.php';

requerirAutenticacion('login.php');

$gestorPedidos = new Pedido();
$usuarioActual = obtenerUsuarioActual();
$usuarioId     = (int) ($usuarioActual['id'] ?? 0);
$esAdmin       = esAdministrador();

$pedidoId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_SESSION['ultimo_pedido_id'] ?? 0);

if ($pedidoId <= 0) {
    establecerFlash('info', 'Selecciona un pedido de tu historial para ver su resumen.');
    header('Location: pedidos.php');
    exit;
}

// Verificación estricta de propiedad del pedido en servidor
$pedido = $gestorPedidos->obtenerPorIdYUsuario($pedidoId, $usuarioId, $esAdmin);
if ($pedido === null) {
    establecerFlash('error', 'El pedido solicitado no existe o no tienes permiso para consultarlo.');
    header('Location: pedidos.php');
    exit;
}

$rutaBase     = '../';
$paginaActual = 'confirmacion';
$tituloPagina = 'Pedido ' . $pedido['codigo'] . ' — LibreShop';

require_once __DIR__ . '/../includes/Cabecera.php';
?>

<main class="page max-w-4xl py-16 text-center lg:py-24">
    <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-100 text-emerald-600">
        <?= icono('check', 38) ?>
    </span>
    <p class="eyebrow mt-7">Pedido confirmado</p>
    <h1 class="mt-2 text-4xl font-bold tracking-[-.04em] text-[#142943]">
        ¡Gracias por tu compra!
    </h1>
    <p class="mx-auto mt-3 max-w-lg text-slate-500">
        Hemos recibido tu pedido correctamente. Puedes consultar su estado y todos los detalles de envío en cualquier momento desde tu biblioteca de pedidos.
    </p>

    <div class="mx-auto mt-9 max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm">
        <div class="flex flex-wrap justify-between gap-5 border-b border-slate-200 pb-5">
            <div>
                <span class="meta">Número de pedido</span>
                <strong class="block text-[#142943]"><?= e($pedido['codigo']) ?></strong>
            </div>
            <div>
                <span class="meta">Fecha</span>
                <strong class="block text-[#142943]"><?= e(formatoFechaEspanol((string) $pedido['fecha_pedido'])) ?></strong>
            </div>
            <div>
                <span class="meta">Método de pago</span>
                <strong class="block text-[#142943]"><?= e($pedido['metodo_pago'] ?? 'Tarjeta') ?></strong>
            </div>
            <div>
                <span class="meta">Estado</span>
                <?= renderizarEstadoPedido((string) $pedido['estado']) ?>
            </div>
        </div>

        <div class="border-b border-slate-100 py-4 text-xs text-slate-500">
            <span class="meta">Dirección de envío</span>
            <p class="font-semibold text-[#142943] text-sm"><?= e($pedido['direccion_envio']) ?></p>
        </div>

        <div class="py-3">
            <?php foreach ($pedido['lineas'] as $linea): ?>
                <div class="flex items-center gap-3 py-2.5 border-b border-slate-100 first:border-0">
                    <?= renderizarPortada($linea, 'h-14 aspect-[.68] shrink-0', '../') ?>
                    <div class="flex-1 min-w-0">
                        <a
                            href="detalle.php?id=<?= (int) $linea['producto_id'] ?>"
                            class="block truncate text-sm font-bold text-[#142943] hover:text-[#1760DC] no-underline"
                        >
                            <?= e($linea['titulo']) ?>
                        </a>
                        <span class="block text-xs text-slate-400">
                            <?= e($linea['autor']) ?> · Precio unitario: <?= formatoPrecio((float) $linea['precio_unitario']) ?> × <?= (int) $linea['cantidad'] ?>
                        </span>
                    </div>
                    <strong class="text-sm text-[#142943]"><?= formatoPrecio((float) $linea['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="border-t border-slate-200 pt-4 space-y-2 text-sm text-slate-500">
            <p class="flex justify-between">
                <span>Subtotal libros</span>
                <span><?= formatoPrecio((float) $pedido['subtotal_libros']) ?></span>
            </p>
            <p class="flex justify-between">
                <span>Envío</span>
                <span><?= (float) $pedido['gastos_envio'] === 0.0 ? 'Gratis' : formatoPrecio((float) $pedido['gastos_envio']) ?></span>
            </p>
            <p class="flex justify-between border-t border-slate-100 pt-3 text-lg font-bold text-[#142943]">
                <span>Importe total</span>
                <span><?= formatoPrecio((float) $pedido['total']) ?></span>
            </p>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="pedidos.php" class="btn-primary">
            Ver mis pedidos <?= icono('arrow', 17) ?>
        </a>
        <a href="catalogo.php" class="btn-secondary">
            Seguir comprando
        </a>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
