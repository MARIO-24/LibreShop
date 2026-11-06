<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Sesiones.php';
require_once __DIR__ . '/../includes/Funciones.php';
require_once __DIR__ . '/../clases/Categoria.php';
require_once __DIR__ . '/../clases/Producto.php';

// Protección obligatoria: acceso exclusivo para usuarios autenticados con rol 'administrador'
requerirAdministrador('login.php', '../index.php');

$gestorCategorias = new Categoria();
$gestorProductos  = new Producto();

$erroresFormulario = [];

// ============================================================================
// PROCESAMIENTO DE ACCIONES POST (CRUD Productos y Categorías con CSRF)
// ============================================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verificarCSRF((string) $token)) {
        establecerFlash('error', 'La sesión del formulario ha caducado o el token CSRF no es válido. Inténtalo de nuevo.');
        header('Location: administracion.php');
        exit;
    }

    $accion      = (string) ($_POST['accion'] ?? '');
    $nuevaSubida = null;

    try {
        switch ($accion) {
            case 'guardar_producto':
                $idEditar       = isset($_POST['producto_id']) ? (int) $_POST['producto_id'] : 0;
                $productoPrevio = $idEditar > 0 ? $gestorProductos->obtenerPorId($idEditar, false) : null;
                if ($idEditar > 0 && $productoPrevio === null) {
                    throw new InvalidArgumentException('El producto que intenta editar no existe.');
                }

                $imagenAnteriorBD = $productoPrevio !== null
                    ? trim((string) ($productoPrevio['imagen'] ?? ''))
                    : '';

                // Gestión de imagen de portada (subida de archivo, sustitución o eliminación)
                $eliminarImagen = !empty($_POST['eliminar_imagen']) && (string) $_POST['eliminar_imagen'] === '1';
                $rutaImagen     = $eliminarImagen ? '' : $imagenAnteriorBD;

                $urlManual = trim((string) ($_POST['imagen_url'] ?? ''));
                if ($urlManual !== '' && !$eliminarImagen) {
                    $rutaImagen = $urlManual;
                }

                if (isset($_FILES['imagen_archivo']) && is_array($_FILES['imagen_archivo'])) {
                    $nuevaSubida = $gestorProductos->procesarSubidaImagen($_FILES['imagen_archivo']);
                    if ($nuevaSubida !== null) {
                        $rutaImagen = $nuevaSubida;
                    }
                }

                $datosProducto = [
                    'titulo'           => $_POST['titulo'] ?? '',
                    'autor'            => $_POST['autor'] ?? '',
                    'isbn'             => $_POST['isbn'] ?? '',
                    'descripcion'      => $_POST['descripcion'] ?? '',
                    'precio'           => $_POST['precio'] ?? 0,
                    'stock'            => $_POST['stock'] ?? 0,
                    'categoria_id'     => (int) ($_POST['categoria_id'] ?? 0),
                    'valoracion'       => $_POST['valoracion'] ?? 4.8,
                    'color_primario'   => $_POST['color_primario'] ?? ($productoPrevio['color_primario'] ?? '#0d223c'),
                    'color_secundario' => $_POST['color_secundario'] ?? ($productoPrevio['color_secundario'] ?? '#d89d4a'),
                    'sello_editorial'  => $_POST['sello_editorial'] ?? ($productoPrevio['sello_editorial'] ?? ''),
                    'imagen'           => $rutaImagen !== '' ? $rutaImagen : null,
                    'activo'           => isset($_POST['activo']) ? 1 : 0,
                    'destacado'        => isset($_POST['destacado']) ? 1 : 0,
                ];

                if ($idEditar > 0) {
                    $gestorProductos->actualizar($idEditar, $datosProducto);
                    // Borrar la imagen anterior únicamente después de haber guardado el nuevo registro con éxito
                    $imagenFinal = (string) ($datosProducto['imagen'] ?? '');
                    if ($imagenAnteriorBD !== '' && $imagenAnteriorBD !== $imagenFinal) {
                        $gestorProductos->eliminarArchivoPortada($imagenAnteriorBD, $idEditar);
                    }
                    establecerFlash('exito', 'Producto «' . trim((string) $datosProducto['titulo']) . '» actualizado correctamente.');
                } else {
                    $nuevoId = $gestorProductos->crear($datosProducto);
                    establecerFlash('exito', 'Nuevo libro «' . trim((string) $datosProducto['titulo']) . '» añadido con éxito (ID #' . $nuevoId . ').');
                }

                header('Location: administracion.php');
                exit;

            case 'cambiar_estado_producto':
                $idProd      = (int) ($_POST['producto_id'] ?? 0);
                $nuevoEstado = (int) ($_POST['nuevo_estado'] ?? 0) === 1;
                $gestorProductos->cambiarEstado($idProd, $nuevoEstado);
                establecerFlash(
                    'exito',
                    $nuevoEstado ? 'Producto activado en el catálogo público.' : 'Producto desactivado del catálogo público.'
                );
                header('Location: administracion.php');
                exit;

            case 'eliminar_producto':
                $idProd    = (int) ($_POST['producto_id'] ?? 0);
                $resultado = $gestorProductos->eliminar($idProd);
                establecerFlash($resultado['eliminado'] ? 'exito' : 'info', $resultado['mensaje']);
                header('Location: administracion.php');
                exit;

            case 'guardar_categoria':
                $idCat = isset($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : 0;
                $datosCat = [
                    'nombre'      => $_POST['nombre'] ?? '',
                    'descripcion' => $_POST['descripcion'] ?? '',
                    'color_fondo' => $_POST['color_fondo'] ?? '#e9f1ff',
                    'activa'      => isset($_POST['activa']) ? 1 : 0,
                ];

                if ($idCat > 0) {
                    $gestorCategorias->actualizar($idCat, $datosCat);
                    establecerFlash('exito', 'Categoría «' . trim((string) $datosCat['nombre']) . '» actualizada.');
                } else {
                    $gestorCategorias->crear($datosCat);
                    establecerFlash('exito', 'Categoría «' . trim((string) $datosCat['nombre']) . '» creada correctamente.');
                }

                header('Location: administracion.php?seccion=categorias');
                exit;

            case 'cambiar_estado_categoria':
                $idCat       = (int) ($_POST['categoria_id'] ?? 0);
                $nuevoEstado = (int) ($_POST['nuevo_estado'] ?? 0) === 1;
                $gestorCategorias->cambiarEstado($idCat, $nuevoEstado);
                establecerFlash('exito', $nuevoEstado ? 'Categoría activada.' : 'Categoría desactivada.');
                header('Location: administracion.php?seccion=categorias');
                exit;

            case 'eliminar_categoria':
                $idCat     = (int) ($_POST['categoria_id'] ?? 0);
                $resultado = $gestorCategorias->eliminar($idCat);
                establecerFlash($resultado['eliminada'] ? 'exito' : 'info', $resultado['mensaje']);
                header('Location: administracion.php?seccion=categorias');
                exit;
        }
    } catch (Throwable $ex) {
        if ($nuevaSubida !== null) {
            $gestorProductos->eliminarArchivoPortada($nuevaSubida);
        }
        $erroresFormulario[] = $ex->getMessage();
    }
}

