-- ============================================================================
-- PROYECTO LIBRESHOP — SCRIPT DE BASE DE DATOS MYSQL
-- Asignatura: Desarrollo Web en Entorno Servidor (DAW)
-- Motor: InnoDB | Charset: utf8mb4_unicode_ci
-- Nota: Este script es idempotente; puede ejecutarse múltiples veces sin
--       duplicar registros gracias a ON DUPLICATE KEY UPDATE.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `libreshop`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `libreshop`;

-- ============================================================================
-- 1. TABLA: usuarios
-- Almacena tanto clientes como administradores de la tienda.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(80) NOT NULL,
  `apellidos` VARCHAR(120) NOT NULL DEFAULT '',
  `email` VARCHAR(160) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Hash generado con password_hash()',
  `rol` ENUM('cliente', 'administrador') NOT NULL DEFAULT 'cliente',
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_email` (`email`),
  KEY `idx_usuarios_rol` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABLA: categorias
-- Clasificación temática del catálogo de libros.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` VARCHAR(255) NOT NULL,
  `color_fondo` VARCHAR(20) NOT NULL DEFAULT '#e9f1ff' COMMENT 'Color de tarjeta en portada (diseño Figma)',
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categorias_nombre` (`nombre`),
  KEY `idx_categorias_activa` (`activa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. TABLA: productos
-- Catálogo de libros con soporte para portada editorial (colores + sello)
-- e imagen personalizada subida desde el gestor de productos.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `productos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(180) NOT NULL,
  `autor` VARCHAR(140) NOT NULL,
  `descripcion` TEXT NOT NULL,
  `precio` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `imagen` VARCHAR(255) DEFAULT NULL COMMENT 'Ruta relativa en assets/img/portadas/ o URL externa',
  `isbn` VARCHAR(30) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `categoria_id` INT UNSIGNED NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `valoracion` DECIMAL(2, 1) NOT NULL DEFAULT 4.8,
  `color_primario` VARCHAR(20) NOT NULL DEFAULT '#0d223c',
  `color_secundario` VARCHAR(20) NOT NULL DEFAULT '#d89d4a',
  `sello_editorial` VARCHAR(40) NOT NULL DEFAULT 'LIBRESHOP',
  `destacado` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_productos_isbn` (`isbn`),
  KEY `idx_productos_categoria` (`categoria_id`),
  KEY `idx_productos_activo` (`activo`),
  KEY `idx_productos_destacado` (`destacado`),
  KEY `idx_productos_busqueda` (`titulo`, `autor`),
  CONSTRAINT `fk_productos_categoria`
    FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TABLA: pedidos
