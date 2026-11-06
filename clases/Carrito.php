<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Sesiones.php';
require_once __DIR__ . '/Producto.php';

/**
 * Clase Carrito
 *
 * Gestiona el carrito de compra almacenado en $_SESSION['carrito'] validando
 * siempre la existencia, estado activo, stock y precio real de cada libro
 * directamente contra MySQL mediante PDO.
 */
class Carrito
{
    public const UMBRAL_ENVIO_GRATIS = 35.00;
    public const COSTE_ENVIO_ESTANDAR = 3.95;

    private PDO $db;
    private Producto $productoModel;

    public function __construct(?PDO $db = null)
    {
        $this->db            = $db ?? Database::conectar();
        $this->productoModel = new Producto($this->db);

        if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
            $_SESSION['carrito'] = [];
        }
    }

    /**
     * Devuelve el mapa crudo [producto_id => cantidad] almacenado en sesión.
     *
     * @return array<int, int>
     */
    public function obtenerItemsSesion(): array
    {
        $items = [];
        if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
            return $items;
        }

        foreach ($_SESSION['carrito'] as $id => $cantidad) {
            $idInt   = (int) $id;
            $cantInt = (int) $cantidad;
            if ($idInt > 0 && $cantInt > 0) {
                $items[$idInt] = $cantInt;
            }
        }

        return $items;
    }

    /**
     * Añade un libro al carrito comprobando que exista, esté activo y tenga stock suficiente.
     *
     * @return array<string, mixed> Datos del producto añadido.
     * @throws InvalidArgumentException Si la cantidad es inválida, el producto está inactivo o no hay stock.
     */
    public function agregar(int $productoId, int $cantidad = 1): array
    {
        if ($productoId <= 0) {
            throw new InvalidArgumentException('El identificador del libro no es válido.');
        }

        if ($cantidad < 1) {
            throw new InvalidArgumentException('La cantidad a añadir debe ser al menos 1 unidad.');
        }

        // Consultar únicamente productos activos cuya categoría también esté activa
        $producto = $this->productoModel->obtenerPorId($productoId, true);
        if ($producto === null) {
            throw new InvalidArgumentException('El libro solicitado no existe o no se encuentra disponible en el catálogo.');
        }

        $stockDisponible = (int) $producto['stock'];
        if ($stockDisponible <= 0) {
            throw new InvalidArgumentException('El libro «' . $producto['titulo'] . '» está temporalmente sin stock.');
        }

        $cantidadActual = (int) ($_SESSION['carrito'][$productoId] ?? 0);
        $nuevaCantidad  = $cantidadActual + $cantidad;

        if ($nuevaCantidad > $stockDisponible) {
            throw new InvalidArgumentException(
                'No hay suficiente stock para «' . $producto['titulo'] . '» (disponibles: ' . $stockDisponible . ', en tu carrito: ' . $cantidadActual . ').'
            );
        }

        $_SESSION['carrito'][$productoId] = $nuevaCantidad;

        return $producto;
    }

    /**
     * Modifica la cantidad de un libro en el carrito.
     * Impide cantidades menores que 1 y superiores al stock disponible en MySQL.
     *
     * @return array<string, mixed> Datos del producto actualizado.
     * @throws InvalidArgumentException Si la cantidad es < 1, el producto no está activo o supera el stock.
     */
    public function actualizarCantidad(int $productoId, int $cantidad): array
    {
        if ($productoId <= 0) {
            throw new InvalidArgumentException('Producto no válido.');
        }

        if ($cantidad < 1) {
            throw new InvalidArgumentException('La cantidad mínima por libro es 1 unidad. Si no lo deseas, elimínalo del carrito.');
        }

        $producto = $this->productoModel->obtenerPorId($productoId, true);
        if ($producto === null) {
            unset($_SESSION['carrito'][$productoId]);
            throw new InvalidArgumentException('El libro ya no está disponible en el catálogo y ha sido retirado del carrito.');
        }

        $stockDisponible = (int) $producto['stock'];
        if ($stockDisponible <= 0) {
            unset($_SESSION['carrito'][$productoId]);
            throw new InvalidArgumentException('El libro «' . $producto['titulo'] . '» se ha agotado y ha sido retirado del carrito.');
        }

        if ($cantidad > $stockDisponible) {
            throw new InvalidArgumentException(
                'Solo quedan ' . $stockDisponible . ' unidades disponibles de «' . $producto['titulo'] . '».'
            );
        }

        $_SESSION['carrito'][$productoId] = $cantidad;

        return $producto;
    }

    /**
     * Elimina un libro individualmente del carrito.
     */
    public function eliminar(int $productoId): void
    {
        unset($_SESSION['carrito'][$productoId]);
    }

    /**
     * Vacía por completo el carrito de la sesión.
     */
    public function vaciar(): void
    {
        $_SESSION['carrito'] = [];
    }

    /**
     * Devuelve el número total de ejemplares acumulados en el carrito.
     */
    public function contarArticulos(): int
    {
        $total = 0;
        foreach ($this->obtenerItemsSesion() as $cantidad) {
            $total += $cantidad;
        }
        return $total;
    }

    /**
     * Calcula el estado completo del carrito consultando los precios y el stock
     * vigentes en MySQL. Si algún producto fue desactivado o su stock bajó,
     * sincroniza el carrito automáticamente e informa mediante 'avisos'.
     *
     * @return array{
     *   lineas: array<int, array<string, mixed>>,
     *   subtotal: float,
     *   envio: float,
     *   total: float,
     *   total_articulos: int,
     *   avisos: array<int, string>
     * }
     */
    public function obtenerResumen(): array
    {
        $itemsSesion = $this->obtenerItemsSesion();
        $lineas      = [];
        $avisos      = [];
        $subtotal    = 0.0;
        $articulos   = 0;

        if (empty($itemsSesion)) {
            return [
                'lineas'          => [],
                'subtotal'        => 0.0,
                'envio'           => 0.0,
                'total'           => 0.0,
                'total_articulos' => 0,
                'avisos'          => [],
            ];
        }

        foreach ($itemsSesion as $productoId => $cantidadSolicitada) {
            $producto = $this->productoModel->obtenerPorId($productoId, true);

            if ($producto === null) {
                unset($_SESSION['carrito'][$productoId]);
                $avisos[] = 'Uno de los libros de tu carrito ya no está disponible en el catálogo y se ha retirado.';
                continue;
            }

            $stockReal = (int) $producto['stock'];
            if ($stockReal <= 0) {
                unset($_SESSION['carrito'][$productoId]);
                $avisos[] = '«' . $producto['titulo'] . '» se ha quedado sin stock y se ha retirado del carrito.';
                continue;
            }

            $cantidadEfectiva = $cantidadSolicitada;
            if ($cantidadEfectiva > $stockReal) {
                $cantidadEfectiva                 = $stockReal;
                $_SESSION['carrito'][$productoId] = $cantidadEfectiva;
                $avisos[] = 'La cantidad de «' . $producto['titulo'] . '» se ha ajustado a ' . $stockReal . ' unidades por disponibilidad de stock.';
            }

            $precioUnitario = round((float) $producto['precio'], 2);
            $subtotalLinea  = round($precioUnitario * $cantidadEfectiva, 2);

            $lineas[] = [
                'producto_id'      => (int) $producto['id'],
                'producto'         => $producto,
                'titulo'           => (string) $producto['titulo'],
                'autor'            => (string) $producto['autor'],
                'isbn'             => (string) $producto['isbn'],
                'categoria_nombre' => (string) $producto['categoria_nombre'],
                'precio_unitario'  => $precioUnitario,
                'cantidad'         => $cantidadEfectiva,
                'stock_disponible' => $stockReal,
                'subtotal'         => $subtotalLinea,
            ];

            $subtotal  = round($subtotal + $subtotalLinea, 2);
            $articulos += $cantidadEfectiva;
        }

        $envio = 0.0;
        if ($subtotal > 0.0 && $subtotal < self::UMBRAL_ENVIO_GRATIS) {
            $envio = self::COSTE_ENVIO_ESTANDAR;
        }

        $total = round($subtotal + $envio, 2);

        return [
            'lineas'          => $lineas,
            'subtotal'        => $subtotal,
            'envio'           => $envio,
            'total'           => $total,
            'total_articulos' => $articulos,
            'avisos'          => $avisos,
        ];
    }
}