// ============================================================================
// CONSULTA DE DATOS PARA LA INTERFAZ DE ADMINISTRACIÓN
// ============================================================================
$seccionActiva   = ($_GET['seccion'] ?? 'productos') === 'categorias' ? 'categorias' : 'productos';
$filtroBusqueda  = trim((string) ($_GET['q'] ?? ''));
$filtroCategoria = isset($_GET['categoria_id']) ? (int) $_GET['categoria_id'] : 0;
$filtroEstado    = (string) ($_GET['estado'] ?? 'todos');
if (!in_array($filtroEstado, ['todos', 'activos', 'inactivos'], true)) {
    $filtroEstado = 'todos';
}

$categorias   = $gestorCategorias->obtenerTodas(false);
$estadisticas = $gestorProductos->obtenerEstadisticas();
$productos    = $gestorProductos->obtenerTodos([
    'estado'       => $filtroEstado,
    'categoria_id' => $filtroCategoria > 0 ? $filtroCategoria : null,
    'q'            => $filtroBusqueda,
    'orden'        => 'recientes',
]);

// Determinar si hay un producto abierto para crear o editar
$productoEnEdicion = null;
$abrirModalProducto = false;

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (string) ($_POST['accion'] ?? '') === 'guardar_producto'
    && !empty($erroresFormulario)
) {
    $idPost     = isset($_POST['producto_id']) ? (int) $_POST['producto_id'] : 0;
    $previoPost = $idPost > 0 ? $gestorProductos->obtenerPorId($idPost, false) : null;
    $elimPost   = !empty($_POST['eliminar_imagen']) && (string) $_POST['eliminar_imagen'] === '1';

    $abrirModalProducto = true;
    $productoEnEdicion  = [
        'id'               => $idPost,
        'titulo'           => (string) ($_POST['titulo'] ?? ''),
        'autor'            => (string) ($_POST['autor'] ?? ''),
        'isbn'             => (string) ($_POST['isbn'] ?? ''),
        'descripcion'      => (string) ($_POST['descripcion'] ?? ''),
        'precio'           => (string) ($_POST['precio'] ?? '19.90'),
        'stock'            => (int) ($_POST['stock'] ?? 10),
        'categoria_id'     => (int) ($_POST['categoria_id'] ?? ($categorias[0]['id'] ?? 1)),
        'valoracion'       => (string) ($_POST['valoracion'] ?? '4.8'),
        'color_primario'   => (string) ($_POST['color_primario'] ?? ($previoPost['color_primario'] ?? '#0d223c')),
        'color_secundario' => (string) ($_POST['color_secundario'] ?? ($previoPost['color_secundario'] ?? '#d89d4a')),
        'sello_editorial'  => (string) ($_POST['sello_editorial'] ?? ($previoPost['sello_editorial'] ?? '')),
        'imagen'           => $elimPost ? '' : (string) ($previoPost['imagen'] ?? ''),
        'activo'           => isset($_POST['activo']) ? 1 : 0,
        'destacado'        => isset($_POST['destacado']) ? 1 : 0,
    ];
} elseif (isset($_GET['editar'])) {
    $productoEnEdicion  = $gestorProductos->obtenerPorId((int) $_GET['editar'], false);
    $abrirModalProducto = $productoEnEdicion !== null;
} elseif (isset($_GET['accion']) && $_GET['accion'] === 'nuevo') {
    $abrirModalProducto = true;
    $productoEnEdicion  = [
        'id'               => 0,
        'titulo'           => '',
        'autor'            => '',
        'isbn'             => '',
        'descripcion'      => '',
        'precio'           => '19.90',
        'stock'            => 10,
        'categoria_id'     => $categorias[0]['id'] ?? 1,
        'valoracion'       => '4.8',
        'color_primario'   => '#0d223c',
        'color_secundario' => '#d89d4a',
        'sello_editorial'  => '',
        'imagen'           => '',
        'activo'           => 1,
        'destacado'        => 0,
    ];
}

