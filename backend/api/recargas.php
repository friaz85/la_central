<?php
// api/recargas.php — Gestión de registros de recargas por el administrador (Angular)
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/auth_helper.php';
$userData = validateAuth(); // Proteger el endpoint con JWT

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Query para traer logs de recarga con información de usuario, teléfono receptor y código único de participación
        $query = "SELECT lr.idLog, lr.idRegistro, lr.Mensaje, lr.Codigo, lr.FechaRegistro, 
                         r.CodigoUnico, r.TelefonoRecarga, r.Monto, r.FolioRecarga AS Folio,
                         u.Celular, u.Nombre as NombreUsuario,
                         t.Telefonia
                  FROM tblLogRecarga lr
                  LEFT JOIN tblRegistro r ON lr.idRegistro = r.idRegistro
                  LEFT JOIN tblUsuario u ON r.idUsuario = u.idUsuario
                  LEFT JOIN tblTelefonia t ON r.idTelefonia = t.idTelefonia
                  ORDER BY lr.FechaRegistro DESC";
                  
        $recargas = DB::select($query);
        
        echo json_encode(["success" => true, "data" => $recargas]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener recargas: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido."]);
}
