# LibreShop — Tienda Online de Libros (PHP 8 + MySQL + PDO)

Aplicación web completa de comercio electrónico desarrollada en **PHP 8**, **MySQL (InnoDB / UTF-8)**, **PDO**, **HTML5**, **CSS3** y **JavaScript**, migrada conservando fielmente el diseño visual original de **Figma Make**.

Proyecto académico para la asignatura de **Desarrollo Web en Entorno Servidor (DAW)**.

---

## 1. Arquitectura del Proyecto

```text
LibreShop/
├── config/
│   └── database.php          # Conexión PDO Singleton (utf8mb4, ERRMODE_EXCEPTION, consultas preparadas nativas)
├── includes/
│   ├── Cabecera.php          # Cabecera responsive sticky, contador de carrito, sesión activa y alertas flash
│   ├── Footer.php            # Pie de página corporativo (#0e2036)
│   ├── Sesiones.php          # Gestión segura de sesiones, CSRF, RBAC y prevención de Open Redirect / Session Fixation
│   └── Funciones.php         # Helpers de escape XSS, iconos SVG, portadas editoriales y tarjetas de producto
├── clases/
│   ├── Categoria.php         # CRUD orientado a objetos de categorías con control de integridad referencial
│   ├── Producto.php          # CRUD de libros, filtrado dinámico, búsqueda y subida segura de portadas
│   ├── Usuario.php           # Registro, login con password_hash()/password_verify() y gestión de roles
│   ├── Carrito.php           # Carrito en $_SESSION['carrito'] sincronizado en tiempo real con precios y stock de MySQL
│   └── Pedido.php            # Checkout transaccional (SELECT ... FOR UPDATE), propiedad de pedidos y máquina de estados
├── paginas/
│   ├── login.php             # Inicio de sesión (diseño dividido en 2 columnas de Figma Make)
│   ├── registro.php          # Registro de nuevos clientes con validación en servidor
│   ├── catalogo.php          # Catálogo dinámico con filtros por categoría, búsqueda y ordenación
│   ├── detalle.php           # Ficha individual de libro con stock real, selector de cantidad y libros relacionados
│   ├── carrito.php           # Gestión de líneas del carrito, subtotales y cálculo de gastos de envío
│   ├── checkout.php          # Finalización de compra autenticada con token de idempotencia y transacción SQL
│   ├── confirmacion.php      # Resumen de pedido confirmado con verificación estricta de propiedad
│   ├── pedidos.php           # Historial de compras del cliente autenticado y desglose por pedido
│   └── administracion.php    # Panel exclusivo para administradores (Productos, Categorías y Pedidos)
├── assets/
│   ├── css/estilos.css       # Hoja de estilos fiel al prototipo original de Figma Make
│   ├── js/app.js             # Interacciones de interfaz en cliente
│   └── img/portadas/         # Directorio de portadas subidas desde el gestor de productos
├── index.php                 # Portada principal con Hero, categorías dinámicas y libros destacados
├── logout.php                # Cierre de sesión seguro mediante POST + token CSRF
└── database.sql              # Esquema relacional MySQL y datos iniciales idempotentes
```

---

## 2. Requisitos e Instalación

1. **Requisitos**:
   - PHP 8.0 o superior con extensión `pdo_mysql` y `fileinfo`.
   - Servidor MySQL / MariaDB (por ejemplo, XAMPP, Laragon o WAMP).
2. **Base de datos**:
   - Importar `database.sql` en MySQL (`mysql -u root < database.sql`), o simplemente abrir `index.php` en el navegador: `config/database.php` inicializará automáticamente la base de datos `libreshop` desde `database.sql` si aún no existe en el servidor local.
3. **Configuración de conexión (opcional)**:
   - Por defecto conecta a `127.0.0.1:3306`, base de datos `libreshop`, usuario `root` sin contraseña.
   - Puede personalizarse mediante variables de entorno (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`) o creando un archivo `config/database.local.php` (ignorado en Git).

---

## 3. Cuentas de Prueba para Evaluación

| Rol | Correo electrónico | Contraseña | Permisos |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@libreshop.es` | `Admin1234!` | Acceso completo a la tienda y al Panel de Administración (`paginas/administracion.php`: gestión de libros, categorías y estados de pedidos). |
| **Cliente** | `marina@libreshop.es` | `Cliente1234!` | Compra de libros, gestión de carrito, checkout e historial personal de pedidos (`paginas/pedidos.php`). |

---

## 4. Seguridad y Buenas Prácticas Implementadas

- **Consultas preparadas PDO nativas** (`PDO::ATTR_EMULATE_PREPARES => false`) en el 100 % de las consultas SQL.
- **Autenticación y contraseñas**: Hash seguro con `password_hash(..., PASSWORD_DEFAULT)`, verificación con `password_verify()` y regeneración del ID de sesión (`session_regenerate_id(true)`) al iniciar y cerrar sesión.
- **Protección CSRF**: Token criptográfico validado con `hash_equals()` en todos los formularios `POST` (login, registro, logout, carrito, checkout y administración).
- **Integridad transaccional en compras**: `Pedido::crearDesdeCarrito()` emplea `BEGIN`, bloqueo pesimista `SELECT ... FOR UPDATE`, recálculo de precios desde MySQL, inserción en `pedidos` y `detalle_pedido`, descuento de stock y `COMMIT` (o `ROLLBACK` automático ante cualquier incidencia).
- **Control de acceso (RBAC) y propiedad de recursos**: Separación estricta entre `cliente` y `administrador` en servidor, e imposibilidad de consultar pedidos de otros usuarios modificando parámetros en la URL.
