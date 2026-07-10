<?php
// webhook.php — Procesador de Webhook de YCloud para WhatsApp (Gatorade G15K)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ycloud.php';

// Validar método GET (Verificación opcional para webhooks si se requiere)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['hub_challenge'])) {
        echo $_GET['hub_challenge'];
        exit;
    }
    echo "Webhook activo";
    exit;
}

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

// Leer cuerpo de la petición
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

error_log("YCloud Webhook Raw Body: " . $rawBody);

if (!$data) {
    http_response_code(400);
    echo "JSON inválido";
    exit;
}

// Validar firma del webhook de YCloud si está configurada
if (defined('YCLOUD_WEBHOOK_SECRET') && !empty(YCLOUD_WEBHOOK_SECRET)) {
    $signatureHeader = $_SERVER['HTTP_X_YCLOUD_SIGNATURE'] ?? '';
    if (preg_match('/t=(\d+),v1=([a-f0-9]+)/', $signatureHeader, $matches)) {
        $timestamp = $matches[1];
        $signature = $matches[2];
        
        // Validar ventana de tiempo (5 minutos)
        if (abs(time() - $timestamp) < 300) {
            $signedPayload = $timestamp . '.' . $rawBody;
            $expectedSignature = hash_hmac('sha256', $signedPayload, YCLOUD_WEBHOOK_SECRET);
            if (!hash_equals($expectedSignature, $signature)) {
                http_response_code(401);
                error_log("YCloud webhook signature mismatch");
                exit;
            }
        } else {
            http_response_code(401);
            error_log("YCloud webhook timestamp too old");
            exit;
        }
    }
}

// Si viene dentro de un evento de YCloud, extraer el objeto 'whatsapp'
if (isset($data['whatsapp'])) {
    $data = $data['whatsapp'];
}

// Responder HTTP 200 inmediatamente
http_response_code(200);
echo json_encode(["status" => "received"]);

// Procesar en segundo plano si está disponible
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ── Parsear formato nativo de YCloud ──────────────────────────────────────
// YCloud envía: { "type": "whatsapp.inbound_message.received", "whatsappInboundMessage": { ... } }
$inbound = null;

$eventType = $data['type'] ?? '';

if ($eventType === 'whatsapp.inbound_message.received' && isset($data['whatsappInboundMessage'])) {
    $msg = $data['whatsappInboundMessage'];

    $celular  = ltrim($msg['from'] ?? '', '+');   // quitar el + si lo trae
    $msgId    = $msg['id'] ?? '';
    $msgType  = $msg['type'] ?? 'text';
    $userName = $msg['customerProfile']['name'] ?? 'Participante';

    $inbound = [
        'from'            => $celular,
        'id'              => $msgId,
        'type'            => $msgType,
        'customerProfile' => ['name' => $userName],
    ];

    if ($msgType === 'text') {
        $inbound['text'] = ['body' => $msg['text']['body'] ?? ''];
    } elseif ($msgType === 'interactive') {
        $interactiveType = $msg['interactive']['type'] ?? '';
        if ($interactiveType === 'button_reply') {
            $inbound['interactive'] = [
                'button_reply' => [
                    'id'    => $msg['interactive']['button_reply']['id']    ?? '',
                    'title' => $msg['interactive']['button_reply']['title'] ?? ''
                ]
            ];
        }
    } elseif ($msgType === 'image') {
        $inbound['image'] = [
            'id'        => $msg['image']['id']        ?? '',
            'mime_type' => $msg['image']['mimeType']  ?? 'image/jpeg',
            'caption'   => $msg['image']['caption']   ?? '',
            'link'      => $msg['image']['link']      ?? ''
        ];
    }
}