-- Cabecera de los pedidos realizados por usuarios autenticados.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `fecha_pedido` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `estado` ENUM('Pendiente', 'Enviado', 'Entregado', 'Cancelado') NOT NULL DEFAULT 'Pendiente',
  `direccion_envio` VARCHAR(255) NOT NULL,
  `metodo_pago` VARCHAR(50) NOT NULL DEFAULT 'Tarjeta',
  PRIMARY KEY (`id`),
  KEY `idx_pedidos_usuario` (`usuario_id`),
  KEY `idx_pedidos_estado` (`estado`),
  KEY `idx_pedidos_fecha` (`fecha_pedido`),
  CONSTRAINT `fk_pedidos_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABLA: detalle_pedido
-- Líneas de cada pedido con precio unitario histórico y subtotal.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `detalle_pedido` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_id` INT UNSIGNED NOT NULL,
  `producto_id` INT UNSIGNED NOT NULL,
  `cantidad` INT UNSIGNED NOT NULL DEFAULT 1,
  `precio_unitario` DECIMAL(10, 2) NOT NULL,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_detalle_pedido` (`pedido_id`),
  KEY `idx_detalle_producto` (`producto_id`),
  CONSTRAINT `fk_detalle_pedido`
    FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `fk_detalle_producto`
    FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATOS INICIALES: CATEGORÍAS (Sin duplicados si se re-ejecuta)
-- ============================================================================
INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `color_fondo`, `activa`) VALUES
  (1, 'Ficción', 'Historias para perderte y encontrarte', '#e9f1ff', 1),
  (2, 'No ficción', 'Ideas para entender mejor el mundo', '#edf5f1', 1),
  (3, 'Infantil', 'Aventuras para pequeños lectores', '#fff4e6', 1)
ON DUPLICATE KEY UPDATE
  `descripcion` = VALUES(`descripcion`),
  `color_fondo` = VALUES(`color_fondo`),
  `activa` = VALUES(`activa`);

-- ============================================================================
-- DATOS INICIALES: PRODUCTOS (Catálogo de libros LibreShop)
-- Incluye los libros requeridos en la especificación académica y la selección
-- visual del diseño original de Figma Make.
-- ============================================================================
INSERT INTO `productos` (
  `id`, `titulo`, `autor`, `descripcion`, `precio`, `imagen`, `isbn`,
  `stock`, `categoria_id`, `activo`, `valoracion`,
  `color_primario`, `color_secundario`, `sello_editorial`, `destacado`
) VALUES
  (
    1,
    'La sombra del viento',
    'Carlos Ruiz Zafón',
    'Un amanecer de 1945, un muchacho es conducido por su padre a un misterioso lugar oculto en el corazón de la ciudad vieja: el Cementerio de los Libros Olvidados.',
    19.90,
    NULL,
    '978-84-08-16338-1',
    12,
    1,
    1,
    4.9,
    '#0d223c',
    '#d89d4a',
    'LAS SOMBRAS',
    1
  ),
  (
    2,
    'Hábitos atómicos',
    'James Clear',
    'Cambios pequeños, resultados extraordinarios. Un método práctico y demostrado para desarrollar buenos hábitos, eliminar los malos y tomar mejores decisiones cada día.',
    18.90,
    NULL,
    '978-84-18118-03-6',
    15,
    2,
    1,
    4.8,
    '#eff0e8',
    '#e14e3c',
    'HÁBITOS',
    1
  ),
  (
    3,
    'El principito',
    'Antoine de Saint-Exupéry',
    'Una fábula poética e inolvidable sobre la amistad, el amor y el sentido de la vida vista a través de los ojos de un pequeño príncipe procedente de otro asteroide.',
    14.95,
    NULL,
    '978-84-9838-149-8',
    20,
    3,
    1,
    4.9,
    '#80549a',
    '#f1c65a',
    'PRINCIPITO',
    1
  ),
  (
    4,
    'Dune',
    'Frank Herbert',
    'En el desértico planeta Arrakis, única fuente de la especia melange, el joven Paul Atreides deberá enfrentarse a la traición imperial y cumplir un destino mesiánico.',
    23.50,
    NULL,
    '978-84-663-5377-9',
    9,
    1,
    1,
    4.8,
    '#dc6b51',
    '#f5d39d',
    'DUNE',
    1
  ),
  (
    5,
    '1984',
    'George Orwell',
    'En una Oceanía totalitaria vigilada por el Gran Hermano y la Policía del Pensamiento, Winston Smith intenta preservar su libertad interior y la verdad histórica.',
    16.50,
    NULL,
    '978-84-9989-094-4',
    14,
    1,
    1,
    4.7,
    '#191c35',
    '#34b5b1',
    '1984',
    0
  ),
  (
    6,
    'Sapiens: De animales a dioses',
    'Yuval Noah Harari',
    'Un recorrido fascinante por la historia de la humanidad: desde los primeros humanos que caminaron sobre la Tierra hasta las revoluciones cognitiva, agrícola y científica.',
    24.90,
    NULL,
    '978-84-9992-622-3',
    11,
    2,
    1,
    4.9,
    '#e8e1d5',
    '#214f60',
    'SAPIENS',
    0
  ),
  (
    7,
    'Matilda',
    'Roald Dahl',
    'Matilda es una lectora prodigiosa de apenas cinco años con poderes extraordinarios que hará frente a la temible directora Trunchbull con la ayuda de la señorita Honey.',
    15.50,
    NULL,
    '978-84-204-8289-7',
    18,
    3,
    1,
    4.8,
    '#17788a',
    '#f7b345',
    'MATILDA',
    0
  ),
  (
    8,
    'El Hobbit',
    'J. R. R. Tolkien',
    'Bilbo Bolsón, un hobbit amante de la tranquilidad de la Comarca, se embarca junto al mago Gandalf y trece enanos en una aventura épica hacia la Montaña Solitaria.',
    21.00,
    NULL,
    '978-84-450-7380-3',
    10,
    1,
    1,
    4.9,
    '#d9e2e0',
    '#31596c',
    'EL HOBBIT',
    0
  ),
  (
    9,
    'La ciudad de las sombras',
    'Clara Montes',
    'Una cartógrafa encuentra un mapa imposible que conduce a los secretos enterrados bajo una ciudad que nunca duerme.',
    19.90,
    NULL,
    '978-84-9129-401-1',
    12,
    1,
    1,
    4.8,
    '#0d223c',
    '#d89d4a',
    'LAS SOMBRAS',
    0
  ),
  (
    10,
    'Atlas de lo invisible',
    'Nora Vidal',
    'Un viaje fascinante por las fuerzas, sistemas y conexiones que moldean nuestra vida sin que podamos verlas.',
    24.90,
    NULL,
    '978-84-9129-403-5',
    15,
    2,
    1,
    4.9,
    '#e8e1d5',
    '#214f60',
    'ATLAS',
    0
  ),
  (
    11,
    'El jardín de Luna',
    'Elena Prado',
    'Luna descubre que las plantas de su abuela hablan al caer la noche y necesitan su ayuda antes del amanecer.',
    14.95,
    NULL,
    '978-84-9129-405-9',
    20,
    3,
    1,
    4.9,
    '#80549a',
    '#f1c65a',
    'LUNA',
    0
  ),
  (
    12,
    'Pensar despacio',
    'Daniel Robles',
    'Herramientas claras para tomar mejores decisiones, reducir el ruido mental y construir una atención más consciente.',
    21.00,
    NULL,
    '978-84-9129-404-2',
    5,
    2,
    1,
    4.7,
    '#eff0e8',
    '#e14e3c',
    'PENSAR',
    0
  )
ON DUPLICATE KEY UPDATE
  `titulo` = VALUES(`titulo`),
  `autor` = VALUES(`autor`),
  `descripcion` = VALUES(`descripcion`),
  `precio` = VALUES(`precio`),
  `stock` = VALUES(`stock`),
  `categoria_id` = VALUES(`categoria_id`),
  `activo` = VALUES(`activo`),
  `valoracion` = VALUES(`valoracion`),
  `color_primario` = VALUES(`color_primario`),
  `color_secundario` = VALUES(`color_secundario`),
  `sello_editorial` = VALUES(`sello_editorial`),
  `destacado` = VALUES(`destacado`);

-- ============================================================================
-- DATOS INICIALES: USUARIOS DE PRUEBA
-- (Preparados en esquema para integridad referencial; la autenticación se
--  activará en la FASE 2).
--  Admin:   admin@libreshop.es   / Admin1234!
--  Cliente: marina@libreshop.es  / Cliente1234!
-- ============================================================================
INSERT INTO `usuarios` (`id`, `nombre`, `apellidos`, `email`, `password`, `rol`) VALUES
  (1, 'Administrador', 'LibreShop', 'admin@libreshop.es', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador'),
  (2, 'Marina', 'García López', 'marina@libreshop.es', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente')
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`),
  `apellidos` = VALUES(`apellidos`),
  `rol` = VALUES(`rol`);
