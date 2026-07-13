<?php
// api/sucursales.php — Listado de sucursales (protegido)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/auth_helper.php';
$userData = validateAuth(); // Proteger endpoint

require_once __DIR__ . '/../db.php';

$idCadena = isset($_GET['idCadena']) ? (int)$_GET['idCadena'] : 0;

try {
    if ($idCadena > 0) {
        $rows = DB::select("SELECT idSucursal, idCadena, NumeroTienda, Tienda FROM tblSucursal WHERE Activo = 1 AND idCadena = ? ORDER BY Tienda ASC", [$idCadena]);
    } else {
        $rows = DB::select("SELECT idSucursal, idCadena, NumeroTienda, Tienda FROM tblSucursal WHERE Activo = 1 ORDER BY Tienda ASC");
    }
    echo json_encode(["success" => true, "data" => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error al obtener sucursales: " . $e->getMessage()]);
}
