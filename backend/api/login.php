<?php
// api/login.php — Autenticación para el administrador del panel Angular
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Usuario y contraseña son requeridos."]);
    exit;
}

// Validar usuario
$isValid = false;
if (defined('ADMIN_USERS') && is_array(ADMIN_USERS)) {
    if (isset(ADMIN_USERS[$username])) {
        $storedValue = ADMIN_USERS[$username];
        if (strpos($storedValue, '$2y$') === 0) {
            if (password_verify($password, $storedValue)) {
                $isValid = true;
            }
        } else {
            if ($password === $storedValue) {
                $isValid = true;
            }
        }
    }
}

// Fallbacks basados en constantes definidas en config.php
if (!$isValid && $username === 'admin' && defined('ADMIN_PASS_ADMIN') && $password === ADMIN_PASS_ADMIN) {
    $isValid = true;
}
if (!$isValid && $username === 'operaciones' && defined('ADMIN_PASS_OPERACIONES') && $password === ADMIN_PASS_OPERACIONES) {
    $isValid = true;
}

if ($isValid) {
    // Generar un Token simple firmado (HMAC-SHA256) sin requerir Composer
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'user' => $username,
        'role' => 'admin',
        'exp' => time() + (3600 * 8) // Expiración en 8 horas
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, DB_PASS, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $token = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    echo json_encode([
        "success" => true,
        "token" => $token,
        "user" => [
            "username" => $username,
            "role" => "admin"
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(["error" => "Credenciales incorrectas."]);
}
