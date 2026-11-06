<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/Sesiones.php';

cerrarSesionUsuario();
establecerFlash('info', 'Has cerrado tu sesión correctamente. ¡Hasta pronto!');

header('Location: index.php');
exit;
