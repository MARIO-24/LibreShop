<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Carrito.php';

/**
 * Clase Pedido
 *
 * Gestiona el ciclo completo de los pedidos en MySQL:
 * - Creación transaccional con bloqueo pesimista (SELECT ... FOR UPDATE) y descuento de stock.
 * - Consulta segura verificando la propiedad del pedido por usuario.
 * - Historial de compras del cliente.
 * - Administración de pedidos, filtrado y máquina de estados controlada.
 */
class Pedido
{
    public const ESTADOS_VALIDOS = ['Pendiente', 'Enviado', 'Entregado', 'Cancelado'];

    /**
     * Transiciones de estado permitidas en administración.
     * Todos los pedidos pueden cambiar a cualquiera de los cuatro estados disponibles
     * sin estados terminales que bloqueen futuras modificaciones.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSICIONES_PERMITIDAS = [
        'Pendiente' => self::ESTADOS_VALIDOS,
        'Enviado'   => self::ESTADOS_VALIDOS,
        'Entregado' => self::ESTADOS_VALIDOS,
        'Cancelado' => self::ESTADOS_VALIDOS,
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::conectar();
    }

    /**
     * Formatea el código visual del pedido siguiendo el diseño de Figma Make (ej. "#LS-2026-0001").
     */
    public static function formatearCodigo(int $pedidoId, ?string $fechaPedido = null): string
    {
        $anio = '2026';
        if ($fechaPedido !== null && $fechaPedido !== '') {
            $ts = strtotime($fechaPedido);
            if ($ts !== false) {
                $anio = date('Y', $ts);
            }
        }

        return sprintf('#LS-%s-%04d', $anio, $pedidoId);
    }

    /**
     * Devuelve los estados disponibles para un pedido.
     * Cualquier estado puede pasar a cualquier otro estado válido.
     *
     * @return array<int, string>
     */
    public static function obtenerTransicionesPermitidas(string $estadoActual): array
    {
        return self::TRANSICIONES_PERMITIDAS[$estadoActual] ?? self::ESTADOS_VALIDOS;
    }

    /**
     * Valida los campos de envío y método de pago recibidos en el checkout.
     * Nunca almacena numeraciones de tarjetas bancarias ni códigos CVV.
     *
     * @param array<string, mixed> $datosEnvio
     * @return array{direccion_completa: string, metodo_pago: string, direccion: string, codigo_postal: string, ciudad: string}
     * @throws InvalidArgumentException Si algún campo es inválido.
     */
    public function validarDatosCheckout(array $datosEnvio): array
    {
        $direccion    = trim((string) ($datosEnvio['direccion'] ?? ''));
        $codigoPostal = trim((string) ($datosEnvio['codigo_postal'] ?? ''));
        $ciudad       = trim((string) ($datosEnvio['ciudad'] ?? ''));
        $metodoPago   = trim((string) ($datosEnvio['metodo_pago'] ?? 'Tarjeta'));

        if ($direccion === '' || mb_strlen($direccion) < 5 || mb_strlen($direccion) > 160) {
            throw new InvalidArgumentException('Introduce una dirección de envío válida (entre 5 y 160 caracteres).');
        }

        if ($codigoPostal === '' || !preg_match('/^[0-9A-Za-z\-\s]{4,10}$/', $codigoPostal)) {
            throw new InvalidArgumentException('Introduce un código postal válido (ej. 28001).');
        }

        if ($ciudad === '' || mb_strlen($ciudad) < 2 || mb_strlen($ciudad) > 80) {
            throw new InvalidArgumentException('Introduce una ciudad válida.');
        }

        $metodosPermitidos = [
            'card'    => 'Tarjeta',
            'paypal'  => 'PayPal',
            'Tarjeta' => 'Tarjeta',
            'PayPal'  => 'PayPal',
        ];

        if (!isset($metodosPermitidos[$metodoPago])) {
            throw new InvalidArgumentException('Selecciona un método de pago válido (Tarjeta o PayPal).');
        }

        $metodoNormalizado = $metodosPermitidos[$metodoPago];
        $direccionCompleta = sprintf('%s, %s %s', $direccion, $codigoPostal, $ciudad);

        return [
            'direccion_completa' => mb_substr($direccionCompleta, 0, 255),
            'metodo_pago'        => $metodoNormalizado,
            'direccion'          => $direccion,
            'codigo_postal'      => $codigoPostal,
            'ciudad'             => $ciudad,
        ];
    }

