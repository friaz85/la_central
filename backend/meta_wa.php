<?php
// meta_wa.php — Servicio para interactuar con la API de WhatsApp de 360dialog (Meta-compliant v2)
require_once __DIR__ . '/config.php';

// Valores por defecto en caso de que aún no estén en config.php
if (!defined('META_ACCESS_TOKEN')) {
    define('META_ACCESS_TOKEN', '3I4O3QydIWXXH14JVtlwRlKqAK'); // Token de 360dialog provisto
}
if (!defined('META_TEMPLATE_APROBACION')) {
    define('META_TEMPLATE_APROBACION', 'registro_aprobado');
}
if (!defined('META_TEMPLATE_RECHAZO')) {
    define('META_TEMPLATE_RECHAZO', 'registro_rechazado');
}

class MetaWAService
{
    private string $accessToken;
    private string $apiUrl;

    public function __construct()
    {
        $this->accessToken = META_ACCESS_TOKEN;
        $this->apiUrl = "https://waba-v2.360dialog.io/messages";
    }

    /**
     * Envía un mensaje de texto simple.
     */
    public function sendText(string $to, string $body): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'text',
            'text' => [
                'body' => $body
            ]
        ];
        return $this->request($payload);
    }

    /**
     * Envía un mensaje interactivo con hasta 3 botones de respuesta rápida.
     */
    public function sendButtons(string $to, string $bodyText, array $buttons, ?string $headerText = null, ?string $footerText = null): array
    {
        $interactive = [
            'type' => 'button',
            'body' => [
                'text' => $bodyText
            ],
            'action' => [
                'buttons' => []
            ]
        ];

        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => $headerText
            ];
        }

        if ($footerText) {
            $interactive['footer'] = [
                'text' => $footerText
            ];
        }

        foreach ($buttons as $btn) {
            $interactive['action']['buttons'][] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $btn['id'],
                    'title' => $btn['title']
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'interactive',
            'interactive' => $interactive
        ];

        return $this->request($payload);
    }

    /**
     * Envía un mensaje con imagen usando URL pública.
     */
    public function sendImage(string $to, string $imageUrl, ?string $caption = null): array
    {
        $image = [
            'link' => $imageUrl
        ];

        if ($caption) {
            $image['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'image',
            'image' => $image
        ];

        return $this->request($payload);
    }

    /**
     * Envía un mensaje con video usando URL pública.
     */
    public function sendVideo(string $to, string $videoUrl, ?string $caption = null): array
    {
        $video = [
            'link' => $videoUrl
        ];

        if ($caption) {
            $video['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'video',
            'video' => $video
        ];

        return $this->request($payload);
    }

    /**
     * Envía un mensaje de plantilla pre-aprobado (WhatsApp Template Message).
     * Requerido para iniciar conversaciones o responder después de 24 horas.
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode = 'es_MX', array $bodyParams = []): array
    {
        $components = [];
        if (!empty($bodyParams)) {
            $parameters = [];
            foreach ($bodyParams as $param) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => $param
                ];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                ]
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return $this->request($payload);
    }

    /**
     * Envía un mensaje interactivo tipo lista (List Message).
     */
    public function sendList(string $to, string $bodyText, string $buttonText, array $rows, ?string $headerText = null, ?string $footerText = null): array
    {
        $formattedRows = [];
        foreach ($rows as $row) {
            $formattedRows[] = [
                'id' => $row['id'] ?? uniqid(),
                'title' => substr($row['title'] ?? '', 0, 24),
                'description' => isset($row['description']) ? substr($row['description'], 0, 72) : ''
            ];
        }

        $interactive = [
            'type' => 'list',
            'body' => [
                'text' => $bodyText
            ],
            'action' => [
                'button' => $buttonText,
                'sections' => [
                    [
                        'title' => 'Opciones',
                        'rows' => $formattedRows
                    ]
                ]
            ]
        ];

        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => $headerText
            ];
        }

        if ($footerText) {
            $interactive['footer'] = [
                'text' => $footerText
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'interactive',
            'interactive' => $interactive
        ];

        return $this->request($payload);
    }

    /**
     * Descarga un archivo de media usando su ID de 360dialog/WhatsApp y lo guarda en el servidor local.
     */
    public function downloadMedia(string $mediaSource, string $outputFilename): ?string
    {
        $fileContent = null;

        // Si es una URL directa
        if (strpos($mediaSource, 'http') === 0) {
            $downloadUrl = $mediaSource;
        } else {
            // 1. Obtener la URL de descarga temporal consultando a la API de 360dialog con el media ID
            $mediaId = $mediaSource;
            $url = "https://waba-v2.360dialog.io/{$mediaId}";
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => [
                    "D360-API-KEY: {$this->accessToken}",
                    "Accept: application/json"
                ],
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("360dialog downloadMedia: failed to fetch info for media {$mediaId}, HTTP {$httpCode}. Response: {$response}");
                return null;
            }

            $data = json_decode($response, true);
            $downloadUrl = $data['url'] ?? null;

            if (!$downloadUrl) {
                error_log("360dialog downloadMedia: URL key not found in response for media {$mediaId}");
                return null;
            }
        }

        // 2. Descargar el binario usando la URL de descarga (requiere cabecera D360-API-KEY)
        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                "D360-API-KEY: {$this->accessToken}"
            ],
        ]);
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            // Reintentar sin cabeceras (para CDNs directos de Meta pre-firmados que rechazan cabeceras personalizadas)
            $ch = curl_init($downloadUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $fileContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                error_log("360dialog downloadMedia: failed to download binary content from URL. HTTP {$httpCode}");
                return null;
            }
        }

        // Crear directorio si no existe
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0775, true);
        }

        $filePath = UPLOAD_DIR . $outputFilename;
        if (file_put_contents($filePath, $fileContent) !== false) {
            return $outputFilename;
        }

        return null;
    }

    /**
     * Envía la solicitud HTTP a la API de 360dialog.
     */
    private function request(array $payload): array
    {
        $payloadJson = json_encode($payload);
        error_log("360dialog API Request to: " . $this->apiUrl . " | Payload: " . $payloadJson);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payloadJson,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "D360-API-KEY: {$this->accessToken}"
            ],
        ]);
        
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            error_log("360dialog request curl error: {$error}");
            return ['success' => false, 'error' => $error];
        }

        error_log("360dialog API Response (HTTP {$httpCode}): " . $response);

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $errorMsg = $decoded['error']['message'] ?? 'API Error';
            return ['success' => false, 'error' => $errorMsg, 'code' => $httpCode, 'details' => $decoded];
        }

        return ['success' => true, 'data' => $decoded];
    }

    /**
     * Limpia y formatea el celular al formato internacional E.164.
     */
    private function formatPhone(string $celular): string
    {
        $num = preg_replace('/\D/', '', $celular);
        
        // Si empieza con 521, cambiarlo a 52 para WhatsApp API estándar
        if (strlen($num) === 13 && strpos($num, '521') === 0) {
            return '52' . substr($num, 3);
        }
        if (strlen($num) === 10) {
            return '52' . $num;
        }
        return $num;
    }
}

