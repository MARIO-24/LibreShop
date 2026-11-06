<?php
declare(strict_types=1);

/**
 * Gestión base de sesiones PHP, mensajes flash y protección CSRF.
 * (El control de autenticación de usuarios y carrito se completará en las Fases 2 y 3).
 */
if (session_status() === PHP_SESSION_NONE) {
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $esHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
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
