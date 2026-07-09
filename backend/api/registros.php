<?php
// api/registros.php — Gestión de registros por el administrador (Angular)
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/auth_helper.php';
$userData = validateAuth(); // Proteger endpoint

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../ycloud.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Listar todos los registros
    try {
        $query = "SELECT r.*, u.Celular, u.Nombre as NombreUsuario,
                         c.Nombre AS NombreCadena, p.Producto AS NombreProducto
                  FROM tblRegistro r
                  JOIN tblUsuario u ON r.idUsuario = u.idUsuario
                  LEFT JOIN tblCadena c ON r.idCadena = c.idCadena
                  LEFT JOIN tblProducto p ON r.idProducto = p.idProducto
                  ORDER BY r.FechaRegistro DESC";
        $registros = DB::select($query);
        
        // Agregar URL completa de la foto
        foreach ($registros as &$reg) {
            $reg['FotoCajasUrl'] = APP_URL . '/uploads/' . $reg['FotoCajas'];
        }
        
        echo json_encode(["success" => true, "data" => $registros]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener registros: " . $e->getMessage()]);
    }
} 
elseif ($method === 'POST') {
    // Procesar Aprobación o Rechazo
    $data = json_decode(file_get_contents("php://input"), true);
    
    $idRegistro = (int)($data['idRegistro'] ?? 0);
    $accion     = trim($data['accion'] ?? ''); // 'aprobar' o 'rechazar'
    $motivo     = trim($data['motivo'] ?? ''); // requerido si es rechazo

    if (!$idRegistro || !in_array($accion, ['aprobar', 'rechazar'])) {
        http_response_code(400);
        echo json_encode(["error" => "idRegistro y acción ('aprobar' o 'rechazar') son requeridos."]);
        exit;
    }

    try {
        // Obtener el registro y el usuario
        $registro = DB::selectOne(
            "SELECT r.*, u.Celular, u.Nombre FROM tblRegistro r 
             JOIN tblUsuario u ON r.idUsuario = u.idUsuario 
             WHERE r.idRegistro = ? AND r.Activo = 1", 
            [$idRegistro]
        );

        if (!$registro) {
            http_response_code(404);
            echo json_encode(["error" => "Registro no encontrado."]);
            exit;
        }

        if ((int)$registro['Estatus'] !== 1) {
            http_response_code(400);
            echo json_encode(["error" => "Este registro ya fue procesado previamente."]);
            exit;
        }

        $wa = new YCloudService();

        if ($accion === 'aprobar') {
            $folioTicket = trim($data['FolioTicket'] ?? '');
            $fechaTicket = trim($data['FechaTicket'] ?? '');
            $montoTicket = isset($data['MontoTicket']) ? trim($data['MontoTicket']) : '';
            $idCadena    = !empty($data['idCadena']) ? (int)$data['idCadena'] : null;
            $idProducto  = !empty($data['idProducto']) ? (int)$data['idProducto'] : null;

            if (empty($folioTicket)) {
                http_response_code(400);
                echo json_encode(["error" => "El folio del ticket es requerido."]);
                exit;
            }
            if (empty($fechaTicket)) {
                http_response_code(400);
                echo json_encode(["error" => "La fecha del ticket es requerida."]);
                exit;
            }
            if ($montoTicket === '') {
                http_response_code(400);
                echo json_encode(["error" => "El monto del ticket es requerido."]);
                exit;
            }

            // Validar que el Folio no esté duplicado
            $folioExists = DB::selectOne(
                "SELECT 1 FROM tblRegistro WHERE FolioTicket = ? AND Activo = 1 AND idRegistro != ?",
                [$folioTicket, $idRegistro]
            );
            if ($folioExists) {
                http_response_code(409);
                echo json_encode(["error" => "El folio '{$folioTicket}' ya existe en otro registro. Verifica el número de folio."]);
                exit;
            }

            // Actualizar registro en BD a Aprobado (Estatus = 2)
            DB::execute(
                "UPDATE tblRegistro SET 
                    Estatus = 2, 
                    FolioTicket = ?, 
                    FechaTicket = ?, 
                    MontoTicket = ?, 
                    idCadena = ?, 
                    idProducto = ?, 
                    FechaValidacion = NOW() 
                 WHERE idRegistro = ?",
                [$folioTicket, $fechaTicket, $montoTicket, $idCadena, $idProducto, $idRegistro]
            );

            // Enviar mensaje de aprobación de Gatorade G15K
            $mensaje = "🎉 ¡Felicidades! Tu ticket de compra ha sido validado correctamente.\n\n"
                     . "En caso de resultar entre uno de los ganadores te lo indicaremos por este mismo medio. 😊\n\n"
                     . "Si tienes más compras por registrar escribe la palabra Hola y sigue nuevamente los pasos del Bot.\n"
                     . "¡Gracias por participar en la promoción G15K de Gatorade®!";

            $notificado = false;
            $diffHours = (time() - strtotime($registro['FechaRegistro'])) / 3600;

            if ($diffHours > 24 && defined('YCLOUD_TEMPLATE_APROBACION') && !empty(YCLOUD_TEMPLATE_APROBACION)) {
                $res = $wa->sendTemplate($registro['Celular'], YCLOUD_TEMPLATE_APROBACION, 'es_MX', [
                    $registro['Nombre'] ?: 'Participante'
                ]);
                $notificado = $res['success'] ?? false;
            }

            if (!$notificado) {
                $res = $wa->sendText($registro['Celular'], $mensaje);
                if (empty($res['success']) && defined('YCLOUD_TEMPLATE_APROBACION') && !empty(YCLOUD_TEMPLATE_APROBACION)) {
                    $wa->sendTemplate($registro['Celular'], YCLOUD_TEMPLATE_APROBACION, 'es_MX', [
                        $registro['Nombre'] ?: 'Participante'
                    ]);
                }
            }

            echo json_encode([
                "success" => true,
                "message" => "Registro aprobado y usuario notificado."
            ]);
        } 
        elseif ($accion === 'rechazar') {
            if (empty($motivo)) {
                http_response_code(400);
                echo json_encode(["error" => "El motivo de rechazo es obligatorio."]);
                exit;
            }

            // Actualizar registro en BD a Rechazado (Estatus = 3)
            DB::execute(
                "UPDATE tblRegistro SET Estatus = 3, MotivoRechazo = ?, FechaValidacion = NOW() WHERE idRegistro = ?",
                [$motivo, $idRegistro]
            );

            // Permitir al usuario reintentar reiniciando su PasoBot
            DB::execute(
                "UPDATE tblUsuario SET PasoBot = 'BIENVENIDA' WHERE idUsuario = ?",
                [$registro['idUsuario']]
            );

            // Enviar mensaje de rechazo de Gatorade G15K
            $mensaje = "⚠️ ¡Lo sentimos!\n\n"
                     . "Tu ticket de compra no pudo ser validado por el siguiente motivo:\n"
                     . "*{$motivo}*\n\n"
                     . "Te recomendamos enviar un ticket de acuerdo con las especificaciones en los TyC de la promoción.\n"
                     . "Si tienes otro ticket o quieres volver a intentarlo escribe la palabra Hola y sigue nuevamente los pasos del Bot.\n"
                     . "¡Gracias por participar en la promoción G15K de Gatorade®!";

            $notificado = false;
            $diffHours = (time() - strtotime($registro['FechaRegistro'])) / 3600;

            if ($diffHours > 24 && defined('YCLOUD_TEMPLATE_RECHAZO') && !empty(YCLOUD_TEMPLATE_RECHAZO)) {
                $res = $wa->sendTemplate($registro['Celular'], YCLOUD_TEMPLATE_RECHAZO, 'es_MX', [
                    $registro['Nombre'] ?: 'Participante',
                    $motivo
                ]);
                $notificado = $res['success'] ?? false;
            }

            if (!$notificado) {
                $res = $wa->sendText($registro['Celular'], $mensaje);
                if (empty($res['success']) && defined('YCLOUD_TEMPLATE_RECHAZO') && !empty(YCLOUD_TEMPLATE_RECHAZO)) {
                    $wa->sendTemplate($registro['Celular'], YCLOUD_TEMPLATE_RECHAZO, 'es_MX', [
                        $registro['Nombre'] ?: 'Participante',
                        $motivo
                    ]);
                }
            }

            echo json_encode(["success" => true, "message" => "Registro rechazado y usuario notificado."]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno al procesar el registro: " . $e->getMessage()]);
    }
}
