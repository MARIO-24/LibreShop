<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Sesiones.php';
require_once __DIR__ . '/../includes/Funciones.php';
require_once __DIR__ . '/../clases/Usuario.php';

// Si ya ha iniciado sesión, redirigir directamente al inicio o al panel si es administrador
if (estaAutenticado()) {
    header('Location: ' . (esAdministrador() ? 'administracion.php' : '../index.php'));
    exit;
}

$gestorUsuarios = new Usuario();
$errores        = [];
$emailPrevio    = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $emailPrevio = trim((string) ($_POST['email'] ?? ''));
    $password    = (string) ($_POST['password'] ?? '');
    $token       = (string) ($_POST['csrf_token'] ?? '');

    if (!verificarCSRF($token)) {
        $errores[] = 'La sesión del formulario ha caducado. Por favor, inténtalo de nuevo.';
    } else {
        try {
            // Aseguramos que las dos cuentas de demostración del examen tengan su hash activo
            $gestorUsuarios->asegurarCuentasDemo();

            $usuario = $gestorUsuarios->autenticar($emailPrevio, $password);
            iniciarSesionUsuario($usuario);

            establecerFlash('exito', '¡Bienvenido de nuevo, ' . $usuario['nombre'] . '!');

            // Redirección segura si venía de una página privada protegida
            $urlRetorno = (string) ($_SESSION['url_retorno'] ?? '');
            unset($_SESSION['url_retorno']);

            if ($urlRetorno !== '' && str_starts_with($urlRetorno, '/') && !str_starts_with($urlRetorno, '//')) {
                header('Location: ' . $urlRetorno);
                exit;
            }

            header('Location: ' . ($usuario['rol'] === 'administrador' ? 'administracion.php' : '../index.php'));
            exit;
        } catch (InvalidArgumentException $e) {
            $errores[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('[LibreShop Login Error] ' . $e->getMessage());
            $errores[] = 'No se pudo procesar el inicio de sesión en este momento.';
        }
    }
}

$flashLogin    = obtenerFlash();
$rutaBase      = '../';
$paginaActual  = 'login';
$tituloPagina  = 'Iniciar sesión — LibreShop';
$mostrarHeader = false; // Diseño original Figma Make: sin barra superior ni pie en login/registro
$mostrarFooter = false;

require_once __DIR__ . '/../includes/Cabecera.php';
?>

<main class="grid min-h-screen lg:grid-cols-2">
    <div class="hidden bg-[#142943] p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <?= renderizarLogo('../index.php') ?>
        <div class="mx-auto max-w-md">
            <span class="text-6xl font-serif text-blue-400">“</span>
            <blockquote class="text-3xl font-semibold leading-tight tracking-[-.03em]">
                Un lector vive mil vidas antes de morir. El que nunca lee vive solo una.
            </blockquote>
            <p class="mt-5 text-sm text-slate-400">— George R. R. Martin</p>
        </div>
        <p class="text-xs text-slate-500">© 2026 LibreShop · Tu librería, estés donde estés.</p>
    </div>

    <div class="flex items-center justify-center bg-white p-6 py-16">
        <div class="w-full max-w-md">
            <div class="mb-10 lg:hidden">
                <?= renderizarLogo('../index.php') ?>
            </div>

            <p class="eyebrow">Bienvenido de nuevo</p>
            <h1 class="mt-2 text-4xl font-bold tracking-[-.04em] text-[#142943]">
                Inicia sesión
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                Accede a tus pedidos y continúa descubriendo historias.
            </p>

            <?php if ($flashLogin !== null): ?>
                <div class="mt-5 rounded-lg border p-3 text-sm <?= $flashLogin['tipo'] === 'error' ? 'border-red-200 bg-red-50 text-red-700' : ($flashLogin['tipo'] === 'info' ? 'border-blue-200 bg-blue-50 text-[#1760DC]' : 'border-emerald-200 bg-emerald-50 text-emerald-800') ?>">
                    <p><?= e($flashLogin['texto']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <?php foreach ($errores as $err): ?>
                        <p><?= e($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="mt-7 space-y-4" method="post" action="login.php" novalidate>
                <?= campoCSRF() ?>
                <label class="field-label">
                    Correo electrónico
                    <input
                        name="email"
                        type="email"
                        required
                        autocomplete="email"
                        value="<?= e($emailPrevio) ?>"
                        class="input mt-2"
                        placeholder="nombre@ejemplo.com"
                    />
                </label>
                <label class="field-label">
                    Contraseña
                    <input
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="input mt-2"
                        placeholder="Mínimo 6 caracteres"
                    />
                </label>
                <button class="btn-primary mt-2 w-full justify-center" type="submit">
                    Entrar <?= icono('arrow', 17) ?>
                </button>
            </form>

            <p class="mt-7 text-center text-sm text-slate-500">
                ¿Aún no tienes cuenta?
                <a href="registro.php" class="font-bold text-[#1760DC] no-underline">
                    Regístrate
                </a>
            </p>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-500">
                <strong class="block text-[#142943] mb-1">Cuentas de prueba para evaluación:</strong>
                <p>Admin: <code class="font-semibold text-[#1760DC]">admin@libreshop.es</code> · <code class="font-semibold">Admin1234!</code></p>
                <p class="mt-0.5">Cliente: <code class="font-semibold text-[#1760DC]">marina@libreshop.es</code> · <code class="font-semibold">Cliente1234!</code></p>
            </div>

            <a
                href="catalogo.php"
                class="mt-6 flex w-full items-center justify-center gap-2 text-xs font-semibold text-slate-400 hover:text-slate-600 no-underline"
            >
                <?= icono('back', 15) ?> Continuar como visitante
            </a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
