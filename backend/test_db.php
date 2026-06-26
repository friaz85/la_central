<?php
// test_db.php - Script de prueba para BD y YCloud API
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ycloud.php';

header('Content-Type: application/json');

echo "Probando conexión a Base de Datos...\n";
try {
    $res = DB::select("SELECT 1 AS ok");
    echo "BD OK: " . json_encode($res) . "\n\n";
} catch (Exception $e) {
    echo "BD Error: " . $e->getMessage() . "\n\n";
}

echo "Probando llamada API YCloud...\n";
try {
    $wa = new YCloudService();
    // Enviar un mensaje de prueba al número configurado
    $res = $wa->sendText(YCLOUD_FROM_PHONE, "Mensaje de prueba de conexión");
    echo "YCloud API response: " . json_encode($res) . "\n\n";
} catch (Exception $e) {
    echo "YCloud API error: " . $e->getMessage() . "\n\n";
}
