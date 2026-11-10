<?php
declare(strict_types=1);

/**
 * Configuración y conexión a la base de datos MySQL mediante PDO.
 *
 * Cumple con los requisitos de seguridad:
 * - Uso de variables de configuración y entorno (sin credenciales reales expuestas).
 * - Charset UTF-8 completo (utf8mb4).
 * - Consultas preparadas nativas (ATTR_EMULATE_PREPARES = false).
 * - Manejo de excepciones (ATTR_ERRMODE = ERRMODE_EXCEPTION).
 * - Ocultación de errores internos y credenciales al usuario final.
 */
class Database
{
    private static ?PDO $conexion = null;

    /**
     * Obtiene los parámetros de configuración de forma segura.
     *
     * @return array<string, string>
     */
    private static function obtenerConfiguracion(): array
    {
        $config = [
            'host'    => getenv('DB_HOST') ?: '127.0.0.1',
            'port'    => getenv('DB_PORT') ?: '3306',
            'dbname'  => getenv('DB_NAME') ?: 'libreshop',
            'user'    => getenv('DB_USER') ?: 'root',
            'pass'    => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '',
            'charset' => 'utf8mb4',
        ];

        // Permite sobrescribir localmente sin subir credenciales al repositorio
        $archivoLocal = __DIR__ . '/database.local.php';
        if (is_file($archivoLocal)) {
            $configLocal = require $archivoLocal;
            if (is_array($configLocal)) {
                $config = array_merge($config, $configLocal);
            }
        }

        return $config;
    }

    /**
     * Devuelve una instancia única (Singleton) de PDO conectada a MySQL.
     *
     * @throws RuntimeException Si no es posible establecer la conexión.
     */
    public static function conectar(): PDO
    {
        if (self::$conexion instanceof PDO) {
            return self::$conexion;
        }

        $cfg = self::obtenerConfiguracion();
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['dbname'],
            $cfg['charset']
        );

        try {
            self::$conexion = new PDO($dsn, $cfg['user'], $cfg['pass'], $opciones);
            return self::$conexion;
        } catch (PDOException $e) {
            // Si la base de datos aún no existe en el servidor local, intentamos inicializarla desde database.sql
            if ((int) $e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                try {
                    self::inicializarBaseDeDatos($cfg, $opciones);
                    self::$conexion = new PDO($dsn, $cfg['user'], $cfg['pass'], $opciones);
                    return self::$conexion;
                } catch (Throwable $initError) {
                    error_log('[LibreShop DB Init Error] ' . $initError->getMessage());
                }
            }

            error_log('[LibreShop DB Connection Error] ' . $e->getMessage());
            throw new RuntimeException(
                'No ha sido posible conectar con el servicio de datos en este momento. Por favor, inténtelo más tarde.'
            );
        }
    }

    /**
     * Ejecuta database.sql automáticamente si la base de datos local aún no ha sido creada.
     *
     * @param array<string, string> $cfg
     * @param array<int, mixed>     $opciones
     */
    private static function inicializarBaseDeDatos(array $cfg, array $opciones): void
    {
        $dsnSinDb = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['charset']
        );

        $opcionesTemp = $opciones;
        $opcionesTemp[PDO::ATTR_EMULATE_PREPARES] = true;

        $pdoTemp = new PDO($dsnSinDb, $cfg['user'], $cfg['pass'], $opcionesTemp);
        $rutaSql = dirname(__DIR__) . '/database.sql';

        if (!is_file($rutaSql)) {
            throw new RuntimeException('No se encontró el archivo database.sql.');
        }

        $sql = file_get_contents($rutaSql);
        if ($sql === false) {
            throw new RuntimeException('No se pudo leer el archivo database.sql.');
        }

        $nombreBd = preg_replace('/[^a-zA-Z0-9_]/', '', $cfg['dbname']) ?: 'libreshop';
        if ($nombreBd !== 'libreshop') {
            $sql = preg_replace(
                '/\bCREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`?libreshop`?/i',
                'CREATE DATABASE IF NOT EXISTS `' . $nombreBd . '`',
                $sql
            ) ?? $sql;
            $sql = preg_replace(
                '/\bUSE\s+`?libreshop`?\s*;/i',
                'USE `' . $nombreBd . '`;',
                $sql
            ) ?? $sql;
        }

        $pdoTemp->exec($sql);
    }
}

/**
 * Función auxiliar para obtener la conexión PDO en cualquier punto de la aplicación.
 */
function obtenerConexion(): PDO
{
    return Database::conectar();
}