    /**
     * Crea un pedido completo en MySQL dentro de una transacción ACID segura:
     * 1. Inicia transacción.
     * 2. Bloquea las filas de productos implicados con SELECT ... FOR UPDATE.
     * 3. Comprueba que cada producto existe, está activo y tiene stock suficiente.
     * 4. Obtiene los precios vigentes desde MySQL y calcula el total en el servidor.
     * 5. Inserta la cabecera en `pedidos`.
     * 6. Inserta cada línea en `detalle_pedido` con el precio unitario del momento de compra.
     * 7. Descuenta el stock de `productos`.
     * 8. Confirma la transacción (COMMIT) o revierte todo (ROLLBACK) ante cualquier fallo.
     *
     * @param int                  $usuarioId    ID del usuario autenticado.
     * @param array<int, int>      $itemsCarrito Mapa [producto_id => cantidad].
     * @param array<string, mixed> $datosEnvio   Datos del formulario de checkout.
     * @return int ID del pedido creado.
     * @throws InvalidArgumentException|RuntimeException Si falla la validación o el stock.
     */
    public function crearDesdeCarrito(int $usuarioId, array $itemsCarrito, array $datosEnvio): int
    {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('Debes iniciar sesión para realizar un pedido.');
        }

        // Filtrar y normalizar cantidades del carrito
        $itemsLimpios = [];
        foreach ($itemsCarrito as $prodId => $cant) {
            $idInt   = (int) $prodId;
            $cantInt = (int) $cant;
            if ($idInt <= 0 || $cantInt < 1) {
                throw new InvalidArgumentException('El carrito contiene cantidades no válidas.');
            }
            $itemsLimpios[$idInt] = $cantInt;
        }

        if (empty($itemsLimpios)) {
            throw new InvalidArgumentException('Tu carrito está vacío. Añade al menos un libro antes de confirmar el pedido.');
        }

        $envioValidado = $this->validarDatosCheckout($datosEnvio);

        // Ordenar por ID de producto ascendente para evitar interbloqueos (deadlocks) al bloquear filas
        ksort($itemsLimpios);

        $this->db->beginTransaction();