// ── Fallback: formato Meta (whatsapp_business_account) ────────────────────
if (!$inbound && isset($data['object']) && $data['object'] === 'whatsapp_business_account') {
    $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
    $contact = $data['entry'][0]['changes'][0]['value']['contacts'][0] ?? null;

    if ($message) {
        $celular  = $message['from'] ?? '';
        $msgId    = $message['id']   ?? '';
        $msgType  = $message['type'] ?? 'text';
        $userName = $contact['profile']['name'] ?? 'Participante';

        $inbound = [
            'from'            => $celular,
            'id'              => $msgId,
            'type'            => $msgType,
            'customerProfile' => ['name' => $userName],
        ];

        if ($msgType === 'text') {
            $inbound['text'] = ['body' => $message['text']['body'] ?? ''];
        } elseif ($msgType === 'interactive') {
            $interactiveType = $message['interactive']['type'] ?? '';
            if ($interactiveType === 'button_reply') {
                $inbound['interactive'] = [
                    'button_reply' => [
                        'id'    => $message['interactive']['button_reply']['id']    ?? '',
                        'title' => $message['interactive']['button_reply']['title'] ?? ''
                    ]
                ];
            }
        } elseif ($msgType === 'image') {
            $inbound['image'] = [
                'id'        => $message['image']['id']        ?? '',
                'mime_type' => $message['image']['mime_type'] ?? 'image/jpeg',
                'caption'   => $message['image']['caption']   ?? '',
                'link'      => $message['image']['link']      ?? ''
            ];
        }
    }
}

$msgId   = $inbound['id'] ?? '';
$msgType = $inbound['type'] ?? 'text';
$userName = $inbound['customerProfile']['name'] ?? 'Participante';

if (empty($celular)) {
    exit;
}

if (!empty($msgId)) {
    // Evitar procesamiento paralelo/reintentos
    $existe = DB::selectOne("SELECT 1 FROM tblLog WHERE Accion = 'WEBHOOK_PROCESSED' AND Descripcion = ?", [$msgId]);
    if ($existe) {
        error_log("Webhook: message {$msgId} already processed, skipping retry.");
        exit;
    }
    DB::execute("INSERT INTO tblLog (Accion, Descripcion) VALUES ('WEBHOOK_PROCESSED', ?)", [$msgId]);
}

$wa = new YCloudService();

