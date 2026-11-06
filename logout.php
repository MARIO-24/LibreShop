<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/Sesiones.php';

/**
 * Cierre de sesión seguro protegido contra CSRF.
 * Rechaza peticiones GET y peticiones POST sin un token CSRF válido.
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    establecerFlash('error', 'Operación no permitida: utiliza el botón «Cerrar sesión» para salir de tu cuenta.');
    header('Location: index.php');
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');
if (!verificarCSRF($token)) {
    establecerFlash('error', 'Token de seguridad CSRF inválido o caducado. No se ha cerrado la sesión.');
    header('Location: index.php');
    exit;
}

cerrarSesionUsuario();
establecerFlash('info', 'Has cerrado tu sesión correctamente. ¡Hasta pronto!');

header('Location: index.php');
exit;
