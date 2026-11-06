<?php
declare(strict_types=1);

$rutaBase      = $rutaBase ?? '';
$mostrarFooter = $mostrarFooter ?? true;
?>
<?php if ($mostrarFooter): ?>
    <footer class="bg-[#0e2036] py-12 text-slate-400">
        <div class="page grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.3fr_.8fr_.8fr_1.2fr]">
            <div>
                <?= renderizarLogo($rutaBase . 'index.php') ?>
                <p class="mt-4 max-w-xs text-sm leading-6">
                    Historias que llegan hasta ti. Descubre, lee y comparte el placer de los libros.
                </p>
            </div>
            <div>
                <h3 class="text-sm font-bold text-white">LibreShop</h3>
                <div class="mt-4 flex flex-col items-start gap-2 text-sm">
                    <a href="<?= e($rutaBase) ?>paginas/catalogo.php" class="text-slate-400 hover:text-white no-underline">Catálogo</a>
                    <a href="<?= e($rutaBase) ?>paginas/pedidos.php" class="text-slate-400 hover:text-white no-underline">Mis pedidos</a>
                    <a href="<?= e($rutaBase) ?>paginas/administracion.php" class="text-slate-400 hover:text-white no-underline">Administración</a>
                </div>
            </div>
            <div>
                <h3 class="text-sm font-bold text-white">Atención al lector</h3>
                <p class="mt-4 text-sm">hola@libreshop.es</p>
                <p class="mt-2 text-sm">L–V, 9:00–18:00</p>
            </div>
            <div>
                <h3 class="text-sm font-bold text-white">Mapa de la tienda</h3>
                <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-2 text-sm">
                    <a href="<?= e($rutaBase) ?>paginas/login.php" class="text-slate-400 hover:text-white no-underline">Login</a>
                    <a href="<?= e($rutaBase) ?>paginas/registro.php" class="text-slate-400 hover:text-white no-underline">Registro</a>
                    <a href="<?= e($rutaBase) ?>paginas/detalle.php?id=1" class="text-slate-400 hover:text-white no-underline">Detalle</a>
                    <a href="<?= e($rutaBase) ?>paginas/carrito.php" class="text-slate-400 hover:text-white no-underline">Carrito</a>
                    <a href="<?= e($rutaBase) ?>paginas/checkout.php" class="text-slate-400 hover:text-white no-underline">Checkout</a>
                    <a href="<?= e($rutaBase) ?>paginas/confirmacion.php" class="text-slate-400 hover:text-white no-underline">Confirmación</a>
                </div>
            </div>
        </div>
        <div class="page mt-10 border-t border-white/10 pt-5 text-xs">
            © 2026 LibreShop · Proyecto académico DAW
        </div>
    </footer>
<?php endif; ?>
<script src="<?= e($rutaBase) ?>assets/js/app.js"></script>
</body>
</html>