// Determinar si hay una categoría abierta para crear o editar
$categoriaEnEdicion  = null;
$abrirModalCategoria = false;
if (isset($_GET['editar_cat'])) {
    $categoriaEnEdicion  = $gestorCategorias->obtenerPorId((int) $_GET['editar_cat']);
    $abrirModalCategoria = $categoriaEnEdicion !== null;
    $seccionActiva       = 'categorias';
} elseif (isset($_GET['accion_cat']) && $_GET['accion_cat'] === 'nueva') {
    $abrirModalCategoria = true;
    $seccionActiva       = 'categorias';
    $categoriaEnEdicion  = [
        'id'          => 0,
        'nombre'      => '',
        'descripcion' => '',
        'color_fondo' => '#e9f1ff',
        'activa'      => 1,
    ];
}

$rutaBase      = '../';
$paginaActual  = 'administracion';
$tituloPagina  = 'Panel de Administración — LibreShop';
$mostrarFooter = false; // Fiel al diseño original de Figma Make (view !== "admin" oculta el footer)

require_once __DIR__ . '/../includes/Cabecera.php';
?>

<main class="min-h-[calc(100vh-74px)] bg-slate-50 py-10">
    <div class="page">
        <!-- Cabecera del panel de administración -->
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="eyebrow">Panel de administración</p>
                <h1 class="page-title">Gestión de productos</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Administra el catálogo, las categorías, el precio, las portadas y el inventario en MySQL.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a
                    href="administracion.php?seccion=categorias&amp;accion_cat=nueva"
                    class="btn-secondary"
                >
                    <?= icono('box', 18) ?> Nueva categoría
                </a>
                <a
                    href="administracion.php?accion=nuevo"
                    class="btn-primary"
                >
                    <?= icono('plus', 18) ?> Añadir producto
                </a>
            </div>
        </div>

        <?php if (!empty($erroresFormulario)): ?>
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <?php foreach ($erroresFormulario as $err): ?>
                    <p class="font-semibold"><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Tarjetas de resumen de inventario -->
        <div class="mt-7 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <span class="meta">Libros en catálogo</span>
                <strong class="text-2xl font-bold text-[#142943]"><?= (int) $estadisticas['total_productos'] ?></strong>
                <span class="mt-1 block text-xs text-slate-500">
                    <?= (int) $estadisticas['productos_activos'] ?> activos · <?= (int) $estadisticas['productos_inactivos'] ?> inactivos
                </span>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <span class="meta">Unidades en stock</span>
                <strong class="text-2xl font-bold text-[#1760DC]"><?= (int) $estadisticas['stock_total'] ?></strong>
                <span class="mt-1 block text-xs text-slate-500">Inventario total disponible</span>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <span class="meta">Categorías</span>
                <strong class="text-2xl font-bold text-[#142943]"><?= count($categorias) ?></strong>
                <span class="mt-1 block text-xs text-slate-500">Clasificación activa en tienda</span>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <span class="meta">Alerta de stock</span>
                <strong class="text-2xl font-bold <?= $estadisticas['sin_stock'] > 0 ? 'text-amber-600' : 'text-emerald-600' ?>">
                    <?= (int) $estadisticas['sin_stock'] ?>
                </strong>
                <span class="mt-1 block text-xs text-slate-500">Libros agotados (stock 0)</span>
            </div>
        </div>

        <!-- Selector de pestaña: Productos vs Categorías -->
        <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-4">
            <div class="flex flex-wrap gap-2">
                <a
                    href="administracion.php?seccion=productos"
                    class="filter-pill <?= $seccionActiva === 'productos' ? 'active' : '' ?>"
                >
                    Libros (<?= count($productos) ?>)
                </a>
                <a
                    href="administracion.php?seccion=categorias"
                    class="filter-pill <?= $seccionActiva === 'categorias' ? 'active' : '' ?>"
                >
                    Categorías (<?= count($categorias) ?>)
                </a>
            </div>

            <?php if ($seccionActiva === 'productos'): ?>
                <form method="get" action="administracion.php" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="seccion" value="productos" />
                    <select
                        name="categoria_id"
                        onchange="this.form.submit()"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-[#142943]"
                    >
                        <option value="0">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= $filtroCategoria === (int) $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select
                        name="estado"
                        onchange="this.form.submit()"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-[#142943]"
                    >
                        <option value="todos" <?= $filtroEstado === 'todos' ? 'selected' : '' ?>>Estado: Todos</option>
                        <option value="activos" <?= $filtroEstado === 'activos' ? 'selected' : '' ?>>Solo activos</option>
                        <option value="inactivos" <?= $filtroEstado === 'inactivos' ? 'selected' : '' ?>>Solo inactivos</option>
                    </select>
                    <input
                        type="search"
                        name="q"
                        value="<?= e($filtroBusqueda) ?>"
                        placeholder="Filtrar título, autor, ISBN..."
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-[#142943]"
                    />
                    <button type="submit" class="rounded-lg bg-[#142943] px-3 py-2 text-xs font-bold text-white">
                        Filtrar
                    </button>
                    <?php if ($filtroBusqueda !== '' || $filtroCategoria > 0 || $filtroEstado !== 'todos'): ?>
                        <a href="administracion.php" class="text-xs font-semibold text-[#1760DC]">Limpiar</a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($seccionActiva === 'productos'): ?>
            <!-- ==============================================================
                 TABLA DE GESTIÓN DE PRODUCTOS (Diseño original Figma Make)
                 ============================================================== -->
            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full min-w-[800px] text-left">
                    <thead class="bg-[#142943] text-[10px] uppercase tracking-[.12em] text-slate-300">
                        <tr>
                            <th>Producto</th>
                            <th>ISBN</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-sm text-slate-500">
                                    No se encontraron productos con los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $p): ?>
                                <?php $estaActivo = (int) $p['activo'] === 1; ?>
                                <tr class="border-t border-slate-100">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <?= renderizarPortada($p, 'h-14 aspect-[.68] shrink-0', '../') ?>
                                            <div>
                                                <a
                                                    href="detalle.php?id=<?= (int) $p['id'] ?>"
                                                    class="block text-sm font-bold text-[#142943] hover:text-[#1760DC] no-underline"
                                                >
                                                    <?= e($p['titulo']) ?>
                                                </a>
                                                <small class="block text-xs text-slate-400"><?= e($p['autor']) ?></small>
                                                <?php if ((int) $p['destacado'] === 1): ?>
                                                    <span class="mt-1 inline-block rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">
                                                        ★ Destacado
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-xs font-medium text-slate-500"><?= e($p['isbn']) ?></td>
                                    <td>
                                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                            <?= e($p['categoria_nombre']) ?>
                                        </span>
                                    </td>
                                    <td class="font-semibold text-[#142943]"><?= formatoPrecio((float) $p['precio']) ?></td>
                                    <td>
                                        <span class="font-semibold <?= (int) $p['stock'] <= 3 ? 'text-amber-600' : 'text-slate-700' ?>">
                                            <?= (int) $p['stock'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" action="administracion.php" class="inline">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="accion" value="cambiar_estado_producto" />
                                            <input type="hidden" name="producto_id" value="<?= (int) $p['id'] ?>" />
                                            <input type="hidden" name="nuevo_estado" value="<?= $estaActivo ? 0 : 1 ?>" />
                                            <button
                                                type="submit"
                                                title="Clic para <?= $estaActivo ? 'desactivar' : 'activar' ?>"
                                                class="flex items-center gap-2 text-xs font-semibold <?= $estaActivo ? 'text-emerald-700' : 'text-slate-400' ?>"
                                            >
                                                <i class="h-1.5 w-1.5 rounded-full <?= $estaActivo ? 'bg-emerald-500' : 'bg-slate-400' ?>"></i>
                                                <?= $estaActivo ? 'Activo' : 'Inactivo' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <a
                                                href="administracion.php?editar=<?= (int) $p['id'] ?>"
                                                class="admin-action"
                                                title="Editar producto"
                                                aria-label="Editar <?= e($p['titulo']) ?>"
                                            >
                                                <?= icono('edit', 16) ?>
                                            </a>
                                            <form
                                                method="post"
                                                action="administracion.php"
                                                data-confirmar="¿Deseas eliminar el libro «<?= e($p['titulo']) ?>»?"
                                            >
                                                <?= campoCSRF() ?>
                                                <input type="hidden" name="accion" value="eliminar_producto" />
                                                <input type="hidden" name="producto_id" value="<?= (int) $p['id'] ?>" />
                                                <button
                                                    type="submit"
                                                    class="admin-action hover:text-red-500"
                                                    title="Eliminar producto"
                                                    aria-label="Eliminar <?= e($p['titulo']) ?>"
                                                >
                                                    <?= icono('trash', 16) ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- ==============================================================
                 TABLA DE GESTIÓN DE CATEGORÍAS
                 ============================================================== -->
            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full min-w-[800px] text-left">
                    <thead class="bg-[#142943] text-[10px] uppercase tracking-[.12em] text-slate-300">
                        <tr>
                            <th>ID</th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                            <th>Color tarjeta</th>
                            <th>Libros activos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $c): ?>
                            <?php $catActiva = (int) $c['activa'] === 1; ?>
                            <tr class="border-t border-slate-100">
                                <td class="font-bold text-slate-400">#<?= (int) $c['id'] ?></td>
                                <td>
                                    <strong class="text-sm text-[#142943]"><?= e($c['nombre']) ?></strong>
                                </td>
                                <td class="text-slate-500"><?= e($c['descripcion']) ?></td>
                                <td>
                                    <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                                        <span class="h-5 w-5 rounded border border-slate-300" style="background: <?= e($c['color_fondo']) ?>"></span>
                                        <?= e($c['color_fondo']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                        <?= (int) ($c['total_productos'] ?? 0) ?> libros
                                    </span>
                                </td>
                                <td>
                                    <form method="post" action="administracion.php?seccion=categorias" class="inline">
                                        <?= campoCSRF() ?>
                                        <input type="hidden" name="accion" value="cambiar_estado_categoria" />
                                        <input type="hidden" name="categoria_id" value="<?= (int) $c['id'] ?>" />
                                        <input type="hidden" name="nuevo_estado" value="<?= $catActiva ? 0 : 1 ?>" />
                                        <button
                                            type="submit"
                                            class="flex items-center gap-2 text-xs font-semibold <?= $catActiva ? 'text-emerald-700' : 'text-slate-400' ?>"
                                        >
                                            <i class="h-1.5 w-1.5 rounded-full <?= $catActiva ? 'bg-emerald-500' : 'bg-slate-400' ?>"></i>
                                            <?= $catActiva ? 'Activa' : 'Inactiva' ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <a
                                            href="administracion.php?seccion=categorias&amp;editar_cat=<?= (int) $c['id'] ?>"
                                            class="admin-action"
                                            title="Editar categoría"
                                        >
                                            <?= icono('edit', 16) ?>
                                        </a>
                                        <form
                                            method="post"
                                            action="administracion.php?seccion=categorias"
                                            data-confirmar="¿Deseas eliminar o desactivar la categoría «<?= e($c['nombre']) ?>»?"
                                        >
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="accion" value="eliminar_categoria" />
                                            <input type="hidden" name="categoria_id" value="<?= (int) $c['id'] ?>" />
                                            <button type="submit" class="admin-action hover:text-red-500" title="Eliminar categoría">
                                                <?= icono('trash', 16) ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ==================================================================
             MODAL DE CREACIÓN / EDICIÓN DE PRODUCTO (Fiel a Figma Make)
             ================================================================== -->
        <?php if ($abrirModalProducto && $productoEnEdicion !== null): ?>
            <?php
            $esEdicionProd       = (int) ($productoEnEdicion['id'] ?? 0) > 0;
            $imagenGuardada      = trim((string) ($productoEnEdicion['imagen'] ?? ''));
            $tieneImagenGuardada = $imagenGuardada !== '';
            $urlImagenGuardada   = '';
            if ($tieneImagenGuardada) {
                $urlImagenGuardada = preg_match('#^https?://#i', $imagenGuardada)
                    ? $imagenGuardada
                    : '../' . ltrim($imagenGuardada, '/');
            }
            ?>
            <div id="modal-producto" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-[#071423]/60 p-4 backdrop-blur-sm">
                <form
                    id="form-producto-admin"
                    method="post"
                    action="administracion.php"
                    enctype="multipart/form-data"
                    class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl my-7"
                >
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="guardar_producto" />
                    <input type="hidden" name="producto_id" value="<?= (int) ($productoEnEdicion['id'] ?? 0) ?>" />
                    <input type="hidden" id="input-imagen-actual" name="imagen_actual" value="<?= e($imagenGuardada) ?>" />
                    <input type="hidden" id="input-eliminar-imagen" name="eliminar_imagen" value="0" />

                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <h2 class="text-xl font-bold text-[#142943]">
                            <?= $esEdicionProd ? 'Editar producto #' . (int) $productoEnEdicion['id'] : 'Añadir nuevo producto' ?>
                        </h2>
                        <a href="administracion.php" class="text-slate-400 hover:text-slate-700" aria-label="Cerrar">
                            <?= icono('close', 20) ?>
                        </a>
                    </div>

                    <?php if (!empty($erroresFormulario)): ?>
                        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-xs font-semibold text-red-700">
                            <?php foreach ($erroresFormulario as $errModal): ?>
                                <p><?= e($errModal) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-5 grid gap-5 md:grid-cols-[150px_1fr]">
                        <!-- Vista previa en vivo de la portada -->
                        <div class="flex flex-col items-center">
                            <span class="meta mb-2">Vista previa</span>
                            <div
                                id="preview-book-cover"
                                class="book-cover relative isolate overflow-hidden rounded-[3px] shadow-[8px_10px_22px_rgba(15,31,52,.22)] h-48 aspect-[.68]"
                                style="background: linear-gradient(145deg, <?= e($productoEnEdicion['color_primario'] ?? '#0d223c') ?>, <?= e($productoEnEdicion['color_secundario'] ?? '#d89d4a') ?>)"
                            >
                                <img
                                    id="preview-book-img"
                                    src="<?= e($urlImagenGuardada) ?>"
                                    alt="Vista previa de portada"
                                    class="absolute inset-0 h-full w-full object-cover <?= $tieneImagenGuardada ? '' : 'hidden' ?>"
                                />
                                <div class="absolute inset-y-0 left-0 w-[7%] bg-black/15 z-10"></div>
                                <div id="preview-editorial-content" class="h-full w-full <?= $tieneImagenGuardada ? 'hidden' : '' ?>">
                                    <div class="absolute -right-8 top-[14%] h-28 w-28 rounded-full border border-white/35"></div>
                                    <div class="absolute -right-3 top-[22%] h-16 w-16 rounded-full border border-white/20"></div>
                                    <div class="relative flex h-full flex-col px-[13%] py-[14%] text-white">
                                        <span id="preview-book-autor" class="text-[8px] font-semibold uppercase tracking-[.22em] opacity-80">
                                            <?= e(($productoEnEdicion['autor'] ?? '') !== '' ? $productoEnEdicion['autor'] : 'LibreShop') ?>
                                        </span>
                                        <strong id="preview-book-sello" class="mt-auto max-w-[90%] text-sm font-bold leading-[.94] tracking-[-.06em]">
                                            <?= e(($productoEnEdicion['sello_editorial'] ?? '') !== '' ? $productoEnEdicion['sello_editorial'] : 'NUEVO LIBRO') ?>
                                        </strong>
                                        <span class="mt-2 h-px w-7 bg-white/70"></span>
                                        <span class="mt-2 text-[7px] uppercase tracking-[.2em] opacity-80">LibreShop editorial</span>
                                    </div>
                                </div>
                            </div>
                            <span
                                id="preview-modo-badge"
                                class="mt-2 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[.08em] <?= $tieneImagenGuardada ? 'bg-blue-50 text-[#1760DC]' : 'bg-slate-100 text-slate-600' ?>"
                            >
                                <?= $tieneImagenGuardada ? 'Imagen de portada' : 'Cubierta editorial' ?>
                            </span>
                        </div>

                        <!-- Campos principales -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="field-label sm:col-span-2">
                                Título *
                                <input
                                    id="prod-titulo"
                                    name="titulo"
                                    required
                                    value="<?= e($productoEnEdicion['titulo'] ?? '') ?>"
                                    placeholder="Ej. La sombra del viento"
                                    class="input mt-1.5"
                                />
                            </label>
                            <label class="field-label">
                                Autor *
                                <input
                                    id="prod-autor"
                                    name="autor"
                                    required
                                    value="<?= e($productoEnEdicion['autor'] ?? '') ?>"
                                    placeholder="Ej. Carlos Ruiz Zafón"
                                    class="input mt-1.5"
                                />
                            </label>
                            <label class="field-label">
                                ISBN
                                <input
                                    name="isbn"
                                    value="<?= e($productoEnEdicion['isbn'] ?? '') ?>"
                                    placeholder="978-84-..."
                                    class="input mt-1.5"
                                />
                            </label>
                            <label class="field-label">
                                Precio (€) *
                                <input
                                    name="precio"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    value="<?= e((string) ($productoEnEdicion['precio'] ?? '19.90')) ?>"
                                    class="input mt-1.5"
                                />
                            </label>
                            <label class="field-label">
                                Stock (unidades) *
                                <input
                                    name="stock"
                                    type="number"
                                    min="0"
                                    required
                                    value="<?= (int) ($productoEnEdicion['stock'] ?? 10) ?>"
                                    class="input mt-1.5"
                                />
                            </label>
                            <label class="field-label">
                                Categoría *
                                <select name="categoria_id" class="input mt-1.5" required>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option
                                            value="<?= (int) $cat['id'] ?>"
                                            <?= (int) ($productoEnEdicion['categoria_id'] ?? 1) === (int) $cat['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e($cat['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field-label">
                                Valoración (1.0 – 5.0)
                                <input
                                    name="valoracion"
                                    type="number"
                                    step="0.1"
                                    min="1"
                                    max="5"
                                    value="<?= e((string) ($productoEnEdicion['valoracion'] ?? '4.8')) ?>"
                                    class="input mt-1.5"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <label class="field-label sm:col-span-3">
                            Descripción / Sinopsis *
                            <textarea
                                name="descripcion"
                                required
                                placeholder="Escribe la sinopsis del libro..."
                                class="input mt-1.5"
                            ><?= e($productoEnEdicion['descripcion'] ?? '') ?></textarea>
                        </label>

                        <!-- Subida y gestión de imagen de portada -->
                        <div id="seccion-subida-portada" class="sm:col-span-3">
                            <span class="field-label">Imagen de portada (opcional)</span>
                            <input
                                id="prod-imagen-archivo"
                                name="imagen_archivo"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                class="hidden"
                            />

                            <label
                                id="zona-selector-imagen"
                                for="prod-imagen-archivo"
                                class="mt-1.5 flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 py-4 px-4 text-sm font-medium text-slate-600 transition hover:border-blue-200 hover:text-[#1760DC] <?= $tieneImagenGuardada ? 'hidden' : '' ?>"
                            >
                                <?= icono('upload', 18) ?>
                                <span id="nombre-archivo-portada">Subir imagen de portada (JPG, PNG, WebP · máx. 3 MB)</span>
                            </label>

                            <div
                                id="panel-imagen-activa"
                                class="mt-1.5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 p-3 <?= $tieneImagenGuardada ? '' : 'hidden' ?>"
                                data-imagen-inicial="<?= e($urlImagenGuardada) ?>"
                                data-nombre-inicial="<?= e($imagenGuardada !== '' ? basename($imagenGuardada) : '') ?>"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <img
                                        id="thumb-imagen-activa"
                                        src="<?= e($urlImagenGuardada) ?>"
                                        alt="Miniatura de portada"
                                        class="h-14 w-10 shrink-0 rounded border border-slate-200 bg-white object-cover shadow-sm"
                                    />
                                    <div class="min-w-0">
                                        <span id="estado-imagen-activa" class="block text-[10px] font-bold uppercase tracking-[.12em] text-[#1760DC]">
                                            <?= $tieneImagenGuardada ? 'Portada actual guardada' : 'Nueva portada seleccionada' ?>
                                        </span>
                                        <strong id="nombre-imagen-activa" class="block truncate text-xs font-bold text-[#142943]">
                                            <?= e($imagenGuardada !== '' ? basename($imagenGuardada) : '') ?>
                                        </strong>
                                        <span class="block text-[11px] text-slate-500">
                                            La imagen oculta y reemplaza la cubierta editorial de colores.
                                        </span>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        id="btn-cambiar-imagen"
                                        class="btn-secondary min-h-0 h-9 px-3 py-1.5 text-xs"
                                    >
                                        <?= icono('upload', 14) ?>
                                        <span id="texto-btn-cambiar"><?= $tieneImagenGuardada ? 'Sustituir imagen' : 'Cambiar imagen' ?></span>
                                    </button>
                                    <button
                                        type="button"
                                        id="btn-retirar-imagen"
                                        class="btn-danger-outline min-h-0 h-9 px-3 py-1.5 text-xs"
                                    >
                                        <?= icono('trash', 14) ?>
                                        <span id="texto-btn-retirar"><?= $tieneImagenGuardada ? 'Eliminar portada' : 'Retirar imagen' ?></span>
                                    </button>
                                </div>
                            </div>

                            <p id="aviso-imagen-portada" class="mt-1.5 hidden text-xs font-semibold text-red-600"></p>
                        </div>

                        <!-- Personalización de cubierta editorial (colores + sello extra), visible solo cuando NO hay imagen -->
                        <div
                            id="seccion-personalizacion-cubierta"
                            class="sm:col-span-3 rounded-xl border border-slate-200 bg-slate-50 p-4 <?= $tieneImagenGuardada ? 'hidden' : '' ?>"
                        >
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs font-bold uppercase tracking-[.1em] text-[#142943]">
                                    Personalización de cubierta editorial
                                </span>
                                <span class="text-[11px] text-slate-500">
                                    Se aplica cuando el libro no dispone de imagen de portada
                                </span>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div id="bloque-personalizacion-extra">
                                    <label class="field-label">
                                        Sello en portada
                                        <input
                                            id="prod-sello"
                                            name="sello_editorial"
                                            value="<?= e($productoEnEdicion['sello_editorial'] ?? '') ?>"
                                            placeholder="Ej. LAS SOMBRAS"
                                            class="input mt-1.5"
                                        />
                                    </label>
                                </div>
                                <div id="bloque-colores-cubierta" class="grid grid-cols-2 gap-4 sm:col-span-2">
                                    <label class="field-label">
                                        Color cubierta 1
                                        <input
                                            id="prod-color1"
                                            name="color_primario"
                                            type="color"
                                            value="<?= e($productoEnEdicion['color_primario'] ?? '#0d223c') ?>"
                                            class="input mt-1.5 p-1"
                                        />
                                    </label>
                                    <label class="field-label">
                                        Color cubierta 2
                                        <input
                                            id="prod-color2"
                                            name="color_secundario"
                                            type="color"
                                            value="<?= e($productoEnEdicion['color_secundario'] ?? '#d89d4a') ?>"
                                            class="input mt-1.5 p-1"
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-6 sm:col-span-3 pt-2">
                            <label class="flex items-center gap-2 text-sm font-semibold text-[#142943]">
                                <input
                                    type="checkbox"
                                    name="activo"
                                    value="1"
                                    <?= (int) ($productoEnEdicion['activo'] ?? 1) === 1 ? 'checked' : '' ?>
                                />
                                Producto activo (visible en tienda)
                            </label>
                            <label class="flex items-center gap-2 text-sm font-semibold text-[#142943]">
                                <input
                                    type="checkbox"
                                    name="destacado"
                                    value="1"
                                    <?= (int) ($productoEnEdicion['destacado'] ?? 0) === 1 ? 'checked' : '' ?>
                                />
                                Mostrar en Libros destacados (Inicio)
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <a href="administracion.php" class="btn-secondary">Cancelar</a>
                        <button type="submit" class="btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- ==================================================================
             MODAL DE CREACIÓN / EDICIÓN DE CATEGORÍA
             ================================================================== -->
        <?php if ($abrirModalCategoria && $categoriaEnEdicion !== null): ?>
            <?php $esEdicionCat = (int) ($categoriaEnEdicion['id'] ?? 0) > 0; ?>
            <div id="modal-categoria" class="fixed inset-0 z-50 grid place-items-center bg-[#071423]/60 p-4 backdrop-blur-sm">
                <form
                    method="post"
                    action="administracion.php?seccion=categorias"
                    class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl"
                >
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="guardar_categoria" />
                    <input type="hidden" name="categoria_id" value="<?= (int) ($categoriaEnEdicion['id'] ?? 0) ?>" />

                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-bold text-[#142943]">
                            <?= $esEdicionCat ? 'Editar categoría' : 'Nueva categoría' ?>
                        </h2>
                        <a href="administracion.php?seccion=categorias" class="text-slate-400 hover:text-slate-700">
                            <?= icono('close', 20) ?>
                        </a>
                    </div>

                    <div class="mt-6 grid gap-4">
                        <label class="field-label">
                            Nombre de la categoría *
                            <input
                                name="nombre"
                                required
                                value="<?= e($categoriaEnEdicion['nombre'] ?? '') ?>"
                                placeholder="Ej. Ciencia Ficción"
                                class="input mt-2"
                            />
                        </label>
                        <label class="field-label">
                            Descripción *
                            <input
                                name="descripcion"
                                required
                                value="<?= e($categoriaEnEdicion['descripcion'] ?? '') ?>"
                                placeholder="Subtítulo descriptivo para la portada..."
                                class="input mt-2"
                            />
                        </label>
                        <label class="field-label">
                            Color de fondo en portada
                            <input
                                name="color_fondo"
                                type="color"
                                value="<?= e($categoriaEnEdicion['color_fondo'] ?? '#e9f1ff') ?>"
                                class="input mt-2 p-1"
                            />
                        </label>
                        <label class="flex items-center gap-2 text-sm font-semibold text-[#142943]">
                            <input
                                type="checkbox"
                                name="activa"
                                value="1"
                                <?= (int) ($categoriaEnEdicion['activa'] ?? 1) === 1 ? 'checked' : '' ?>
                            />
                            Categoría activa en tienda
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="administracion.php?seccion=categorias" class="btn-secondary">Cancelar</a>
                        <button type="submit" class="btn-primary">Guardar categoría</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/Footer.php'; ?>
