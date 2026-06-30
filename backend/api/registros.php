<?php
// api/registros.php — Gestión de registros por el administrador (Angular)
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/auth_helper.php';
$userData = validateAuth(); // Proteger endpoint

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../meta_wa.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Listar todos los registros
    try {
        $query = "SELECT r.*, u.Celular, u.Nombre as NombreUsuario 
                  FROM tblRegistro r
                  JOIN tblUsuario u ON r.idUsuario = u.idUsuario
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

        $wa = new MetaWAService();

        if ($accion === 'aprobar') {
            // Validar que el registro tenga una telefonía seleccionada
            $idTelefonia = (int)($registro['idTelefonia'] ?? 0);
            if (!$idTelefonia) {
                http_response_code(400);
                echo json_encode(["error" => "Este registro no tiene una compañía telefónica seleccionada."]);
                exit;
            }

            // Obtener compañía telefónica y SKU
            $telefoniaRow = DB::selectOne("SELECT * FROM tblTelefonia WHERE idTelefonia = ? AND Activo = 1", [$idTelefonia]);
            if (!$telefoniaRow) {
                http_response_code(400);
                echo json_encode(["error" => "Compañía telefónica no encontrada o inactiva."]);
                exit;
            }

            // Limpiar el número de celular a 10 dígitos para la recarga
            $telefonoInput = trim($data['telefono'] ?? '');
            if (empty($telefonoInput)) {
                http_response_code(400);
                echo json_encode(["error" => "El número telefónico es obligatorio para realizar la recarga."]);
                exit;
            }
            $telefono = substr(preg_replace('/\D/', '', $telefonoInput), -10);
            if (strlen($telefono) !== 10) {
                http_response_code(400);
                echo json_encode(["error" => "El número telefónico para la recarga debe tener 10 dígitos."]);
                exit;
            }

            // Contar cuántas aprobaciones exitosas ya existen para determinar si es el registro 100
            $approvedCountRow = DB::selectOne("SELECT COUNT(*) as total FROM tblRegistro WHERE Estatus IN (2, 4, 5) AND Activo = 1");
            $approvedCount = (int)($approvedCountRow['total'] ?? 0);
            
            $monto = 20.00;
            // Si es la participación número 100, 200, 300...
            if (($approvedCount + 1) % 100 === 0) {
                $monto = 50.00;
            }

            // Formatear SKU de Taecel con monto a 3 dígitos (ej: TELCEL020, BAIT050)
            $sku = $telefoniaRow['SKU'] . sprintf("%03d", $monto);

            // 1. Actualizar registro a Estatus = 5 (En proceso) y guardar datos básicos
            DB::execute(
                "UPDATE tblRegistro SET 
                    Estatus = 5, 
                    Monto = ?, 
                    TelefonoRecarga = ?, 
                    FechaValidacion = NOW() 
                 WHERE idRegistro = ?",
                [$monto, $telefono, $idRegistro]
            );

            // 2. Enviar solicitud de recarga a Taecel (RequestTXN)
            $paramsTxn = [
                'key'        => TAECEL_KEY,
                'nip'        => TAECEL_NIP,
                'producto'   => $sku,
                'referencia' => $telefono,
            ];
            
            $txnResponse = taecelRequest('RequestTXN', $paramsTxn);
            $transID = $txnResponse['data']['transID'] ?? '';

            if (!empty($transID)) {
                DB::execute("UPDATE tblRegistro SET TransID = ? WHERE idRegistro = ?", [$transID, $idRegistro]);
            }

            $recargaExitosa = false;
            $folio = '';

            if (!empty($txnResponse['success']) && $txnResponse['success']) {
                // Verificar estatus inmediatamente (StatusTXN)
                $statusResponse = taecelRequest('StatusTXN', [
                    'key'     => TAECEL_KEY,
                    'nip'     => TAECEL_NIP,
                    'transID' => $transID,
                ]);

                if (!empty($statusResponse['success']) && $statusResponse['success']) {
                    $dataRes = $statusResponse['data'] ?? $statusResponse;
                    $saldoRaw = $dataRes['Saldo Final'] ?? $dataRes['Saldo'] ?? '0';
                    $saldo = preg_replace('/[^0-9.]/', '', str_replace(',', '', $saldoRaw));
                    $folio = $dataRes['Folio'] ?? $dataRes['folio'] ?? '';

                    // Actualizar a éxito (Estatus = 4)
                    DB::execute(
                        "UPDATE tblRegistro SET 
                            Estatus = 4, 
                            FolioRecarga = ?, 
                            Saldo_Final = ? 
                         WHERE idRegistro = ?",
                        [$folio, $saldo, $idRegistro]
                    );

                    // Insertar Log de Recarga
                    DB::execute(
                        "INSERT INTO tblLogRecarga (idRegistro, Mensaje, Codigo) VALUES (?, ?, '0')",
                        [$idRegistro, "Recarga exitosa desde admin. Folio: $folio. Saldo Taecel: $saldoRaw"]
                    );
                    $recargaExitosa = true;
                } else {
                    $msgError = $statusResponse['message'] ?? 'Error al verificar estatus';
                    $errCode = (string)($statusResponse['error'] ?? 'E');

                    DB::execute(
                        "INSERT INTO tblLogRecarga (idRegistro, Mensaje, Codigo) VALUES (?, ?, ?)",
                        [$idRegistro, "Fallo verificación desde admin: $msgError", $errCode]
                    );
                }
            } else {
                $msgError = $txnResponse['message'] ?? 'Error en solicitud de recarga';
                $errCode = (string)($txnResponse['error'] ?? 'E');

                DB::execute(
                    "INSERT INTO tblLogRecarga (idRegistro, Mensaje, Codigo) VALUES (?, ?, ?)",
                    [$idRegistro, "Fallo RequestTXN desde admin: $msgError", $errCode]
                );
            }

            // 3. Notificar al usuario por WhatsApp
            if ($recargaExitosa) {
                $mensaje = "🎉 ¡Felicidades! Tu registro ha sido *aprobado y validado correctamente* ✅\n\n"
                         . "Hemos realizado tu recarga de *$" . number_format($monto, 2) . " de Tiempo Aire* de forma directa a tu celular. \n"
                         . "Folio de confirmación: *{$folio}*.\n\n"
                         . "¡Muchas gracias por participar en *Clásicos La Fe! 🔥*";
            } else {
                $mensaje = "🎉 ¡Felicidades! Tu registro ha sido *aprobado y validado correctamente* ✅\n\n"
                         . "Tu recarga de *$" . number_format($monto, 2) . " de Tiempo Aire* está siendo procesada. \n"
                         . "En las próximas horas verás reflejado tu saldo en tu celular. 📱\n\n"
                         . "¡Muchas gracias por tu paciencia y por participar en *Clásicos La Fe! 🔥*";
            }

            $notificado = false;
            $diffHours = (time() - strtotime($registro['FechaRegistro'])) / 3600;

            if ($diffHours > 24 && defined('META_TEMPLATE_APROBACION') && !empty(META_TEMPLATE_APROBACION)) {
                // Notificar por plantilla (fuera de ventana de 24h)
                // Parámetros de plantilla sugeridos: [Nombre, Monto, Folio]
                $res = $wa->sendTemplate($registro['Celular'], META_TEMPLATE_APROBACION, 'es_MX', [
                    $registro['Nombre'] ?: 'Participante',
                    number_format($monto, 2),
                    $folio ?: 'En proceso'
                ]);
                $notificado = $res['success'] ?? false;
            }

            if (!$notificado) {
                // Dentro de las 24h o fallback
                $res = $wa->sendText($registro['Celular'], $mensaje);
                if (empty($res['success']) && defined('META_TEMPLATE_APROBACION') && !empty(META_TEMPLATE_APROBACION)) {
                    // Si falló el texto libre, intentar con plantilla
                    $wa->sendTemplate($registro['Celular'], META_TEMPLATE_APROBACION, 'es_MX', [
                        $registro['Nombre'] ?: 'Participante',
                        number_format($monto, 2),
                        $folio ?: 'En proceso'
                    ]);
                }
            }

            echo json_encode([
                "success" => true, 
                "message" => "Registro aprobado. Recarga procesada (" . ($recargaExitosa ? "Éxito Folio: $folio" : "Pendiente/En proceso") . ") y usuario notificado."
            ]);
        } 
        elseif ($accion === 'rechazar') {
            if (empty($motivo)) {
                http_response_code(400);
                echo json_encode(["error" => "El motivo de rechazo es obligatorio."]);
                exit;
            }

            // Actualizar registro en BD
            DB::execute(
                "UPDATE tblRegistro SET Estatus = 3, MotivoRechazo = ?, FechaValidacion = NOW() WHERE idRegistro = ?",
                [$motivo, $idRegistro]
            );

            // Permitir al usuario reintentar reiniciando su PasoBot
            DB::execute(
                "UPDATE tblUsuario SET PasoBot = 'BIENVENIDA' WHERE idUsuario = ?",
                [$registro['idUsuario']]
            );

            // Enviar mensaje de rechazo
            $mensaje = "Hola " . ($registro['Nombre'] ?: 'Participante') . ", lamentamos informarte que tu registro ha sido *rechazado* ❌\n\n"
                     . "Motivo: *{$motivo}*\n\n"
                     . "Si deseas volver a participar con fotos válidas, por favor escribe la palabra *Hola* y sigue las instrucciones. ¡Gracias! 😊";

            $notificado = false;
            $diffHours = (time() - strtotime($registro['FechaRegistro'])) / 3600;

            if ($diffHours > 24 && defined('META_TEMPLATE_RECHAZO') && !empty(META_TEMPLATE_RECHAZO)) {
                // Notificar por plantilla (fuera de ventana de 24h)
                // Parámetros de plantilla sugeridos: [Nombre, Motivo]
                $res = $wa->sendTemplate($registro['Celular'], META_TEMPLATE_RECHAZO, 'es_MX', [
                    $registro['Nombre'] ?: 'Participante',
                    $motivo
                ]);
                $notificado = $res['success'] ?? false;
            }

            if (!$notificado) {
                $res = $wa->sendText($registro['Celular'], $mensaje);
                if (empty($res['success']) && defined('META_TEMPLATE_RECHAZO') && !empty(META_TEMPLATE_RECHAZO)) {
                    $wa->sendTemplate($registro['Celular'], META_TEMPLATE_RECHAZO, 'es_MX', [
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

/**
 * Petición cURL a la API de Taecel.
 */
function taecelRequest(string $endpoint, array $params): array
{
    $url  = TAECEL_API_URL . $endpoint;
    $body = http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Taecel cURL error ($endpoint): $error");
        return ['success' => false, 'message' => $error];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log("Taecel invalid JSON response: $response");
        return ['success' => false, 'message' => 'Proveedor de recargas fuera de línea'];
    }

    return $decoded;
}
