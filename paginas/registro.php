<?php
declare(strict_types=1);

$rutaBase     = '../';
$paginaActual = 'registro';
$tituloPagina = 'Crear cuenta — LibreShop';

require_once __DIR__ . '/../includes/Cabecera.php';
?>

<main class="page py-16 text-center">
    <div class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p class="eyebrow">Fase 2 — Control de Usuario</p>
        <h1 class="mt-2 text-2xl font-bold text-[#142943]">Registro de usuarios en preparación</h1>
        <p class="mt-3 text-sm text-slate-500">
            El registro de cuentas se implementará en la <strong>Fase 2</strong> tras validar el Gestor de Productos.
        </p>
        <div class="mt-6 flex justify-center gap-3">
            <a href="../index.php" class="btn-secondary">Volver al inicio</a>
            <a href="catalogo.php" class="btn-primary">Ir al catálogo</a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
