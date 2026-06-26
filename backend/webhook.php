<?php
// webhook.php — Procesador de Webhook de YCloud para WhatsApp
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ycloud.php';

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

// Leer cuerpo de la petición
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!$data) {
    http_response_code(400);
    echo "JSON inválido";
    exit;
}

// Validar firma del webhook si está configurada (YCloud-Signature)
if (defined('YCLOUD_WEBHOOK_SECRET') && !empty(YCLOUD_WEBHOOK_SECRET)) {
    $signatureHeader = $_SERVER['HTTP_YCLOUD_SIGNATURE'] ?? '';
    // YCloud-Signature: t=timestamp,s=signature
    if (preg_match('/t=(\d+),s=([a-f0-9]+)/', $signatureHeader, $matches)) {
        $timestamp = $matches[1];
        $signature = $matches[2];
        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawBody, YCLOUD_WEBHOOK_SECRET);
        if (!hash_equals($expectedSignature, $signature)) {
            http_response_code(401);
            error_log("Webhook signature mismatch");
            exit;
        }
    } else {
        http_response_code(401);
        error_log("Missing signature header");
        exit;
    }
}

// Responder HTTP 200 inmediatamente a YCloud
http_response_code(200);
echo json_encode(["status" => "received"]);

// Procesar el mensaje en segundo plano (después de cerrar la conexión)
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Solo procesar si el evento es de mensaje recibido
if (($data['type'] ?? '') !== 'whatsapp.inbound_message.received') {
    exit;
}

$inbound = $data['whatsappInboundMessage'] ?? null;
if (!$inbound) {
    exit;
}

$celular = $inbound['from'] ?? '';
$msgId   = $inbound['id'] ?? '';
$msgType = $inbound['type'] ?? 'text';
$userName = $inbound['customer']['name'] ?? 'Participante';

if (empty($celular)) {
    exit;
}

// Inicializar el servicio de YCloud
$wa = new YCloudService();

