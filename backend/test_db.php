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

echo "Probando llamada API YCloud (Video)...\n";
try {
    $wa = new YCloudService();
    $videoUrl = "https://clasicoslafe.qrewards.com.mx/assets/video.mp4";
    $targetPhone = "+525540297872"; // Teléfono del usuario
    $res = $wa->sendVideo($targetPhone, $videoUrl, "Video de prueba");
    echo "YCloud API response (Video): " . json_encode($res) . "\n\n";
} catch (Exception $e) {
    echo "YCloud API error (Video): " . $e->getMessage() . "\n\n";
}
