<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Sesiones.php';
require_once __DIR__ . '/../includes/Funciones.php';
require_once __DIR__ . '/../clases/Usuario.php';

if (estaAutenticado()) {
    header('Location: ../index.php');
    exit;
}

$gestorUsuarios = new Usuario();
$errores        = [];
$valoresPrevios = [
    'nombre'    => '',
    'apellidos' => '',
    'email'     => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $valoresPrevios['nombre']    = trim((string) ($_POST['nombre'] ?? $_POST['name'] ?? ''));
    $valoresPrevios['apellidos'] = trim((string) ($_POST['apellidos'] ?? ''));
    $valoresPrevios['email']     = trim((string) ($_POST['email'] ?? ''));
    $password                    = (string) ($_POST['password'] ?? '');
    $confirmacion                = (string) ($_POST['confirm'] ?? '');
    $token                       = (string) ($_POST['csrf_token'] ?? '');

    if (!verificarCSRF($token)) {
        $errores[] = 'La sesión del formulario ha caducado. Por favor, inténtalo de nuevo.';
    } else {
        try {
            $nuevoUsuario = $gestorUsuarios->registrar([
                'nombre'    => $valoresPrevios['nombre'],
                'apellidos' => $valoresPrevios['apellidos'],
                'email'     => $valoresPrevios['email'],
                'password'  => $password,
                'confirm'   => $confirmacion,
            ]);

            iniciarSesionUsuario($nuevoUsuario);
            establecerFlash('exito', '¡Cuenta creada con éxito! Bienvenido a LibreShop, ' . $nuevoUsuario['nombre'] . '.');

            header('Location: ../index.php');
            exit;
        } catch (InvalidArgumentException $e) {
            $errores[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('[LibreShop Registro Error] ' . $e->getMessage());
            $errores[] = 'No se pudo completar el registro en este momento. Inténtalo más tarde.';
        }
    }
}

$flashRegistro = obtenerFlash();
$rutaBase      = '../';
$paginaActual  = 'registro';
$tituloPagina  = 'Crear cuenta — LibreShop';
$mostrarHeader = false;
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

            <p class="eyebrow">Únete a LibreShop</p>
            <h1 class="mt-2 text-4xl font-bold tracking-[-.04em] text-[#142943]">
                Crea tu cuenta
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                Guarda tus favoritos y consulta todos tus pedidos.
            </p>

            <?php if ($flashRegistro !== null): ?>
                <div class="mt-5 rounded-lg border p-3 text-sm <?= $flashRegistro['tipo'] === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-[#1760DC]' ?>">
                    <p><?= e($flashRegistro['texto']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <?php foreach ($errores as $err): ?>
                        <p><?= e($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="mt-7 space-y-4" method="post" action="registro.php" novalidate>
                <?= campoCSRF() ?>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="field-label">
                        Nombre *
                        <input
                            name="nombre"
                            required
                            autocomplete="given-name"
                            value="<?= e($valoresPrevios['nombre']) ?>"
                            class="input mt-2"
                            placeholder="Tu nombre"
                        />
                    </label>
                    <label class="field-label">
                        Apellidos
                        <input
                            name="apellidos"
                            autocomplete="family-name"
                            value="<?= e($valoresPrevios['apellidos']) ?>"
                            class="input mt-2"
                            placeholder="Tus apellidos"
                        />
                    </label>
                </div>
                <label class="field-label">
                    Correo electrónico *
                    <input
                        name="email"
                        type="email"
                        required
                        autocomplete="email"
                        value="<?= e($valoresPrevios['email']) ?>"
                        class="input mt-2"
                        placeholder="nombre@ejemplo.com"
                    />
                </label>
                <label class="field-label">
                    Contraseña *
                    <input
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="input mt-2"
                        placeholder="Mínimo 6 caracteres"
                    />
                </label>
                <label class="field-label">
                    Confirmar contraseña *
                    <input
                        name="confirm"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="input mt-2"
                        placeholder="Repite tu contraseña"
                    />
                </label>
                <button class="btn-primary mt-2 w-full justify-center" type="submit">
                    Crear cuenta <?= icono('arrow', 17) ?>
                </button>
            </form>

            <p class="mt-7 text-center text-sm text-slate-500">
                ¿Ya tienes una cuenta?
                <a href="login.php" class="font-bold text-[#1760DC] no-underline">
                    Inicia sesión
                </a>
            </p>

            <a
                href="catalogo.php"
                class="mt-8 flex w-full items-center justify-center gap-2 text-xs font-semibold text-slate-400 hover:text-slate-600 no-underline"
            >
                <?= icono('back', 15) ?> Continuar como visitante
            </a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