try {
    // Buscar o crear usuario en la BD
    $usuario = DB::selectOne("SELECT * FROM tblUsuario WHERE Celular = ?", [$celular]);

    if (!$usuario) {
        // Crear usuario nuevo
        DB::execute("INSERT INTO tblUsuario (Celular, Nombre, PasoBot) VALUES (?, ?, 'BIENVENIDA')", [$celular, $userName]);
        $userId = DB::lastInsertId();
        $usuario = [
            'idUsuario' => $userId,
            'Celular' => $celular,
            'Nombre' => $userName,
            'PasoBot' => 'BIENVENIDA',
            'TerminosAceptados' => 0,
            'CodigoParticipacion' => null
        ];
    }

    // Permitir reiniciar el bot si el usuario escribe 'hola'
    $bodyText = strtolower(trim($inbound['text']['body'] ?? ''));
    if ($bodyText === 'hola') {
        DB::execute("UPDATE tblUsuario SET PasoBot = 'BIENVENIDA' WHERE idUsuario = ?", [$usuario['idUsuario']]);
        $usuario['PasoBot'] = 'BIENVENIDA';
    }

    $pasoActual = $usuario['PasoBot'];

    // Lógica del Bot Conversacional
    if ($pasoActual === 'BIENVENIDA') {
        // Mensaje de bienvenida + Términos y Condiciones
        $body = "¡Hola! " . ($usuario['Nombre'] ?: 'participante') . " 👋 Bienvenido a la promoción *Clásicos de la Fe* 🔥\n\n"
              . "¡Llevarte una recarga de *Tiempo Aire nunca fue tan fácil*! 📱\n"
              . "Para participar necesitas tener *3 cajas de Cerillos Clásicos La Fe.*\n\n"
              . "Antes de continuar, por favor lee nuestros *Términos y Condiciones*:\n"
              . "📄 https://clasicosdelafe.qrewards.com.mx/terminos-y-condiciones.pdf\n\n"
              . "¿Aceptas los términos y condiciones?";

        $buttons = [
            ['id' => 'tyc_si', 'title' => 'Aceptar (SÍ)'],
            ['id' => 'tyc_no', 'title' => 'Rechazar (NO)']
        ];

        $wa->sendButtons($celular, $body, $buttons, "Clásicos de la Fe");
        DB::execute("UPDATE tblUsuario SET PasoBot = 'TERMINOS' WHERE idUsuario = ?", [$usuario['idUsuario']]);
    } 
    elseif ($pasoActual === 'TERMINOS') {
        $userResponse = '';
        if ($msgType === 'interactive') {
            $userResponse = $inbound['interactive']['button_reply']['id'] ?? '';
        } else {
            $textBody = strtolower(trim($inbound['text']['body'] ?? ''));
            if ($textBody === 'si' || $textBody === 'sí' || $textBody === 'aceptar') $userResponse = 'tyc_si';
            if ($textBody === 'no' || $textBody === 'rechazar') $userResponse = 'tyc_no';
        }

        if ($userResponse === 'tyc_si') {
            // Aceptó Términos
            DB::execute("UPDATE tblUsuario SET TerminosAceptados = 1 WHERE idUsuario = ?", [$usuario['idUsuario']]);

            // Enviar video tutorial nativo por YCloud
            $videoUrl = "https://clasicosdelafe.qrewards.com.mx/assets/video.mp4";
            $wa->sendVideo($celular, $videoUrl, "Video tutorial — Clásicos de la Fe");

            $body = "¡Perfecto, gracias por aceptar! 🙌\n"
                  . "Antes de registrarte, mira el breve video que te acabamos de enviar con todo lo que necesitas hacer 👆\n\n"
                  . "En el video verás:\n"
                  . "📌 Cómo localizar el *código QR* dentro del empaque\n"
                  . "📌 Cómo *marcar tus 3 cajetillas* con tu código único\n"
                  . "📌 Cómo *tomar y enviar la foto* correctamente\n\n"
                  . "Cuando termines, presiona el botón:";

            $buttons = [
                ['id' => 'video_continuar', 'title' => 'CONTINUAR']
            ];

            $wa->sendButtons($celular, $body, $buttons, "Instrucciones");
            DB::execute("UPDATE tblUsuario SET PasoBot = 'VIDEO' WHERE idUsuario = ?", [$usuario['idUsuario']]);
        } 
        elseif ($userResponse === 'tyc_no') {
            // Rechazó Términos
            $body = "Entendemos tu decisión. 😊\n"
                  . "Si cambias de opinión puedes volver a escribirnos cuando quieras.\n"
                  . "¡Hasta pronto! 👋";
            $wa->sendText($celular, $body);
            DB::execute("UPDATE tblUsuario SET PasoBot = 'BIENVENIDA' WHERE idUsuario = ?", [$usuario['idUsuario']]);
        } 
        else {
            // Respuesta no válida en este paso, reenviar botones
            $body = "¿Aceptas los Términos y Condiciones para participar?\n"
                  . "📄 https://clasicosdelafe.qrewards.com.mx/terminos-y-condiciones.pdf";
            $buttons = [
                ['id' => 'tyc_si', 'title' => 'Aceptar (SÍ)'],
                ['id' => 'tyc_no', 'title' => 'Rechazar (NO)']
            ];
            $wa->sendButtons($celular, $body, $buttons, "Clásicos de la Fe");
        }
    } 
    elseif ($pasoActual === 'VIDEO') {
        $userResponse = '';
        if ($msgType === 'interactive') {
            $userResponse = $inbound['interactive']['button_reply']['id'] ?? '';
        } else {
            $textBody = strtolower(trim($inbound['text']['body'] ?? ''));
            if ($textBody === 'continuar') $userResponse = 'video_continuar';
        }

        if ($userResponse === 'video_continuar') {
            // Generar código único alfanumérico
            $codigoUnico = generateUniqueCode();
            DB::execute("UPDATE tblUsuario SET CodigoParticipacion = ? WHERE idUsuario = ?", [$codigoUnico, $usuario['idUsuario']]);

            $body = "¡Vamos allá! 🚀\n"
                  . "Tu código único de participación es:\n"
                  . "🔐 *{$codigoUnico}*\n\n"
                  . "Ahora sigue estos pasos:\n"
                  . "① Anota este código en cada una de tus *3 cajetillas* de forma visible\n"
                  . "② Coloca las *3 cajas juntas* donde se lea claramente el código en cada una\n"
                  . "③ Toma una foto y *envíala aquí* 📸";

            $wa->sendText($celular, $body);
            DB::execute("UPDATE tblUsuario SET PasoBot = 'FOTO_PENDIENTE' WHERE idUsuario = ?", [$usuario['idUsuario']]);
        } 
        else {
            // Reenviar botón Continuar
            $body = "Cuando termines de ver el video tutorial, por favor presiona el botón CONTINUAR.";
            $buttons = [
                ['id' => 'video_continuar', 'title' => 'CONTINUAR']
            ];
            $wa->sendButtons($celular, $body, $buttons, "Instrucciones");
        }
    } 
    elseif ($pasoActual === 'FOTO_PENDIENTE') {
        if ($msgType === 'image') {
            $imageInfo = $inbound['image'] ?? null;
            $mediaId = $imageInfo['id'] ?? '';
            
            if (!empty($mediaId)) {
                $ext = 'jpg';
                if (($imageInfo['mime_type'] ?? '') === 'image/png') {
                    $ext = 'png';
                }
                
                // Nombre único del archivo local
                $filename = "cerrillera_" . $usuario['idUsuario'] . "_" . time() . "." . $ext;
                $savedFile = $wa->downloadMedia($mediaId, $filename);

                if ($savedFile) {
                    // Generar token único para el registro
                    $tokenCanje = hash('sha256', $celular . time() . uniqid());

                    // Guardar registro temporal con Estatus = 0 (incompleto, esperando telefonía)
                    DB::execute(
                        "INSERT INTO tblRegistro (idUsuario, Token, FotoCajas, CodigoUnico, Estatus) VALUES (?, ?, ?, ?, 0)",
                        [$usuario['idUsuario'], $tokenCanje, $savedFile, $usuario['CodigoParticipacion']]
                    );

                    // Enviar lista interactiva de compañías telefónicas
                    $bodyList = "✅ ¡Foto recibida!\n\nPor favor, selecciona tu compañía telefónica para poder procesar tu recarga de Tiempo Aire cuando tu registro sea validado:";
                    $rowsList = [
                        ['id' => 'tel_1', 'title' => 'Telcel'],
                        ['id' => 'tel_2', 'title' => 'Movistar'],
                        ['id' => 'tel_3', 'title' => 'AT&T'],
                        ['id' => 'tel_4', 'title' => 'Bait'],
                        ['id' => 'tel_6', 'title' => 'Unefon'],
                        ['id' => 'tel_5', 'title' => 'Virgin Mobile']
                    ];

                    $wa->sendList($celular, $bodyList, "Ver Compañías", $rowsList, "Compañía Telefónica");
                    DB::execute("UPDATE tblUsuario SET PasoBot = 'SELECCION_TELEFONIA' WHERE idUsuario = ?", [$usuario['idUsuario']]);
                } else {
                    $wa->sendText($celular, "Hubo un error al procesar tu imagen. Por favor, intenta enviarla nuevamente. 📸");
                }
            } else {
                $wa->sendText($celular, "No pudimos obtener la imagen. Por favor, intenta de nuevo. 📸");
            }
        } else {
            // No envió imagen
            $codigoUnico = $usuario['CodigoParticipacion'];
            $body = "Por favor, envía la foto de tus *3 cajetillas de Cerillos Clásicos La Fe* marcadas claramente con tu código único: *{$codigoUnico}* 📸";
            $wa->sendText($celular, $body);
        }
    } 
    elseif ($pasoActual === 'SELECCION_TELEFONIA') {
        $selectedId = '';
        if ($msgType === 'interactive') {
            $selectedId = $inbound['interactive']['list_reply']['id'] ?? '';
        }

        if (strpos($selectedId, 'tel_') === 0) {
            $idTelefonia = (int)str_replace('tel_', '', $selectedId);
            
            // Buscar el último registro incompleto del usuario para actualizarlo
            $ultimoRegistro = DB::selectOne(
                "SELECT idRegistro FROM tblRegistro WHERE idUsuario = ? AND Estatus = 0 ORDER BY FechaRegistro DESC LIMIT 1",
                [$usuario['idUsuario']]
            );

            if ($ultimoRegistro) {
                // Actualizar registro con la telefonía (mantiene Estatus = 0 temporalmente)
                DB::execute(
                    "UPDATE tblRegistro SET idTelefonia = ? WHERE idRegistro = ?",
                    [$idTelefonia, $ultimoRegistro['idRegistro']]
                );

                $body = "¡Excelente elección! 📱\n\n"
                      . "Ahora, por favor escribe el *número celular a 10 dígitos* al cual deseas que le realicemos la recarga telefónica:";

                $wa->sendText($celular, $body);
                DB::execute("UPDATE tblUsuario SET PasoBot = 'INGRESO_TELEFONO' WHERE idUsuario = ?", [$usuario['idUsuario']]);
            } else {
                // Si no hay registro temporal, mandar a FOTO_PENDIENTE
                $wa->sendText($celular, "Ocurrió un inconveniente. Por favor, envía la foto nuevamente. 📸");
                DB::execute("UPDATE tblUsuario SET PasoBot = 'FOTO_PENDIENTE' WHERE idUsuario = ?", [$usuario['idUsuario']]);
            }
        } else {
            // No seleccionó de la lista, reenviar la lista
            $bodyList = "Por favor, utiliza el botón de abajo para seleccionar tu compañía telefónica. Esto es necesario para poder recargar tu celular:";
            $rowsList = [
                ['id' => 'tel_1', 'title' => 'Telcel'],
                ['id' => 'tel_2', 'title' => 'Movistar'],
                ['id' => 'tel_3', 'title' => 'AT&T'],
                ['id' => 'tel_4', 'title' => 'Bait'],
                ['id' => 'tel_6', 'title' => 'Unefon'],
                ['id' => 'tel_5', 'title' => 'Virgin Mobile']
            ];
            $wa->sendList($celular, $bodyList, "Ver Compañías", $rowsList, "Compañía Telefónica");
        }
    }
    elseif ($pasoActual === 'INGRESO_TELEFONO') {
        $textBody = preg_replace('/\D/', '', $inbound['text']['body'] ?? '');

        if (strlen($textBody) === 10) {
            // Buscar el último registro incompleto del usuario
            $ultimoRegistro = DB::selectOne(
                "SELECT idRegistro FROM tblRegistro WHERE idUsuario = ? AND Estatus = 0 ORDER BY FechaRegistro DESC LIMIT 1",
                [$usuario['idUsuario']]
            );

            if ($ultimoRegistro) {
                // Actualizar registro con el teléfono y pasar a Estatus = 1 (Pendiente validación)
                DB::execute(
                    "UPDATE tblRegistro SET TelefonoRecarga = ?, Estatus = 1 WHERE idRegistro = ?",
                    [$textBody, $ultimoRegistro['idRegistro']]
                );

                $body = "¡Perfecto! Hemos registrado tu compañía telefónica y el número *{$textBody}* para tu recarga. 📱\n\n"
                      . "Tu registro pasará a validación... 🔍\n"
                      . "En un periodo máximo de 48hrs hábiles te daremos respuesta en este mismo chat.\n"
                      . "¡Gracias por tu paciencia! 🙏";

                $wa->sendText($celular, $body);
                DB::execute("UPDATE tblUsuario SET PasoBot = 'COMPLETADO' WHERE idUsuario = ?", [$usuario['idUsuario']]);
            } else {
                $wa->sendText($celular, "Ocurrió un inconveniente. Por favor, envía la foto nuevamente. 📸");
                DB::execute("UPDATE tblUsuario SET PasoBot = 'FOTO_PENDIENTE' WHERE idUsuario = ?", [$usuario['idUsuario']]);
            }
        } else {
            $body = "El número ingresado no es válido. ❌\n\n"
                  . "Por favor, escribe el *número celular a 10 dígitos* (solo números, ej: 5512345678) para tu recarga:";
            $wa->sendText($celular, $body);
        }
    }
    elseif ($pasoActual === 'COMPLETADO') {
        // El usuario ya completó el registro
        $body = "Tu registro está en proceso de validación. 🔍 En un lapso máximo de 48hrs hábiles te daremos respuesta aquí mismo. ¡Gracias por participar! 🙏";
        $wa->sendText($celular, $body);
    }

} catch (Exception $e) {
    error_log("Webhook Error: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString());
}

/**
 * Genera un código único alfanumérico de 6 dígitos que omite
 * los caracteres confusos solicitados: 1, I, O, 0, 2, Z, 6, G, l, L.
 */
function generateUniqueCode(): string
{
    $chars = 'ABCDEFHJKMNPQRSTUVWXY345789';
    $len = 6;
    do {
        $code = '';
        for ($i = 0; $i < $len; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        // Validar unicidad
        $exists = DB::selectOne("SELECT 1 FROM tblUsuario WHERE CodigoParticipacion = ?", [$code]);
    } while ($exists);
    
    return $code;
}
