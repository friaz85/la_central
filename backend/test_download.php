<?php
// test_download.php — Script para probar descarga de media de YCloud
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/meta_wa.php';

header('Content-Type: text/plain');

$mediaId = "1051124407444361"; // Reemplazar con un ID de media de Meta real al probar

echo "Probando descarga de media con MetaWAService...\n";
$wa = new MetaWAService();
$filename = "test_meta_download.jpg";

$result = $wa->downloadMedia($mediaId, $filename);

if ($result) {
    echo "Descarga exitosa. Guardado como: " . $result . "\n";
} else {
    echo "Fallo al descargar la media con ID: " . $mediaId . "\n";
}

