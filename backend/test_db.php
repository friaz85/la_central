<?php
// test_db.php - Script de prueba para BD y YCloud API
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/meta_wa.php';

// Cargar la configuración de la BD
require_once __DIR__ . '/db.php';

echo "Probando conexión a Base de Datos...\n";
try {
    $res = DB::select("SHOW TABLES");
    echo "Tablas encontradas: " . json_encode($res) . "\n\n";
} catch (Exception $e) {
    echo "Error de BD: " . $e->getMessage() . "\n\n";
}

echo "Probando llamada API Meta WA (Video)...\n";
try {
    $wa = new MetaWAService();
    $res = $wa->sendVideo('525537041142', 'https://clasicoslafe.qrewards.com.mx/assets/video.mp4', 'Video Prueba Meta');
    echo "Meta WA API response (Video): " . json_encode($res) . "\n\n";
} catch (Exception $e) {
    echo "Meta WA API error (Video): " . $e->getMessage() . "\n\n";
}
