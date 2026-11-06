<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Clase Usuario
 *
 * Gestiona el registro, la autenticación segura, la consulta de perfiles y
 * la verificación de roles empleando PDO, consultas preparadas, password_hash()
 * y password_verify().
 */
class Usuario
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::conectar();
    }

    /**
     * Comprueba si un correo electrónico ya está registrado en la base de datos.
     */
    public function existeEmail(string $email, ?int $excluirId = null): bool
    {
        $emailNormalizado = mb_strtolower(trim($email));
        $sql              = 'SELECT id FROM usuarios WHERE email = :email';
        $params           = [':email' => $emailNormalizado];

        if ($excluirId !== null) {
            $sql                  .= ' AND id <> :excluir_id';
            $params[':excluir_id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /**
     * Obtiene los datos públicos de un usuario por su ID (nunca expone el hash de contraseña).
     *
     * @return array<string, mixed>|null
     */
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, apellidos, email, rol, fecha_registro
             FROM usuarios
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch();

        return $usuario !== false ? $usuario : null;
    }

    /**
     * Registra un nuevo usuario con rol 'cliente' tras validar todos los campos en el servidor.
     *
     * Seguridad:
     * - Ignora cualquier campo 'rol' enviado desde el cliente para evitar escalada de privilegios.
     * - Almacena exclusivamente el hash generado con password_hash(..., PASSWORD_DEFAULT).
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed> Datos del usuario recién registrado (sin contraseña).
     * @throws InvalidArgumentException Si algún campo no supera la validación.
     */
    public function registrar(array $datos): array
    {
        $nombreInput    = trim((string) ($datos['nombre'] ?? $datos['name'] ?? ''));
        $apellidosInput = trim((string) ($datos['apellidos'] ?? ''));
        $email          = mb_strtolower(trim((string) ($datos['email'] ?? '')));
        $password       = (string) ($datos['password'] ?? '');
        $confirmacion   = (string) ($datos['confirm'] ?? $datos['password_confirm'] ?? '');

        if ($apellidosInput === '' && str_contains($nombreInput, ' ')) {
            $partes    = preg_split('/\s+/', $nombreInput, 2);
            $nombre    = trim((string) ($partes[0] ?? ''));
            $apellidos = trim((string) ($partes[1] ?? ''));
        } else {
            $nombre    = $nombreInput;
            $apellidos = $apellidosInput;
        }

        if ($nombre === '' || mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) {
            throw new InvalidArgumentException('Introduce tu nombre (entre 2 y 80 caracteres).');
        }

        if (mb_strlen($apellidos) > 120) {
            throw new InvalidArgumentException('Los apellidos no pueden superar los 120 caracteres.');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
            throw new InvalidArgumentException('Introduce un correo electrónico válido.');
        }

        if (mb_strlen($password) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
        }

        if (mb_strlen($password) > 72) {
            throw new InvalidArgumentException('La contraseña no debe superar los 72 caracteres.');
        }

        if ($password !== $confirmacion) {
            throw new InvalidArgumentException('Las contraseñas no coinciden.');
        }

        if ($this->existeEmail($email)) {
            throw new InvalidArgumentException('Este correo electrónico ya está registrado en LibreShop.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // El rol se fija estrictamente a 'cliente' en servidor; nunca se confía en datos del navegador.
        $rolSeguro = 'cliente';

        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nombre, apellidos, email, password, rol)
             VALUES (:nombre, :apellidos, :email, :password, :rol)'
        );
        $stmt->execute([
            ':nombre'    => $nombre,
            ':apellidos' => $apellidos,
            ':email'     => $email,
            ':password'  => $passwordHash,
            ':rol'       => $rolSeguro,
        ]);

        $idNuevo = (int) $this->db->lastInsertId();
        $creado  = $this->obtenerPorId($idNuevo);

        if ($creado === null) {
            throw new RuntimeException('No se pudo recuperar el usuario tras el registro.');
        }

        return $creado;
    }

    /**
     * Autentica a un usuario mediante email y contraseña empleando password_verify().
     *
     * @return array<string, mixed> Datos del usuario autenticado (sin hash de contraseña).
     * @throws InvalidArgumentException Si las credenciales no son válidas.
     */
    public function autenticar(string $email, string $password): array
    {
        $emailNormalizado = mb_strtolower(trim($email));

        if ($emailNormalizado === '' || !filter_var($emailNormalizado, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Introduce un correo electrónico válido.');
        }

        if ($password === '') {
            throw new InvalidArgumentException('Introduce tu contraseña.');
        }

        $stmt = $this->db->prepare(
            'SELECT id, nombre, apellidos, email, password, rol, fecha_registro
             FROM usuarios
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute([':email' => $emailNormalizado]);
        $usuario = $stmt->fetch();

        if ($usuario === false || !password_verify($password, (string) $usuario['password'])) {
            throw new InvalidArgumentException('Correo electrónico o contraseña incorrectos.');
        }

        if (password_needs_rehash((string) $usuario['password'], PASSWORD_DEFAULT)) {
            $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUpd   = $this->db->prepare('UPDATE usuarios SET password = :password WHERE id = :id');
            $stmtUpd->execute([
                ':password' => $nuevoHash,
                ':id'       => (int) $usuario['id'],
            ]);
        }

        unset($usuario['password']);

        return $usuario;
    }

    /**
     * Proceso de inicialización controlado e idempotente para las cuentas de demostración.
     *
     * Seguridad:
     * - NUNCA se ejecuta durante el flujo de inicio de sesión.
     * - NUNCA sobrescribe contraseñas, roles, nombres ni apellidos de cuentas ya existentes.
     * - Únicamente inserta una cuenta si su email aún no existe en la tabla `usuarios`.
     *
     * Cuentas iniciales para evaluación académica:
     * - Admin:   admin@libreshop.es  / Admin1234!
     * - Cliente: marina@libreshop.es / Cliente1234!
     */
    public function asegurarCuentasDemo(): void
    {
        $cuentas = [
            [
                'nombre'         => 'Administrador',
                'apellidos'      => 'LibreShop',
                'email'          => 'admin@libreshop.es',
                'password_plana' => 'Admin1234!',
                'rol'            => 'administrador',
            ],
            [
                'nombre'         => 'Marina',
                'apellidos'      => 'García López',
                'email'          => 'marina@libreshop.es',
                'password_plana' => 'Cliente1234!',
                'rol'            => 'cliente',
            ],
        ];

        $stmtInsert = $this->db->prepare(
            'INSERT IGNORE INTO usuarios (nombre, apellidos, email, password, rol)
             VALUES (:nombre, :apellidos, :email, :password, :rol)'
        );

        foreach ($cuentas as $cuenta) {
            if ($this->existeEmail($cuenta['email'])) {
                continue;
            }

            $stmtInsert->execute([
                ':nombre'    => $cuenta['nombre'],
                ':apellidos' => $cuenta['apellidos'],
                ':email'     => $cuenta['email'],
                ':password'  => password_hash($cuenta['password_plana'], PASSWORD_DEFAULT),
                ':rol'       => $cuenta['rol'],
            ]);
        }
    }
}
