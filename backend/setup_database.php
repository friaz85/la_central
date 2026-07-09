<?php
// setup_database.php — Script para ejecutar database.sql en la base de datos configurada

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

echo "Iniciando configuración de Base de Datos...\n";
echo "Host: " . DB_HOST . "\n";
echo "Usuario: " . DB_USER . "\n";
echo "Base de Datos: " . DB_NAME . "\n\n";

try {
    $pdo = DB::connect();
    echo "¡Conexión a la base de datos exitosa!\n";

    $sqlPath = dirname(__DIR__) . '/database.sql';
    if (!file_exists($sqlPath)) {
        throw new Exception("No se encontró el archivo database.sql en " . $sqlPath);
    }

    $sql = file_get_contents($sqlPath);
    echo "Leyendo database.sql...\n";

    // Ejecutar consultas en lote
    $pdo->exec($sql);
    echo "¡Las tablas y datos iniciales fueron creados/actualizados exitosamente!\n";

    // Mostrar las tablas existentes
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas actuales en la BD: " . implode(', ', $tables) . "\n";

} catch (Exception $e) {
    echo "❌ Error al configurar la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}