        try {
            $stmtUser = $this->db->prepare('SELECT id FROM usuarios WHERE id = :id LIMIT 1');
            $stmtUser->execute([':id' => $usuarioId]);
            if ($stmtUser->fetch() === false) {
                throw new InvalidArgumentException('La cuenta de usuario de la sesión no es válida. Vuelve a iniciar sesión.');
            }

            $stmtBloqueo = $this->db->prepare(
                'SELECT p.id, p.titulo, p.precio, p.stock, p.activo,
                        c.activa AS categoria_activa
                 FROM productos p
                 INNER JOIN categorias c ON c.id = p.categoria_id
                 WHERE p.id = :id
                 FOR UPDATE'
            );

            $lineasParaInsertar = [];
            $subtotalPedido     = 0.0;

            foreach ($itemsLimpios as $productoId => $cantidad) {
                $stmtBloqueo->execute([':id' => $productoId]);
                $producto = $stmtBloqueo->fetch();

                if ($producto === false || (int) $producto['activo'] !== 1 || (int) $producto['categoria_activa'] !== 1) {
                    throw new RuntimeException(
                        'Uno de los libros seleccionados ya no se encuentra activo en el catálogo.'
                    );
                }

                $stockActual = (int) $producto['stock'];
                if ($stockActual < $cantidad) {
                    throw new RuntimeException(
                        sprintf(
                            'No hay stock suficiente para «%s» (solicitados: %d, disponibles: %d).',
                            (string) $producto['titulo'],
                            $cantidad,
                            $stockActual
                        )
                    );
                }

                $precioUnitario = round((float) $producto['precio'], 2);
                $subtotalLinea  = round($precioUnitario * $cantidad, 2);

                $lineasParaInsertar[] = [
                    'producto_id'     => (int) $producto['id'],
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal'        => $subtotalLinea,
                ];

                $subtotalPedido = round($subtotalPedido + $subtotalLinea, 2);
            }

            $gastosEnvio = ($subtotalPedido > 0.0 && $subtotalPedido < Carrito::UMBRAL_ENVIO_GRATIS)
                ? Carrito::COSTE_ENVIO_ESTANDAR
                : 0.0;
            $totalPedido = round($subtotalPedido + $gastosEnvio, 2);

            // Insertar cabecera en `pedidos`
            $stmtPedido = $this->db->prepare(
                'INSERT INTO pedidos (usuario_id, total, estado, direccion_envio, metodo_pago)
                 VALUES (:usuario_id, :total, :estado, :direccion_envio, :metodo_pago)'
            );
            $stmtPedido->execute([
                ':usuario_id'      => $usuarioId,
                ':total'           => $totalPedido,
                ':estado'          => 'Pendiente',
                ':direccion_envio' => $envioValidado['direccion_completa'],
                ':metodo_pago'     => $envioValidado['metodo_pago'],
            ]);

            $pedidoId = (int) $this->db->lastInsertId();

            // Insertar líneas en `detalle_pedido` y descontar stock en `productos`
            $stmtDetalle = $this->db->prepare(
                'INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unitario, subtotal)
                 VALUES (:pedido_id, :producto_id, :cantidad, :precio_unitario, :subtotal)'
            );

            $stmtDescontarStock = $this->db->prepare(
                'UPDATE productos
                 SET stock = stock - :cantidad
                 WHERE id = :id AND stock >= :cantidad_check'
            );

            foreach ($lineasParaInsertar as $linea) {
                $stmtDetalle->execute([
                    ':pedido_id'       => $pedidoId,
                    ':producto_id'     => $linea['producto_id'],
                    ':cantidad'        => $linea['cantidad'],
                    ':precio_unitario' => $linea['precio_unitario'],
                    ':subtotal'        => $linea['subtotal'],
                ]);

                $stmtDescontarStock->execute([
                    ':cantidad'       => $linea['cantidad'],
                    ':id'             => $linea['producto_id'],
                    ':cantidad_check' => $linea['cantidad'],
                ]);

                if ($stmtDescontarStock->rowCount() !== 1) {
                    throw new RuntimeException('No se pudo actualizar el stock del producto #' . $linea['producto_id'] . '.');
                }
            }

            $this->db->commit();

            return $pedidoId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Obtiene un pedido completo con sus líneas verificando estrictamente la propiedad.
     * Si $esAdmin es false, la consulta SQL exige que `p.usuario_id = :usuario_id`.
     *
     * @return array<string, mixed>|null
     */
    public function obtenerPorIdYUsuario(int $pedidoId, int $usuarioId, bool $esAdmin = false): ?array
    {
        if ($pedidoId <= 0) {
            return null;
        }

        $sql = 'SELECT p.id, p.usuario_id, p.fecha_pedido, p.total, p.estado,
                       p.direccion_envio, p.metodo_pago,
                       u.nombre AS usuario_nombre, u.apellidos AS usuario_apellidos, u.email AS usuario_email
                FROM pedidos p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                WHERE p.id = :id';

        $params = [':id' => $pedidoId];

        if (!$esAdmin) {
            $sql                  .= ' AND p.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $pedido = $stmt->fetch();

        if ($pedido === false) {
            return null;
        }

        $pedido['codigo'] = self::formatearCodigo((int) $pedido['id'], (string) $pedido['fecha_pedido']);
        $pedido['lineas'] = $this->obtenerLineasPedido((int) $pedido['id']);

        $subtotalLineas = 0.0;
        foreach ($pedido['lineas'] as $l) {
            $subtotalLineas = round($subtotalLineas + (float) $l['subtotal'], 2);
        }
        $pedido['subtotal_libros'] = $subtotalLineas;
        $pedido['gastos_envio']    = max(0.0, round((float) $pedido['total'] - $subtotalLineas, 2));

        return $pedido;
    }

    /**
     * Obtiene las líneas de detalle de un pedido junto con los datos de portada del libro.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerLineasPedido(int $pedidoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT dp.id, dp.pedido_id, dp.producto_id, dp.cantidad,
                    dp.precio_unitario, dp.subtotal,
                    pr.titulo, pr.autor, pr.isbn, pr.imagen,
                    pr.color_primario, pr.color_secundario, pr.sello_editorial
             FROM detalle_pedido dp
             INNER JOIN productos pr ON pr.id = dp.producto_id
             WHERE dp.pedido_id = :pedido_id
             ORDER BY dp.id ASC'
        );
        $stmt->execute([':pedido_id' => $pedidoId]);

        return $stmt->fetchAll();
    }

    /**
     * Devuelve el historial de pedidos perteneciente exclusivamente al usuario indicado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerPorUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.usuario_id, p.fecha_pedido, p.total, p.estado,
                    p.direccion_envio, p.metodo_pago,
                    COALESCE(SUM(dp.cantidad), 0) AS total_unidades
             FROM pedidos p
             LEFT JOIN detalle_pedido dp ON dp.pedido_id = p.id
             WHERE p.usuario_id = :usuario_id
             GROUP BY p.id, p.usuario_id, p.fecha_pedido, p.total, p.estado, p.direccion_envio, p.metodo_pago
             ORDER BY p.fecha_pedido DESC, p.id DESC'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);
        $pedidos = $stmt->fetchAll();

        foreach ($pedidos as &$p) {
            $p['codigo'] = self::formatearCodigo((int) $p['id'], (string) $p['fecha_pedido']);
        }
        unset($p);

        return $pedidos;
    }

    /**
     * Lista todos los pedidos del sistema para el panel de administración,
     * permitiendo buscar por número/código de pedido o cliente, y filtrar por estado.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    public function obtenerTodosAdmin(array $filtros = []): array
    {
        $condiciones = [];
        $params      = [];

        $estado = trim((string) ($filtros['estado'] ?? 'todos'));
        if ($estado !== '' && $estado !== 'todos' && in_array($estado, self::ESTADOS_VALIDOS, true)) {
            $condiciones[]     = 'p.estado = :estado';
            $params[':estado'] = $estado;
        }

        $busqueda = trim((string) ($filtros['q'] ?? ''));
        if ($busqueda !== '') {
            // Si busca estilo "#LS-2026-0004" o "0004", extraemos también el número entero si existe
            $idExtraido = null;
            if (preg_match('/(\d+)\s*$/', $busqueda, $coincidencias)) {
                $idExtraido = (int) $coincidencias[1];
            }

            if ($idExtraido !== null && $idExtraido > 0) {
                $condiciones[]         = '(p.id = :id_exacto OR u.nombre LIKE :q1 OR u.apellidos LIKE :q2 OR u.email LIKE :q3)';
                $params[':id_exacto']  = $idExtraido;
            } else {
                $condiciones[] = '(u.nombre LIKE :q1 OR u.apellidos LIKE :q2 OR u.email LIKE :q3)';
            }

            $like          = '%' . $busqueda . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        $sql = 'SELECT p.id, p.usuario_id, p.fecha_pedido, p.total, p.estado,
                       p.direccion_envio, p.metodo_pago,
                       u.nombre AS usuario_nombre, u.apellidos AS usuario_apellidos, u.email AS usuario_email,
                       COALESCE(SUM(dp.cantidad), 0) AS total_unidades
                FROM pedidos p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                LEFT JOIN detalle_pedido dp ON dp.pedido_id = p.id';

        if (!empty($condiciones)) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        $sql .= ' GROUP BY p.id, p.usuario_id, p.fecha_pedido, p.total, p.estado,
                           p.direccion_envio, p.metodo_pago,
                           u.nombre, u.apellidos, u.email
                  ORDER BY p.fecha_pedido DESC, p.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll();

        foreach ($pedidos as &$p) {
            $p['codigo'] = self::formatearCodigo((int) $p['id'], (string) $p['fecha_pedido']);
        }
        unset($p);

        return $pedidos;
    }

    /**
     * Actualiza el estado de un pedido permitiendo transiciones libres entre cualquiera
     * de los cuatro estados válidos y controlando el stock de forma transaccional:
     * - Al pasar de un estado activo (Pendiente, Enviado, Entregado) a 'Cancelado':
     *   restituye el stock del pedido una única vez.
     * - Al salir de 'Cancelado' hacia otro estado (Pendiente, Enviado, Entregado):
     *   verifica que exista stock suficiente y vuelve a descontar las unidades.
     * - Entre estados activos o si se mantiene el mismo estado:
     *   no modifica el stock para evitar devoluciones o descuentos duplicados.
     *
     * @throws InvalidArgumentException|RuntimeException Si el pedido no existe, el estado es inválido,
     *                                                   el usuario no es administrador o no hay stock suficiente.
     */
    public function actualizarEstado(int $pedidoId, string $nuevoEstado): bool
    {
        if (
            function_exists('estaAutenticado')
            && ((estaAutenticado() && !esAdministrador()) || (PHP_SAPI !== 'cli' && !esAdministrador()))
        ) {
            throw new RuntimeException('Acceso restringido: solo un administrador puede modificar el estado de los pedidos.');
        }

        if ($pedidoId <= 0) {
            throw new InvalidArgumentException('El pedido indicado no existe.');
        }

        $nuevoEstado = trim($nuevoEstado);
        if (!in_array($nuevoEstado, self::ESTADOS_VALIDOS, true)) {
            throw new InvalidArgumentException('El estado de pedido indicado no es válido.');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('SELECT id, estado FROM pedidos WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $pedidoId]);
            $pedido = $stmt->fetch();

            if ($pedido === false) {
                throw new InvalidArgumentException('El pedido indicado no existe.');
            }

            $estadoAnterior = (string) $pedido['estado'];
            if ($estadoAnterior === $nuevoEstado) {
                $this->db->commit();
                return true;
            }

            $stmtLineas = $this->db->prepare(
                'SELECT producto_id, SUM(cantidad) AS cantidad_total
                 FROM detalle_pedido
                 WHERE pedido_id = :pedido_id
                 GROUP BY producto_id
                 ORDER BY producto_id ASC'
            );
            $stmtLineas->execute([':pedido_id' => $pedidoId]);
            $lineas = $stmtLineas->fetchAll();

            // 1. Si pasa de un estado activo a Cancelado: restituir stock una sola vez
            if ($estadoAnterior !== 'Cancelado' && $nuevoEstado === 'Cancelado') {
                $stmtLockProd = $this->db->prepare('SELECT id FROM productos WHERE id = :id FOR UPDATE');
                $stmtSumar    = $this->db->prepare('UPDATE productos SET stock = stock + :cantidad WHERE id = :id');

                foreach ($lineas as $linea) {
                    $prodId   = (int) $linea['producto_id'];
                    $cantidad = (int) $linea['cantidad_total'];

                    $stmtLockProd->execute([':id' => $prodId]);
                    $stmtSumar->execute([
                        ':cantidad' => $cantidad,
                        ':id'       => $prodId,
                    ]);
                }
            }
            // 2. Si sale de Cancelado hacia otro estado activo: verificar stock suficiente y descontar
            elseif ($estadoAnterior === 'Cancelado' && $nuevoEstado !== 'Cancelado') {
                $stmtLockProd  = $this->db->prepare('SELECT id, titulo, stock FROM productos WHERE id = :id FOR UPDATE');
                $stmtDescontar = $this->db->prepare(
                    'UPDATE productos
                     SET stock = stock - :cantidad
                     WHERE id = :id AND stock >= :cantidad_check'
                );

                foreach ($lineas as $linea) {
                    $prodId   = (int) $linea['producto_id'];
                    $cantidad = (int) $linea['cantidad_total'];

                    $stmtLockProd->execute([':id' => $prodId]);
                    $producto = $stmtLockProd->fetch();

                    if ($producto === false) {
                        throw new RuntimeException('Uno de los productos del pedido ya no existe en el catálogo.');
                    }

                    $stockDisponible = (int) $producto['stock'];
                    if ($stockDisponible < $cantidad) {
                        throw new RuntimeException(
                            sprintf(
                                'No hay stock suficiente para reactivar el pedido: «%s» requiere %d %s y actualmente hay %d en stock.',
                                (string) $producto['titulo'],
                                $cantidad,
                                $cantidad === 1 ? 'unidad' : 'unidades',
                                $stockDisponible
                            )
                        );
                    }

                    $stmtDescontar->execute([
                        ':cantidad'       => $cantidad,
                        ':id'             => $prodId,
                        ':cantidad_check' => $cantidad,
                    ]);

                    if ($stmtDescontar->rowCount() !== 1) {
                        throw new RuntimeException(
                            'No se pudo descontar el stock del producto «' . (string) $producto['titulo'] . '».'
                        );
                    }
                }
            }

            $stmtUpd = $this->db->prepare('UPDATE pedidos SET estado = :estado WHERE id = :id');
            $stmtUpd->execute([
                ':estado' => $nuevoEstado,
                ':id'     => $pedidoId,
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
