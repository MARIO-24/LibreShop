<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Clase Categoria
 *
 * Gestiona todas las operaciones CRUD de las categorías de libros en MySQL
 * empleando programación orientada a objetos y consultas preparadas PDO.
 */
class Categoria
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::conectar();
    }

    /**
     * Obtiene todas las categorías junto con el número de productos asociados.
     *
     * @param bool $soloActivas Si es true, devuelve únicamente las categorías activas.
     * @return array<int, array<string, mixed>>
     */
    public function obtenerTodas(bool $soloActivas = true): array
    {
        $sql = 'SELECT c.id, c.nombre, c.descripcion, c.color_fondo, c.activa,
                       COUNT(p.id) AS total_productos
                FROM categorias c
                LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1';

        if ($soloActivas) {
            $sql .= ' WHERE c.activa = 1';
        }

        $sql .= ' GROUP BY c.id, c.nombre, c.descripcion, c.color_fondo, c.activa
                  ORDER BY c.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Obtiene una categoría por su identificador primario.
     *
     * @param int $id ID de la categoría.
     * @return array<string, mixed>|null
     */
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, descripcion, color_fondo, activa
             FROM categorias
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila !== false ? $fila : null;
    }

    /**
     * Obtiene una categoría por su nombre exacto.
     *
     * @param string $nombre Nombre de la categoría.
     * @return array<string, mixed>|null
     */
    public function obtenerPorNombre(string $nombre): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, descripcion, color_fondo, activa
             FROM categorias
             WHERE nombre = :nombre
             LIMIT 1'
        );
        $stmt->execute([':nombre' => trim($nombre)]);
        $fila = $stmt->fetch();

        return $fila !== false ? $fila : null;
    }

    /**
     * Crea una nueva categoría validando los datos de entrada.
     *
     * @param array<string, mixed> $datos
     * @return int ID de la categoría creada.
     * @throws InvalidArgumentException Si los datos no superan la validación.
     */
    public function crear(array $datos): int
    {
        $nombre      = trim((string) ($datos['nombre'] ?? ''));
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $colorFondo  = trim((string) ($datos['color_fondo'] ?? '#e9f1ff'));
        $activa      = isset($datos['activa']) ? (int) (bool) $datos['activa'] : 1;

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new InvalidArgumentException('El nombre de la categoría es obligatorio y no debe superar los 100 caracteres.');
        }

        if ($descripcion === '') {
            $descripcion = 'Colección temática de LibreShop';
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorFondo)) {
            $colorFondo = '#e9f1ff';
        }

        if ($this->obtenerPorNombre($nombre) !== null) {
            throw new InvalidArgumentException('Ya existe una categoría con ese nombre.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO categorias (nombre, descripcion, color_fondo, activa)
             VALUES (:nombre, :descripcion, :color_fondo, :activa)'
        );
        $stmt->execute([
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion,
            ':color_fondo' => $colorFondo,
            ':activa'      => $activa,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza los datos de una categoría existente.
     *
     * @param int                  $id    ID de la categoría.
     * @param array<string, mixed> $datos Nuevos datos.
     * @return bool
     */
    public function actualizar(int $id, array $datos): bool
    {
        $actual = $this->obtenerPorId($id);
        if ($actual === null) {
            throw new InvalidArgumentException('La categoría indicada no existe.');
        }

        $nombre      = trim((string) ($datos['nombre'] ?? $actual['nombre']));
        $descripcion = trim((string) ($datos['descripcion'] ?? $actual['descripcion']));
        $colorFondo  = trim((string) ($datos['color_fondo'] ?? $actual['color_fondo']));
        $activa      = isset($datos['activa']) ? (int) (bool) $datos['activa'] : (int) $actual['activa'];

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new InvalidArgumentException('El nombre de la categoría es obligatorio.');
        }

        $existente = $this->obtenerPorNombre($nombre);
        if ($existente !== null && (int) $existente['id'] !== $id) {
            throw new InvalidArgumentException('Otra categoría ya utiliza ese nombre.');
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorFondo)) {
            $colorFondo = '#e9f1ff';
        }

        $stmt = $this->db->prepare(
            'UPDATE categorias
             SET nombre = :nombre,
                 descripcion = :descripcion,
                 color_fondo = :color_fondo,
                 activa = :activa
             WHERE id = :id'
        );

        return $stmt->execute([
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion,
            ':color_fondo' => $colorFondo,
            ':activa'      => $activa,
            ':id'          => $id,
        ]);
    }

    /**
     * Activa o desactiva una categoría.
     */
    public function cambiarEstado(int $id, bool $activa): bool
    {
        $stmt = $this->db->prepare('UPDATE categorias SET activa = :activa WHERE id = :id');
        return $stmt->execute([
            ':activa' => $activa ? 1 : 0,
            ':id'     => $id,
        ]);
    }

    /**
     * Elimina una categoría si no tiene productos asociados; si los tiene, la desactiva.
     *
     * @return array{eliminada: bool, desactivada: bool, mensaje: string}
     */
    public function eliminar(int $id): array
    {
        $stmtCont = $this->db->prepare('SELECT COUNT(*) FROM productos WHERE categoria_id = :id');
        $stmtCont->execute([':id' => $id]);
        $totalProductos = (int) $stmtCont->fetchColumn();

        if ($totalProductos > 0) {
            $this->cambiarEstado($id, false);
            return [
                'eliminada'   => false,
                'desactivada' => true,
                'mensaje'     => 'La categoría tiene ' . $totalProductos . ' libro(s) asociado(s), por lo que se ha desactivado en lugar de borrarse.',
            ];
        }

        $stmt = $this->db->prepare('DELETE FROM categorias WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return [
            'eliminada'   => true,
            'desactivada' => false,
            'mensaje'     => 'Categoría eliminada correctamente.',
        ];
    }
}
