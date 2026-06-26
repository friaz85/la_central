<?php
// api/auth_helper.php — Validaciones de autenticación de Token para API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config.php';

function validateAuth(): array
{
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(["error" => "No autorizado. Token faltante."]);
        exit;
    }

    $token = $matches[1];
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        http_response_code(401);
        echo json_encode(["error" => "Token con formato inválido."]);
        exit;
    }

    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;

    // Verificar firma
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlSignature));
    $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, DB_PASS, true);

    if (!hash_equals($signature, $expectedSignature)) {
        http_response_code(401);
        echo json_encode(["error" => "Firma de token inválida."]);
        exit;
    }

    // Decodificar payload
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlPayload)), true);

    if (!$payload || ($payload['exp'] ?? 0) < time()) {
        http_response_code(401);
        echo json_encode(["error" => "Token expirado o inválido."]);
        exit;
    }

    return $payload;
}