try {
    // Buscar o crear usuario
    $usuario = DB::selectOne("SELECT * FROM tblUsuario WHERE Celular = ?", [$celular]);

    if (!$usuario) {
        DB::execute("INSERT INTO tblUsuario (Celular, Nombre, PasoBot) VALUES (?, ?, 'BIENVENIDA')", [$celular, $userName]);
        $userId = DB::lastInsertId();
        $usuario = [
            'idUsuario' => $userId,
            'Celular' => $celular,
            'Nombre' => $userName,
            'PasoBot' => 'BIENVENIDA',
            'TerminosAceptados' => 0
        ];
    }

    $bodyText = strtolower(trim($inbound['text']['body'] ?? ''));

    // Reiniciar bot si el usuario escribe 'hola'
    if ($bodyText === 'hola') {
        DB::execute("UPDATE tblUsuario SET PasoBot = 'BIENVENIDA' WHERE idUsuario = ?", [$usuario['idUsuario']]);
        $usuario['PasoBot'] = 'BIENVENIDA';
    }

    $pasoActual = $usuario['PasoBot'];

    if ($pasoActual === 'BIENVENIDA') {
        // Enviar Imagen de Bienvenida
        $imageUrl = APP_URL . '/bienvenida.jpeg';
        $wa->sendImage($celular, $imageUrl);
        sleep(1);

        // Mensaje 1: Bienvenida (siempre se envía)
        $wa->sendText($celular,
            "🏆 ¡Bienvenido(a) a la promoción *G15K* de *Gatorade®*!\n\n"
          . "Participa comprando *\$95.00 MXN* o más en productos *Gatorade®* participantes y registra tu ticket para formar parte de esta promoción.\n\n"
          . "Para continuar, sigue las instrucciones que te compartiremos a continuación."
        );
        sleep(1);

        // Verificar si ya tiene datos completos
        $datosCompletos = !empty($usuario['Nombre']) && !empty($usuario['Email']) && !empty($usuario['Estado']);

        if ($datosCompletos) {
            // Ir directo a foto
            DB::execute("UPDATE tblUsuario SET PasoBot = 'FOTO_PENDIENTE' WHERE idUsuario = ?", [$usuario['idUsuario']]);
            $body = "¡Ahora envíanos una fotografía clara y legible de tu ticket de compra completo!\n\n"
                  . "📸 *Recomendaciones:*\n"
                  . "• Asegúrate de que el ticket se vea completo.\n"
                  . "• La fecha, hora, tienda y productos participantes deben ser visibles.\n"
                  . "• Evita reflejos, sombras o imágenes borrosas.\n\n"
                  . "Adjunta la fotografía de tu ticket para continuar.";
            $wa->sendText($celular, $body);
        } else {
            // Enviar T&C con botones
            $body = "Antes de continuar, es necesario que aceptes nuestras Bases, Términos y Condiciones, así como el Aviso de Privacidad.\n\n"
                  . "📄 Bases y Términos: https://g15k.qrewards.com.mx/bases\n"
                  . "🔒 Aviso de Privacidad: https://g15k.qrewards.com.mx/privacidad\n\n"
                  . "¿Aceptas los Bases, Términos y Condiciones, así como el Aviso de Privacidad de la promoción?";
            $buttons = [
                ['id' => 'tyc_si', 'title' => 'Sí, acepto'],
                ['id' => 'tyc_no', 'title' => 'No acepto']
            ];
            $wa->sendButtons($celular, $body, $buttons);
            DB::execute("UPDATE tblUsuario SET PasoBot = 'TERMINOS' WHERE idUsuario = ?", [$usuario['idUsuario']]);
        }


    } 
    elseif ($pasoActual === 'TERMINOS') {
        $userResponse = '';
        if ($msgType === 'interactive') {
            $userResponse = $inbound['interactive']['button_reply']['id'] ?? '';
        } else {
            if ($bodyText === 'si' || $bodyText === 'sí' || $bodyText === 'aceptar' || $bodyText === '1') $userResponse = 'tyc_si';
            if ($bodyText === 'no' || $bodyText === 'rechazar' || $bodyText === '2') $userResponse = 'tyc_no';
        }

        if ($userResponse === 'tyc_si') {
            DB::execute("UPDATE tblUsuario SET TerminosAceptados = 1, PasoBot = 'INGRESO_NOMBRE' WHERE idUsuario = ?", [$usuario['idUsuario']]);
            $body = "Perfecto. Para comenzar tu registro, por favor comparte tu *nombre completo* tal como aparece en tu identificación oficial:";
            $wa->sendText($celular, $body);
        } 
        elseif ($userResponse === 'tyc_no') {
            $body = "Entendemos tu decisión. 😊\n"
                  . "Si cambias de opinión, puedes volver a escribirnos *Hola* en cualquier momento.\n"
                  . "¡Hasta pronto! 👋";
            $wa->sendText($celular, $body);
            DB::execute("UPDATE tblUsuario SET PasoBot = 'BIENVENIDA' WHERE idUsuario = ?", [$usuario['idUsuario']]);
        } 
        else {
            $body = "¿Aceptas los Bases, Términos y Condiciones, así como el Aviso de Privacidad de la promoción?\n"
                  . "📄 https://g15k.qrewards.com.mx/bases";
            $buttons = [
                ['id' => 'tyc_si', 'title' => 'Sí, acepto'],
                ['id' => 'tyc_no', 'title' => 'No acepto']
            ];
            $wa->sendButtons($celular, $body, $buttons);
        }
    } 
    elseif ($pasoActual === 'INGRESO_NOMBRE') {
        $nameInput = trim($inbound['text']['body'] ?? '');
        if (!empty($nameInput) && strlen($nameInput) > 3) {
            DB::execute("UPDATE tblUsuario SET TempNombre = ?, PasoBot = 'INGRESO_EMAIL' WHERE idUsuario = ?", [$nameInput, $usuario['idUsuario']]);
            $body = "Gracias, por favor comparte tu *correo electrónico*:";
            $wa->sendText($celular, $body);
        } else {
            $body = "Por favor, escribe tu nombre completo tal como aparece en tu identificación oficial:";
            $wa->sendText($celular, $body);
        }
    }
    elseif ($pasoActual === 'INGRESO_EMAIL') {
        $emailInput = trim($inbound['text']['body'] ?? '');
        if (filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            DB::execute("UPDATE tblUsuario SET TempEmail = ?, PasoBot = 'INGRESO_ESTADO' WHERE idUsuario = ?", [$emailInput, $usuario['idUsuario']]);
            
            $body = "Por último, indícanos en donde resides:\n"
                  . "Ciudad de México o Estado de México\n"
                  . "Otro estado";
            $buttons = [
                ['id' => 'res_1', 'title' => 'CDMX / EDOMEX'],
                ['id' => 'res_2', 'title' => 'Otro estado']
            ];
            $wa->sendButtons($celular, $body, $buttons);
        } else {
            $body = "El correo electrónico ingresado no es válido. ❌\nPor favor, comparte un correo electrónico válido (ejemplo: usuario@correo.com):";
            $wa->sendText($celular, $body);
        }
    }
    elseif ($pasoActual === 'INGRESO_ESTADO') {
        $userResponse = '';
        if ($msgType === 'interactive') {
            $userResponse = $inbound['interactive']['button_reply']['id'] ?? '';
        } else {
            if ($bodyText === '1' || strpos($bodyText, 'ciudad') !== false || strpos($bodyText, 'méxico') !== false || strpos($bodyText, 'mexico') !== false) $userResponse = 'res_1';
            if ($bodyText === '2' || strpos($bodyText, 'otro') !== false || strpos($bodyText, 'estado') !== false) $userResponse = 'res_2';
        }

        if ($userResponse === 'res_1' || $userResponse === 'res_2') {
            $estadoStr = ($userResponse === 'res_1') ? "Ciudad de México / Estado de México" : "Otro estado";
            DB::execute("UPDATE tblUsuario SET TempEstado = ?, PasoBot = 'CONFIRMACION_DATOS' WHERE idUsuario = ?", [$estadoStr, $usuario['idUsuario']]);

            // Obtener datos temporales para mostrar
            $usrTemp = DB::selectOne("SELECT TempNombre, TempEmail, TempEstado FROM tblUsuario WHERE idUsuario = ?", [$usuario['idUsuario']]);
            
            $body = "Por favor verifica que tus datos sean correctos:\n\n"
                  . "👤 *Nombre:* {$usrTemp['TempNombre']}\n"
                  . "📱 *Teléfono:* {$celular}\n"
                  . "📧 *Correo:* {$usrTemp['TempEmail']}\n"
                  . "📍 *Estado:* {$usrTemp['TempEstado']}\n\n"
                  . "¿La información es correcta?";

            $buttons = [
                ['id' => 'confirm_si', 'title' => 'Sí, es correcta'],
                ['id' => 'confirm_no', 'title' => 'No, deseo corregirla']
            ];
            $wa->sendButtons($celular, $body, $buttons);
        } else {
            $body = "Por favor, indícanos en donde resides utilizando los botones:\n"
                  . "1️⃣ Ciudad de México o Estado de México\n"
                  . "2️⃣ Otro estado";
            $buttons = [
                ['id' => 'res_1', 'title' => 'CDMX / EDOMEX'],
                ['id' => 'res_2', 'title' => 'Otro estado']
            ];
            $wa->sendButtons($celular, $body, $buttons);
        }
    }
    elseif ($pasoActual === 'CONFIRMACION_DATOS') {
        $userResponse = '';
        if ($msgType === 'interactive') {
            $userResponse = $inbound['interactive']['button_reply']['id'] ?? '';
        } else {
            if ($bodyText === 'si' || $bodyText === 'sí' || $bodyText === 'correcta' || $bodyText === '1') $userResponse = 'confirm_si';
            if ($bodyText === 'no' || $bodyText === 'corregir' || $bodyText === '2') $userResponse = 'confirm_no';
        }

        if ($userResponse === 'confirm_si') {
            // Confirmar y copiar datos temporales a definitivos
            $usrTemp = DB::selectOne("SELECT TempNombre, TempEmail, TempEstado FROM tblUsuario WHERE idUsuario = ?", [$usuario['idUsuario']]);
            DB::execute(
                "UPDATE tblUsuario SET Nombre = ?, Email = ?, Estado = ?, PasoBot = 'FOTO_PENDIENTE' WHERE idUsuario = ?",
                [$usrTemp['TempNombre'], $usrTemp['TempEmail'], $usrTemp['TempEstado'], $usuario['idUsuario']]
            );

            $body = "¡Perfecto! Ahora envíanos una fotografía clara y legible de tu ticket de compra completo.\n\n"
                  . "📸 *Recomendaciones:*\n"
                  . "• Asegúrate de que el ticket se vea completo.\n"
                  . "• La fecha, hora, tienda y productos participantes deben ser visibles.\n"
                  . "• Evita reflejos, sombras o imágenes borrosas.\n\n"
                  . "Adjunta la fotografía de tu ticket para continuar.";
            $wa->sendText($celular, $body);
        }
        elseif ($userResponse === 'confirm_no') {
            DB::execute("UPDATE tblUsuario SET PasoBot = 'INGRESO_NOMBRE' WHERE idUsuario = ?", [$usuario['idUsuario']]);
            $body = "Entendido. Vamos a corregir tus datos.\n\nPor favor comparte tu *nombre completo* tal como aparece en tu identificación oficial:";
            $wa->sendText($celular, $body);
        }
        else {
            $usrTemp = DB::selectOne("SELECT TempNombre, TempEmail, TempEstado FROM tblUsuario WHERE idUsuario = ?", [$usuario['idUsuario']]);
            $body = "Por favor verifica que tus datos sean correctos:\n\n"
                  . "👤 *Nombre:* {$usrTemp['TempNombre']}\n"
                  . "📱 *Teléfono:* {$celular}\n"
                  . "📧 *Correo:* {$usrTemp['TempEmail']}\n"
                  . "📍 *Estado:* {$usrTemp['TempEstado']}\n\n"
                  . "¿La información es correcta?";
            $buttons = [
                ['id' => 'confirm_si', 'title' => 'Sí, es correcta'],
                ['id' => 'confirm_no', 'title' => 'No, deseo corregirla']
            ];
            $wa->sendButtons($celular, $body, $buttons);
        }
    }
    elseif ($pasoActual === 'FOTO_PENDIENTE') {
        if ($msgType === 'image') {
            $imageInfo = $inbound['image'] ?? null;
            $mediaId = $imageInfo['id'] ?? '';
            $mediaSource = !empty($imageInfo['link']) ? $imageInfo['link'] : $mediaId;
            
            if (!empty($mediaSource)) {
                $ext = 'jpg';
                if (($imageInfo['mime_type'] ?? '') === 'image/png') {
                    $ext = 'png';
                }
                
                $filename = "ticket_g15k_" . $usuario['idUsuario'] . "_" . time() . "." . $ext;
                $savedFile = $wa->downloadMedia($mediaSource, $filename);

                if ($savedFile) {
                    $tokenCanje = hash('sha256', $celular . time() . uniqid());
                    // Insertar en tblRegistro
                    DB::execute(
                        "INSERT INTO tblRegistro (idUsuario, Token, FotoCajas, Estatus) VALUES (?, ?, ?, 1)",
                        [$usuario['idUsuario'], $tokenCanje, $savedFile]
                    );

                    $body = "✅ ¡Tu ticket fue registrado!\n\n"
                          . "Hemos recibido correctamente tu información y tu ticket de compra. Nuestro equipo realizará la validación correspondiente.\n\n"
                          . "Te recomendamos conservar tu ticket original hasta la conclusión de la promoción.\n"
                          . "¡Gracias por participar en la promoción *G15K* de *Gatorade®*!\n\n"
                          . "Si tienes más compras por registrar escribe la palabra *Hola* y sigue nuevamente los pasos del Bot.";
                    $wa->sendText($celular, $body);

                    DB::execute("UPDATE tblUsuario SET PasoBot = 'COMPLETADO' WHERE idUsuario = ?", [$usuario['idUsuario']]);


                } else {
                    $wa->sendText($celular, "Hubo un error al procesar tu imagen. Por favor, intenta enviarla nuevamente. 📸");
                }
            } else {
                $wa->sendText($celular, "No pudimos obtener la imagen. Por favor, intenta de nuevo. 📸");
            }
        } else {
            $body = "Por favor, envía una fotografía clara y legible de tu ticket de compra completo para continuar. 📸";
            $wa->sendText($celular, $body);
        }
    }
    elseif ($pasoActual === 'COMPLETADO') {
        $body = "Tu ticket de compra está en proceso de validación. 🔍\n\n"
              . "En caso de resultar ganador nos pondremos en contacto contigo.\n\n"
              . "Si tienes más compras por registrar escribe la palabra *Hola* y sigue nuevamente los pasos del Bot. ¡Gracias por participar! 🏆";
        $wa->sendText($celular, $body);
    }
    else {
        // Fallback global: estado desconocido o mensaje fuera de contexto
        $wa->sendText($celular,
            "No entendimos tu mensaje. 😊\n\n"
          . "Escribe *Hola* para comenzar o continuar con tu registro en la promoción *G15K de Gatorade®*."
        );
    }

} catch (Exception $e) {
    error_log("Webhook Error: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString());
}
