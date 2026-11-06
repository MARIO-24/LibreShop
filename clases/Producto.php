<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Clase Producto
 *
 * Encapsula toda la lógica de negocio y persistencia en MySQL para el catálogo
 * y el gestor de productos (alta, consulta, filtrado, edición, activación,
 * baja física/lógica y subida segura de portadas).
 */
class Producto
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::conectar();
    }

    /**
     * Consulta dinámica de productos desde MySQL con soporte para filtros.
     *
     * Filtros admitidos:
     * - 'categoria_id'     (int)
     * - 'categoria_nombre' (string)
     * - 'q'                (string: busca por título, autor o ISBN)
     * - 'estado'           ('activos' | 'inactivos' | 'todos') — por defecto 'activos'
     * - 'destacado'        (bool)
     * - 'orden'            ('recomendados' | 'precio_asc' | 'precio_desc' | 'titulo_asc' | 'recientes')
     * - 'limite'           (int)
     *
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    public function obtenerTodos(array $filtros = []): array
    {
        $condiciones = [];
        $parametros  = [];

        $estado = (string) ($filtros['estado'] ?? 'activos');
        if ($estado === 'activos') {
            $condiciones[] = 'p.activo = 1';
            $condiciones[] = 'c.activa = 1';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'p.activo = 0';
        }

        if (!empty($filtros['categoria_id'])) {
            $condiciones[]              = 'p.categoria_id = :categoria_id';
            $parametros[':categoria_id'] = (int) $filtros['categoria_id'];
        }

        if (!empty($filtros['categoria_nombre']) && $filtros['categoria_nombre'] !== 'Todos') {
            $condiciones[]                  = 'c.nombre = :categoria_nombre';
            $parametros[':categoria_nombre'] = trim((string) $filtros['categoria_nombre']);
        }

        if (isset($filtros['destacado'])) {
            $condiciones[]           = 'p.destacado = :destacado';
            $parametros[':destacado'] = (int) (bool) $filtros['destacado'];
        }

        $busqueda = trim((string) ($filtros['q'] ?? ''));
        if ($busqueda !== '') {
            $condiciones[]          = '(p.titulo LIKE :q1 OR p.autor LIKE :q2 OR p.isbn LIKE :q3)';
            $termino                = '%' . $busqueda . '%';
            $parametros[':q1']      = $termino;
            $parametros[':q2']      = $termino;
            $parametros[':q3']      = $termino;
        }

        $sql = 'SELECT p.id, p.titulo, p.autor, p.descripcion, p.precio, p.imagen,
                       p.isbn, p.stock, p.categoria_id, p.activo, p.valoracion,
                       p.color_primario, p.color_secundario, p.sello_editorial,
                       p.destacado, p.fecha_creacion,
                       c.nombre AS categoria_nombre, c.activa AS categoria_activa
                FROM productos p
                INNER JOIN categorias c ON c.id = p.categoria_id';

        if (!empty($condiciones)) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        $orden = (string) ($filtros['orden'] ?? 'recomendados');
        switch ($orden) {
            case 'precio_asc':
                $sql .= ' ORDER BY p.precio ASC, p.id ASC';
                break;
            case 'precio_desc':
                $sql .= ' ORDER BY p.precio DESC, p.id ASC';
                break;
            case 'titulo_asc':
                $sql .= ' ORDER BY p.titulo ASC';
                break;
            case 'recientes':
                $sql .= ' ORDER BY p.id DESC';
                break;
            case 'recomendados':
            default:
                $sql .= ' ORDER BY p.destacado DESC, p.valoracion DESC, p.id ASC';
                break;
        }

        if (!empty($filtros['limite']) && (int) $filtros['limite'] > 0) {
            $sql .= ' LIMIT ' . (int) $filtros['limite'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    /**
     * Obtiene los productos destacados para la portada.
     *
     * @param int $limite Número máximo de libros a mostrar.
     * @return array<int, array<string, mixed>>
     */
    public function obtenerDestacados(int $limite = 4): array
    {
        return $this->obtenerTodos([
            'estado' => 'activos',
            'orden'  => 'recomendados',
            'limite' => $limite,
        ]);
    }

    /**
     * Obtiene un producto por su ID primario.
     *
     * @param int  $id         ID del libro.
     * @param bool $soloActivo Si es true, solo lo devuelve si está activo.
     * @return array<string, mixed>|null
     */
    public function obtenerPorId(int $id, bool $soloActivo = false): ?array
    {
        $sql = 'SELECT p.id, p.titulo, p.autor, p.descripcion, p.precio, p.imagen,
                       p.isbn, p.stock, p.categoria_id, p.activo, p.valoracion,
                       p.color_primario, p.color_secundario, p.sello_editorial,
                       p.destacado, p.fecha_creacion,
                       c.nombre AS categoria_nombre
                FROM productos p
                INNER JOIN categorias c ON c.id = p.categoria_id
                WHERE p.id = :id';

        if ($soloActivo) {
            $sql .= ' AND p.activo = 1 AND c.activa = 1';
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila !== false ? $fila : null;
    }

    /**
     * Obtiene libros relacionados de la misma categoría excluyendo el libro actual.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerRelacionados(int $categoriaId, int $excluirId, int $limite = 4): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.titulo, p.autor, p.descripcion, p.precio, p.imagen,
                    p.isbn, p.stock, p.categoria_id, p.activo, p.valoracion,
                    p.color_primario, p.color_secundario, p.sello_editorial,
                    p.destacado, c.nombre AS categoria_nombre
             FROM productos p
             INNER JOIN categorias c ON c.id = p.categoria_id
             WHERE p.categoria_id = :categoria_id
               AND p.id <> :excluir_id
               AND p.activo = 1
               AND c.activa = 1
             ORDER BY p.valoracion DESC, p.id ASC
             LIMIT ' . max(1, $limite)
        );
        $stmt->execute([
            ':categoria_id' => $categoriaId,
            ':excluir_id'   => $excluirId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Obtiene estadísticas globales del inventario para el panel de administración.
     *
     * @return array<string, int>
     */
    public function obtenerEstadisticas(): array
    {
        $sql = 'SELECT
                    COUNT(*) AS total_productos,
                    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) AS productos_activos,
                    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) AS productos_inactivos,
                    COALESCE(SUM(stock), 0) AS stock_total,
                    SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS sin_stock
                FROM productos';
        $fila = $this->db->query($sql)->fetch();

        return [
            'total_productos'     => (int) ($fila['total_productos'] ?? 0),
            'productos_activos'   => (int) ($fila['productos_activos'] ?? 0),
            'productos_inactivos' => (int) ($fila['productos_inactivos'] ?? 0),
            'stock_total'         => (int) ($fila['stock_total'] ?? 0),
            'sin_stock'           => (int) ($fila['sin_stock'] ?? 0),
        ];
    }

    /**
     * Comprueba si un ISBN ya está registrado en otro producto.
     */
    public function existeIsbn(string $isbn, ?int $excluirId = null): bool
    {
        $sql    = 'SELECT id FROM productos WHERE isbn = :isbn';
        $params = [':isbn' => trim($isbn)];

        if ($excluirId !== null) {
            $sql                  .= ' AND id <> :excluir_id';
            $params[':excluir_id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /**
     * Da de alta un nuevo libro en la base de datos.
     *
     * @param array<string, mixed> $datos
     * @return int ID del producto insertado.
     * @throws InvalidArgumentException Si algún campo no es válido.
     */
    public function crear(array $datos): int
    {
        $limpios = $this->validarYNormalizar($datos, null);

        $stmt = $this->db->prepare(
            'INSERT INTO productos (
                titulo, autor, descripcion, precio, imagen, isbn,
                stock, categoria_id, activo, valoracion,
                color_primario, color_secundario, sello_editorial, destacado
             ) VALUES (
                :titulo, :autor, :descripcion, :precio, :imagen, :isbn,
                :stock, :categoria_id, :activo, :valoracion,
                :color_primario, :color_secundario, :sello_editorial, :destacado
             )'
        );

        $stmt->execute([
            ':titulo'           => $limpios['titulo'],
            ':autor'            => $limpios['autor'],
            ':descripcion'      => $limpios['descripcion'],
            ':precio'           => $limpios['precio'],
            ':imagen'           => $limpios['imagen'],
            ':isbn'             => $limpios['isbn'],
            ':stock'            => $limpios['stock'],
            ':categoria_id'     => $limpios['categoria_id'],
            ':activo'           => $limpios['activo'],
            ':valoracion'       => $limpios['valoracion'],
            ':color_primario'   => $limpios['color_primario'],
            ':color_secundario' => $limpios['color_secundario'],
            ':sello_editorial'  => $limpios['sello_editorial'],
            ':destacado'        => $limpios['destacado'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza un libro existente en la base de datos.
     *
     * @param int                  $id    ID del libro.
     * @param array<string, mixed> $datos Datos del formulario.
     * @return bool
     */
    public function actualizar(int $id, array $datos): bool
    {
        $actual = $this->obtenerPorId($id, false);
        if ($actual === null) {
            throw new InvalidArgumentException('El producto que intenta editar no existe.');
        }

        $datosCombinados = array_merge($actual, $datos);
        $limpios         = $this->validarYNormalizar($datosCombinados, $id);

        $stmt = $this->db->prepare(
            'UPDATE productos
             SET titulo           = :titulo,
                 autor            = :autor,
                 descripcion      = :descripcion,
                 precio           = :precio,
                 imagen           = :imagen,
                 isbn             = :isbn,
                 stock            = :stock,
                 categoria_id     = :categoria_id,
                 activo           = :activo,
                 valoracion       = :valoracion,
                 color_primario   = :color_primario,
                 color_secundario = :color_secundario,
                 sello_editorial  = :sello_editorial,
                 destacado        = :destacado
             WHERE id = :id'
        );

        return $stmt->execute([
            ':titulo'           => $limpios['titulo'],
            ':autor'            => $limpios['autor'],
            ':descripcion'      => $limpios['descripcion'],
            ':precio'           => $limpios['precio'],
            ':imagen'           => $limpios['imagen'],
            ':isbn'             => $limpios['isbn'],
            ':stock'            => $limpios['stock'],
            ':categoria_id'     => $limpios['categoria_id'],
            ':activo'           => $limpios['activo'],
            ':valoracion'       => $limpios['valoracion'],
            ':color_primario'   => $limpios['color_primario'],
            ':color_secundario' => $limpios['color_secundario'],
            ':sello_editorial'  => $limpios['sello_editorial'],
            ':destacado'        => $limpios['destacado'],
            ':id'               => $id,
        ]);
    }

    /**
     * Activa o desactiva un producto (baja/alta lógica).
     */
    public function cambiarEstado(int $id, bool $activo): bool
    {
        $stmt = $this->db->prepare('UPDATE productos SET activo = :activo WHERE id = :id');
        return $stmt->execute([
            ':activo' => $activo ? 1 : 0,
            ':id'     => $id,
        ]);
    }

    /**
     * Elimina un producto si no tiene pedidos asociados; en caso contrario, lo desactiva.
     *
     * @return array{eliminado: bool, desactivado: bool, mensaje: string}
     */
    public function eliminar(int $id): array
    {
        $producto = $this->obtenerPorId($id, false);
        if ($producto === null) {
            throw new InvalidArgumentException('El producto indicado no existe.');
        }

        $stmtPedidos = $this->db->prepare('SELECT COUNT(*) FROM detalle_pedido WHERE producto_id = :id');
        $stmtPedidos->execute([':id' => $id]);
        $enPedidos = (int) $stmtPedidos->fetchColumn();

        if ($enPedidos > 0) {
            $this->cambiarEstado($id, false);
            return [
                'eliminado'   => false,
                'desactivado' => true,
                'mensaje'     => 'El libro figura en pedidos históricos, por lo que se ha desactivado del catálogo público para conservar la integridad.',
            ];
        }

        $stmt = $this->db->prepare('DELETE FROM productos WHERE id = :id');
        $stmt->execute([':id' => $id]);

        // Si tenía una imagen local subida, la limpiamos del disco de forma segura
        $this->eliminarArchivoPortada((string) ($producto['imagen'] ?? ''), $id);

        return [
            'eliminado'   => true,
            'desactivado' => false,
            'mensaje'     => 'Producto eliminado definitivamente del catálogo.',
        ];
    }

    /**
     * Elimina de forma segura un archivo de portada local si ya no está referenciado
     * por ningún otro libro en la base de datos.
     */
    public function eliminarArchivoPortada(?string $rutaImagen, ?int $excluirProductoId = null): bool
    {
        $ruta = trim((string) ($rutaImagen ?? ''));
        if ($ruta === '' || !str_starts_with($ruta, 'assets/img/portadas/')) {
            return false;
        }

        $nombreArchivo = basename($ruta);
        if (
            $nombreArchivo === ''
            || $nombreArchivo === '.'
            || $nombreArchivo === '..'
            || $nombreArchivo === '.gitkeep'
            || $nombreArchivo === '.htaccess'
        ) {
            return false;
        }

        $rutaNormalizada = 'assets/img/portadas/' . $nombreArchivo;

        // Comprobar que ningún otro libro del catálogo utilice esta misma imagen
        $sql    = 'SELECT COUNT(*) FROM productos WHERE imagen = :imagen';
        $params = [':imagen' => $rutaNormalizada];
        if ($excluirProductoId !== null && $excluirProductoId > 0) {
            $sql                  .= ' AND id <> :excluir_id';
            $params[':excluir_id'] = $excluirProductoId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }

        $rutaFisica = dirname(__DIR__) . '/assets/img/portadas/' . $nombreArchivo;
        if (is_file($rutaFisica)) {
            return @unlink($rutaFisica);
        }

        return false;
    }

    /**
     * Gestiona la subida segura de una imagen de portada al servidor.
     * Valida tamaño máximo, extensión original, ausencia de extensiones ejecutables,
     * MIME real mediante finfo, estructura de imagen y genera un nombre único seguro.
     *
     * @param array<string, mixed> $archivo Elemento de $_FILES['imagen_archivo'].
     * @return string|null Ruta relativa guardada o null si no se subió archivo.
     * @throws InvalidArgumentException Si el archivo no es una imagen válida o supera el tamaño.
     */
    public function procesarSubidaImagen(array $archivo): ?string
    {
        if (!isset($archivo['error']) || (int) $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $codigoError = (int) $archivo['error'];
        if ($codigoError === UPLOAD_ERR_INI_SIZE || $codigoError === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('La imagen de portada supera el tamaño máximo permitido (3 MB).');
        }

        if ($codigoError !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Error al subir el archivo de imagen (código ' . $codigoError . ').');
        }

        $tamanoMaximo = 3 * 1024 * 1024; // 3 MB
        $tamanoArchivo = (int) ($archivo['size'] ?? 0);
        if ($tamanoArchivo <= 0) {
            throw new InvalidArgumentException('El archivo de imagen subido está vacío.');
        }

        if ($tamanoArchivo > $tamanoMaximo) {
            throw new InvalidArgumentException('La imagen de portada no debe superar los 3 MB.');
        }

        $nombreOriginal = trim((string) ($archivo['name'] ?? ''));
        if ($nombreOriginal === '' || str_contains($nombreOriginal, "\0")) {
            throw new InvalidArgumentException('El nombre del archivo subido no es válido.');
        }

        // Rechazar nombres con extensiones ejecutables o de script (incluidas dobles extensiones)
        if (preg_match('/\.(php\d*|phtml|phar|pl|py|cgi|sh|exe|bat|cmd|js|jsp|asp|aspx|htm|html|svg|htaccess)\b/i', $nombreOriginal)) {
            throw new InvalidArgumentException('Archivo no permitido: no se admiten archivos ejecutables ni scripts.');
        }

        $extensionOriginal = strtolower((string) pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
        if ($extensionOriginal === '' || !in_array($extensionOriginal, $extensionesPermitidas, true)) {
            throw new InvalidArgumentException('Extensión de archivo no válida. Solo se permiten imágenes JPG, JPEG, PNG o WebP.');
        }

        $tmpName = (string) ($archivo['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new InvalidArgumentException('No se pudo procesar el archivo temporal subido.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($tmpName);

        $mimesPermitidos = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png'  => ['png'],
            'image/webp' => ['webp'],
        ];

        if (!isset($mimesPermitidos[$mime])) {
            throw new InvalidArgumentException('Formato de imagen no válido. El archivo debe ser una imagen real JPG, PNG o WebP.');
        }

        if (!in_array($extensionOriginal, $mimesPermitidos[$mime], true)) {
            throw new InvalidArgumentException('La extensión del archivo no coincide con su contenido real de imagen.');
        }

        $dimensiones = @getimagesize($tmpName);
        if ($dimensiones === false || (int) ($dimensiones[0] ?? 0) <= 0 || (int) ($dimensiones[1] ?? 0) <= 0) {
            throw new InvalidArgumentException('El archivo subido no contiene una imagen válida.');
        }

        $directorioDestino = dirname(__DIR__) . '/assets/img/portadas';
        if (!is_dir($directorioDestino) && !mkdir($directorioDestino, 0755, true) && !is_dir($directorioDestino)) {
            throw new RuntimeException('No se pudo crear el directorio de portadas.');
        }

        $extensionFinal = $mimesPermitidos[$mime][0];
        do {
            $nombreSeguro = 'portada_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extensionFinal;
            $rutaFisica   = $directorioDestino . '/' . $nombreSeguro;
        } while (file_exists($rutaFisica));

        if (!move_uploaded_file($tmpName, $rutaFisica)) {
            // Fallback para pruebas automatizadas CLI donde el archivo no procede de HTTP POST multipart
            if (!@copy($tmpName, $rutaFisica)) {
                throw new RuntimeException('No fue posible guardar la imagen subida en el servidor.');
            }
        }

        @chmod($rutaFisica, 0644);

        return 'assets/img/portadas/' . $nombreSeguro;
    }

    /**
     * Valida y normaliza todos los campos de un producto antes de guardar en BD.
     *
     * @param array<string, mixed> $datos
     * @param int|null             $idActual
     * @return array<string, mixed>
     */
    private function validarYNormalizar(array $datos, ?int $idActual): array
    {
        $titulo      = trim((string) ($datos['titulo'] ?? ''));
        $autor       = trim((string) ($datos['autor'] ?? ''));
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $precioRaw   = str_replace(',', '.', trim((string) ($datos['precio'] ?? '0')));
        $stockRaw    = trim((string) ($datos['stock'] ?? '0'));
        $categoriaId = (int) ($datos['categoria_id'] ?? 0);
        $isbn        = trim((string) ($datos['isbn'] ?? ''));
        $imagen      = isset($datos['imagen']) && trim((string) $datos['imagen']) !== ''
            ? trim((string) $datos['imagen'])
            : null;

        if ($titulo === '' || mb_strlen($titulo) > 180) {
            throw new InvalidArgumentException('El título del libro es obligatorio y no puede superar los 180 caracteres.');
        }

        if ($autor === '') {
            $autor = 'Autor Editorial LibreShop';
        } elseif (mb_strlen($autor) > 140) {
            throw new InvalidArgumentException('El nombre del autor no puede superar los 140 caracteres.');
        }

        if ($descripcion === '') {
            $descripcion = 'Edición cuidada de la colección LibreShop.';
        }

        if (!is_numeric($precioRaw) || (float) $precioRaw < 0) {
            throw new InvalidArgumentException('El precio debe ser un número válido mayor o igual a 0.');
        }
        $precio = round((float) $precioRaw, 2);

        if (!is_numeric($stockRaw) || (int) $stockRaw < 0) {
            throw new InvalidArgumentException('El stock debe ser un número entero mayor o igual a 0.');
        }
        $stock = (int) $stockRaw;

        // Validar categoría
        $stmtCat = $this->db->prepare('SELECT id FROM categorias WHERE id = :id LIMIT 1');
        $stmtCat->execute([':id' => $categoriaId]);
        if ($stmtCat->fetch() === false) {
            throw new InvalidArgumentException('Debe seleccionar una categoría válida.');
        }

        // Generar ISBN único si se dejó en blanco
        if ($isbn === '') {
            $isbn = '978-84-' . random_int(1000, 9999) . '-' . random_int(100, 999) . '-' . random_int(0, 9);
        }

        if ($this->existeIsbn($isbn, $idActual)) {
            throw new InvalidArgumentException('El ISBN indicado (' . $isbn . ') ya pertenece a otro libro del catálogo.');
        }

        $valoracion = isset($datos['valoracion']) && is_numeric((string) $datos['valoracion'])
            ? max(1.0, min(5.0, round((float) $datos['valoracion'], 1)))
            : 4.8;

        $colorPrimario = trim((string) ($datos['color_primario'] ?? '#0d223c'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorPrimario)) {
            $colorPrimario = '#0d223c';
        }

        $colorSecundario = trim((string) ($datos['color_secundario'] ?? '#d89d4a'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorSecundario)) {
            $colorSecundario = '#d89d4a';
        }

        $selloEditorial = trim((string) ($datos['sello_editorial'] ?? ''));
        if ($selloEditorial === '') {
            // Toma las primeras palabras del título en mayúsculas al estilo Figma Make
            $palabras       = preg_split('/\s+/', mb_strtoupper($titulo)) ?: ['LIBRO'];
            $selloEditorial = mb_substr(implode(' ', array_slice($palabras, 0, 2)), 0, 20);
        } else {
            $selloEditorial = mb_substr(mb_strtoupper($selloEditorial), 0, 40);
        }

        $activo    = isset($datos['activo']) ? (int) (bool) $datos['activo'] : 1;
        $destacado = isset($datos['destacado']) ? (int) (bool) $datos['destacado'] : 0;

        return [
            'titulo'           => $titulo,
            'autor'            => $autor,
            'descripcion'      => $descripcion,
            'precio'           => $precio,
            'imagen'           => $imagen,
            'isbn'             => $isbn,
            'stock'            => $stock,
            'categoria_id'     => $categoriaId,
            'activo'           => $activo,
            'valoracion'       => $valoracion,
            'color_primario'   => $colorPrimario,
            'color_secundario' => $colorSecundario,
            'sello_editorial'  => $selloEditorial,
            'destacado'        => $destacado,
        ];
    }
}
