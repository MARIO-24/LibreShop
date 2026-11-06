<?php
declare(strict_types=1);

/**
 * Gestión completa de sesiones PHP, autenticación, control de acceso por roles (RBAC),
 * mensajes flash y protección contra CSRF y Session Fixation.
 */
if (session_status() === PHP_SESSION_NONE) {
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.cookie_httponly', '1');

    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $esHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    } else {
        @session_start();
    }
}

/**
 * Genera o recupera el token CSRF de la sesión actual.
 */
function obtenerTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

/**
 * Devuelve un campo input oculto con el token CSRF listo para insertar en un <form>.
 */
function campoCSRF(): string
{
    $token = htmlspecialchars(obtenerTokenCSRF(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verifica que el token CSRF enviado por POST coincida con el de la sesión.
 */
function verificarCSRF(?string $tokenEnviado): bool
{
    if (empty($_SESSION['csrf_token']) || empty($tokenEnviado)) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf_token'], $tokenEnviado);
}

/**
 * Guarda un mensaje temporal (flash) en la sesión para mostrarlo tras una redirección PRG.
 *
 * @param string $tipo  'exito' | 'error' | 'info'
 * @param string $texto Mensaje para el usuario.
 */
function establecerFlash(string $tipo, string $texto): void
{
    $_SESSION['flash_mensaje'] = [
        'tipo'  => $tipo,
        'texto' => $texto,
    ];
}

/**
 * Recupera y elimina el mensaje flash pendiente en la sesión.
 *
 * @return array{tipo: string, texto: string}|null
 */
function obtenerFlash(): ?array
{
    if (!isset($_SESSION['flash_mensaje']) || !is_array($_SESSION['flash_mensaje'])) {
        return null;
    }

    $mensaje = $_SESSION['flash_mensaje'];
    unset($_SESSION['flash_mensaje']);

    return $mensaje;
}

/**
 * Inicia sesión de forma segura para el usuario autenticado, regenerando el ID de sesión
 * para prevenir ataques de fijación de sesión (Session Fixation).
 *
 * @param array<string, mixed> $usuario Datos del usuario procedentes de MySQL.
 */
function iniciarSesionUsuario(array $usuario): void
{
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['usuario'] = [
        'id'        => (int) ($usuario['id'] ?? 0),
        'nombre'    => (string) ($usuario['nombre'] ?? ''),
        'apellidos' => (string) ($usuario['apellidos'] ?? ''),
        'email'     => (string) ($usuario['email'] ?? ''),
        'rol'       => ((string) ($usuario['rol'] ?? 'cliente')) === 'administrador' ? 'administrador' : 'cliente',
    ];

    // Renovar el token CSRF al cambiar el estado de autenticación
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Cierra la sesión actual del usuario limpiando datos y cookies de sesión.
 */
function cerrarSesionUsuario(): void
{
    unset($_SESSION['usuario'], $_SESSION['url_retorno']);

    if (session_status() === PHP_SESSION_ACTIVE) {
        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        if (!headers_sent()) {
            session_regenerate_id(true);
        }
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Comprueba si existe un usuario autenticado en la sesión actual.
 */
function estaAutenticado(): bool
{
    return isset($_SESSION['usuario'])
        && is_array($_SESSION['usuario'])
        && !empty($_SESSION['usuario']['id']);
}

/**
 * Devuelve los datos del usuario autenticado o null si es visitante.
 *
 * @return array<string, mixed>|null
 */
function obtenerUsuarioActual(): ?array
{
    return estaAutenticado() ? $_SESSION['usuario'] : null;
}

/**
 * Comprueba si el usuario autenticado tiene rol de 'administrador'.
 */
function esAdministrador(): bool
{
    return estaAutenticado()
        && ($_SESSION['usuario']['rol'] ?? '') === 'administrador';
}

/**
 * Protege una página privada exigiendo que el usuario haya iniciado sesión.
 * Si no está autenticado, guarda la URL solicitada y redirige al login.
 */
function requerirAutenticacion(string $urlLogin = 'login.php'): void
{
    if (!estaAutenticado()) {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $_SESSION['url_retorno'] = (string) $_SERVER['REQUEST_URI'];
        }
        establecerFlash('info', 'Inicia sesión con tu cuenta para acceder a esta página.');
        header('Location: ' . $urlLogin);
        exit;
    }
}

/**
 * Protege páginas exclusivas de administración exigiendo rol 'administrador'.
 * - Si no ha iniciado sesión: redirige a login.php.
 * - Si es un cliente autenticado sin permisos: redirige al inicio con aviso de acceso denegado.
 */
function requerirAdministrador(string $urlLogin = 'login.php', string $urlInicio = '../index.php'): void
{
    if (!estaAutenticado()) {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $_SESSION['url_retorno'] = (string) $_SERVER['REQUEST_URI'];
        }
        establecerFlash('error', 'Debes iniciar sesión como administrador para acceder al panel de gestión.');
        header('Location: ' . $urlLogin);
        exit;
    }

    if (!esAdministrador()) {
        establecerFlash('error', 'Acceso restringido: tu cuenta de cliente no tiene permisos de administrador.');
        header('Location: ' . $urlInicio);
        exit;
    }
}
