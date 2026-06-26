<?php
// api/telefonias.php — Listado de compañías telefónicas activas (público)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';

try {
    $rows = DB::select("SELECT idTelefonia, Telefonia FROM tblTelefonia WHERE Activo = 1 ORDER BY Telefonia ASC");
    echo json_encode(["success" => true, "data" => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error al obtener compañías telefónicas: " . $e->getMessage()]);
}
