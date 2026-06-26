<?php
// db.php — Conexión y operaciones de base de datos seguras usando PDO
require_once __DIR__ . '/config.php';

class DB
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("DB Connection Error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["error" => "Error de conexión a la base de datos."]);
                exit;
            }
        }
        return self::$instance;
    }

    /**
     * Ejecuta una consulta SELECT y devuelve todos los registros.
     */
    public static function select(string $query, array $params = []): array
    {
        $stmt = self::connect()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ejecuta una consulta SELECT y devuelve una sola fila.
     */
    public static function selectOne(string $query, array $params = []): ?array
    {
        $stmt = self::connect()->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ? $result : null;
    }

    /**
     * Ejecuta una consulta INSERT, UPDATE o DELETE.
     */
    public static function execute(string $query, array $params = []): bool
    {
        $stmt = self::connect()->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Devuelve el último ID insertado.
     */
    public static function lastInsertId(): string
    {
        return self::connect()->lastInsertId();
    }

    /**
     * Inicia una transacción.
     */
    public static function beginTransaction(): bool
    {
        return self::connect()->beginTransaction();
    }

    /**
     * Confirma una transacción.
     */
    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    /**
     * Revierte una transacción.
     */
    public static function rollBack(): bool
    {
        return self::connect()->rollBack();
    }
}
