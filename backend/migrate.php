<?php
// migrate.php — Migración de base de datos
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "Iniciando migración...\n";

try {
    // 1. Agregar columnas temporales a tblUsuario
    $queries = [
        "ALTER TABLE tblUsuario ADD COLUMN TempFotoCajas VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE tblUsuario ADD COLUMN TempIdTelefonia INT DEFAULT NULL"
    ];

    foreach ($queries as $q) {
        try {
            DB::execute($q);
            echo "Ejecutado: $q\n";
        } catch (Exception $e) {
            echo "Aviso (puede que ya exista): " . $e->getMessage() . "\n";
        }
    }

    echo "Migración completada exitosamente.\n";
} catch (Exception $e) {
    echo "Error general: " . $e->getMessage() . "\n";
}
