<?php
// api/canje.php — Endpoint de canje (Promoción Finalizada)
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config.php';

http_response_code(400);
echo json_encode([
    "success" => false,
    "error" => "La promoción G15K de Gatorade® ha finalizado. Ya no es posible registrar tickets ni realizar canjes."
]);
exit;
