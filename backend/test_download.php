<?php
// test_download.php — Script para probar descarga de media de YCloud
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ycloud.php';

header('Content-Type: text/plain');

$mediaId = "1051124407444361"; // ID del log
$phone = YCLOUD_FROM_PHONE; // "525537041142"
$apiKey = YCLOUD_API_KEY;

$urls = [
    "Default" => "https://api.ycloud.com/v2/whatsapp/media/{$mediaId}",
    "With Phone Path" => "https://api.ycloud.com/v2/whatsapp/media/{$phone}/{$mediaId}",
    "With Phone Query" => "https://api.ycloud.com/v2/whatsapp/media/{$mediaId}?phoneNumber={$phone}",
    "With Phone Query formatted" => "https://api.ycloud.com/v2/whatsapp/media/{$mediaId}?phoneNumber=%2B525537041142",
    "Upload Path GET" => "https://api.ycloud.com/v2/whatsapp/media/{$phone}/upload/{$mediaId}",
    "Meta Graph Direct" => "https://graph.facebook.com/v19.0/{$mediaId}",
    "Invalid Route" => "https://api.ycloud.com/v2/whatsapp/xyz",
    "Get Phone Number Details" => "https://api.ycloud.com/v2/whatsapp/phoneNumbers/3912557032207779/525537041142",
    "Media Download Path Prefix" => "https://api.ycloud.com/v2/whatsapp/media/download/{$mediaId}",
    "Media Download Path Suffix" => "https://api.ycloud.com/v2/whatsapp/media/{$mediaId}/download",
];

foreach ($urls as $label => $url) {
    echo "=== testing $label: $url ===\n";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            "X-API-Key: {$apiKey}",
            "Authorization: Bearer {$apiKey}",
            "Accept: application/json"
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
}
