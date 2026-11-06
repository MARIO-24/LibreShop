<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Sesiones.php';
require_once __DIR__ . '/../includes/Funciones.php';
require_once __DIR__ . '/../clases/Carrito.php';
require_once __DIR__ . '/../clases/Pedido.php';

// Solo los usuarios autenticados pueden finalizar compras
requerirAutenticacion('login.php');

$gestorCarrito = new Carrito();
$gestorPedidos = new Pedido();
$usuarioActual = obtenerUsuarioActual();
$usuarioId     = (int) ($usuarioActual['id'] ?? 0);

$errores = [];
$valores = [
    'direccion'     => '',
    'codigo_postal' => '',
    'ciudad'        => '',
    'metodo_pago'   => 'Tarjeta',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $valores['direccion']     = trim((string) ($_POST['direccion'] ?? ''));
    $valores['codigo_postal'] = trim((string) ($_POST['codigo_postal'] ?? ''));
    $valores['ciudad']        = trim((string) ($_POST['ciudad'] ?? ''));
    $valores['metodo_pago']   = trim((string) ($_POST['metodo_pago'] ?? 'Tarjeta'));

    $tokenCsrf     = (string) ($_POST['csrf_token'] ?? '');
    $tokenCheckout = (string) ($_POST['checkout_token'] ?? '');
    $tokenSesion   = (string) ($_SESSION['checkout_token'] ?? '');

    if (!verificarCSRF($tokenCsrf)) {
        $errores[] = 'El token de seguridad CSRF no es válido. Por favor, recarga la página e inténtalo de nuevo.';
    } elseif ($tokenSesion === '' || !hash_equals($tokenSesion, $tokenCheckout)) {
        // Evitar compras duplicadas por doble clic o reenvío de formulario
        if (!empty($_SESSION['ultimo_pedido_id'])) {
            header('Location: confirmacion.php?id=' . (int) $_SESSION['ultimo_pedido_id']);
            exit;
        }
        $errores[] = 'Esta solicitud de compra ya fue procesada o ha caducado. Revisa el resumen antes de confirmar.';
    } else {
        // Consumir el token de idempotencia antes de ejecutar la transacción
        unset($_SESSION['checkout_token']);

        try {
            $itemsCarrito = $gestorCarrito->obtenerItemsSesion();
            $pedidoId     = $gestorPedidos->crearDesdeCarrito($usuarioId, $itemsCarrito, $valores);

            // Solo vaciamos el carrito después de que la transacción SQL se ha confirmado con éxito
            $gestorCarrito->vaciar();
            $_SESSION['ultimo_pedido_id'] = $pedidoId;

            establecerFlash('exito', 'Tu pedido ' . Pedido::formatearCodigo($pedidoId) . ' se ha registrado correctamente.');
            header('Location: confirmacion.php?id=' . $pedidoId);
            exit;
        } catch (InvalidArgumentException|RuntimeException $e) {
            $errores[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('[LibreShop Checkout Error] ' . $e->getMessage());
            $errores[] = 'No ha sido posible completar el pedido en este momento. Tu carrito sigue intacto.';
        }
    }
}

$resumen = $gestorCarrito->obtenerResumen();
$lineas  = $resumen['lineas'];

if (empty($lineas)) {
    establecerFlash('info', 'Tu carrito está vacío. Añade libros antes de finalizar el pedido.');
    header('Location: carrito.php');
    exit;
}

// Generar nuevo token único de idempotencia para el formulario actual
$_SESSION['checkout_token'] = bin2hex(random_bytes(24));
$checkoutToken              = $_SESSION['checkout_token'];

$rutaBase     = '../';
$paginaActual = 'checkout';
$tituloPagina = 'Finalizar pedido — LibreShop';

require_once __DIR__ . '/../includes/Cabecera.php';
?>

<main class="page py-10 lg:py-14">
    <a
        href="carrito.php"
        class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-[#1760DC] no-underline"
    >
        <?= icono('back', 16) ?> Volver al carrito
    </a>

    <p class="eyebrow">Último paso</p>
    <h1 class="page-title">Finalizar pedido</h1>

    <?php if (!empty($errores)): ?>
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <?php foreach ($errores as $err): ?>
                <p class="font-semibold"><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($resumen['avisos'])): ?>
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
            <?php foreach ($resumen['avisos'] as $aviso): ?>
                <p><?= e($aviso) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form
        id="form-checkout"
        method="post"
        action="checkout.php"
        class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_390px]"
    >
        <?= campoCSRF() ?>
        <input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>" />
        <input type="hidden" id="input-metodo-pago" name="metodo_pago" value="<?= e($valores['metodo_pago']) ?>" />

        <div class="space-y-6">
            <section class="panel">
                <h2 class="panel-title">
                    <span>1</span> Dirección de envío
                </h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2 rounded-lg bg-slate-50 px-3.5 py-2.5 text-xs text-slate-500">
                        Destinatario: <strong class="text-[#142943]"><?= e(trim(($usuarioActual['nombre'] ?? '') . ' ' . ($usuarioActual['apellidos'] ?? ''))) ?></strong>
                        (<?= e($usuarioActual['email'] ?? '') ?>)
                    </div>
                    <label class="field-label sm:col-span-2">
                        Dirección *
                        <input
                            name="direccion"
                            required
                            minlength="5"
                            maxlength="160"
                            value="<?= e($valores['direccion']) ?>"
                            class="input mt-2"
                            placeholder="Calle, número, piso, puerta..."
                        />
                    </label>
                    <label class="field-label">
                        Código postal *
                        <input
                            name="codigo_postal"
                            required
                            minlength="4"
                            maxlength="10"
                            value="<?= e($valores['codigo_postal']) ?>"
                            class="input mt-2"
                            placeholder="28001"
                        />
                    </label>
                    <label class="field-label">
                        Ciudad *
                        <input
                            name="ciudad"
                            required
                            minlength="2"
                            maxlength="80"
                            value="<?= e($valores['ciudad']) ?>"
                            class="input mt-2"
                            placeholder="Madrid"
                        />
                    </label>
                </div>
            </section>

            <section class="panel">
                <h2 class="panel-title">
                    <span>2</span> Método de pago
                </h2>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        data-metodo-pago="Tarjeta"
                        class="payment <?= $valores['metodo_pago'] !== 'PayPal' ? 'active' : '' ?>"
                    >
                        <span class="text-xl">▰</span>
                        <span>
                            <strong>Tarjeta</strong>
                            <small>Visa, Mastercard</small>
                        </span>
                        <i></i>
                    </button>
                    <button
                        type="button"
                        data-metodo-pago="PayPal"
                        class="payment <?= $valores['metodo_pago'] === 'PayPal' ? 'active' : '' ?>"
                    >
                        <span class="text-lg font-black italic text-blue-700">Pay</span>
                        <span>
                            <strong>PayPal</strong>
                            <small>Pago seguro</small>
                        </span>
                        <i></i>
                    </button>
                </div>
                <p class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                    Por seguridad, LibreShop registra únicamente la modalidad de pago seleccionada para tu pedido sin almacenar numeraciones de tarjetas bancarias ni códigos CVV.
                </p>
            </section>
        </div>

        <aside class="panel lg:sticky lg:top-24">
            <h2 class="text-lg font-bold text-[#142943]">Resumen</h2>
            <div class="mt-5 space-y-4">
                <?php foreach ($lineas as $linea): ?>
                    <div class="flex gap-3">
                        <?= renderizarPortada($linea['producto'], 'h-16 aspect-[.68] shrink-0', '../') ?>
                        <div class="min-w-0 flex-1">
                            <strong class="block truncate text-sm text-[#142943]"><?= e($linea['titulo']) ?></strong>
                            <span class="block text-xs text-slate-500">
                                Cantidad: <?= (int) $linea['cantidad'] ?> × <?= formatoPrecio((float) $linea['precio_unitario']) ?>
                            </span>
                        </div>
                        <strong class="text-sm text-[#142943]"><?= formatoPrecio((float) $linea['subtotal']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 border-t border-slate-200 pt-5 space-y-3">
                <p class="flex justify-between text-sm text-slate-500">
                    <span>Subtotal</span>
                    <span><?= formatoPrecio((float) $resumen['subtotal']) ?></span>
                </p>
                <p class="flex justify-between text-sm text-slate-500">
                    <span>Envío</span>
                    <span class="<?= (float) $resumen['envio'] === 0.0 ? 'font-semibold text-emerald-700' : '' ?>">
                        <?= (float) $resumen['envio'] === 0.0 ? 'Gratis' : formatoPrecio((float) $resumen['envio']) ?>
                    </span>
                </p>
                <p class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-xl font-bold text-[#142943]">
                    <span>Total</span>
                    <span><?= formatoPrecio((float) $resumen['total']) ?></span>
                </p>
            </div>

            <button id="btn-confirmar-pedido" type="submit" class="btn-primary mt-6 w-full justify-center">
                Confirmar pedido <?= icono('check', 17) ?>
            </button>
            <p class="mt-3 text-center text-[11px] text-slate-400">
                Pago cifrado y compra 100 % segura
            </p>
        </aside>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
