<?php
// ycloud.php — Servicio para interactuar con la API de WhatsApp de YCloud
require_once __DIR__ . '/config.php';

class YCloudService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = YCLOUD_API_KEY;
        $this->apiUrl = YCLOUD_API_URL;
    }

    /**
     * Envía un mensaje de texto simple.
     */
    public function sendText(string $to, string $body): array
    {
        $payload = [
            'from' => YCLOUD_FROM_PHONE,
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
            'from' => YCLOUD_FROM_PHONE,
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
        $payload = [
            'from' => YCLOUD_FROM_PHONE,
            'to' => $this->formatPhone($to),
            'type' => 'image',
            'image' => [
                'link' => $imageUrl
            ]
        ];

        if ($caption) {
            $payload['image']['caption'] = $caption;
        }

        return $this->request($payload);
    }

    /**
     * Envía un mensaje con video usando URL pública.
     */
    public function sendVideo(string $to, string $videoUrl, ?string $caption = null): array
    {
        $payload = [
            'from' => YCLOUD_FROM_PHONE,
            'to' => $this->formatPhone($to),
            'type' => 'video',
            'video' => [
                'link' => $videoUrl
            ]
        ];

        if ($caption) {
            $payload['video']['caption'] = $caption;
        }

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
            'from' => YCLOUD_FROM_PHONE,
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
        $interactive = [
            'type' => 'list',
            'body' => [
                'text' => $bodyText
            ],
            'action' => [
                'button' => $buttonText,
                'sections' => [
                    [
                        'rows' => $rows
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
            'from' => YCLOUD_FROM_PHONE,
            'to' => $this->formatPhone($to),
            'type' => 'interactive',
            'interactive' => $interactive
        ];

        return $this->request($payload);
    }
    /**
     * Descarga un archivo de media usando su ID y lo guarda en el servidor local.
     */
    public function downloadMedia(string $mediaId, string $outputFilename): ?string
    {
        // 1. Obtener la información del archivo de YCloud (que nos dará la URL real)
        $url = "https://api.ycloud.com/v2/whatsapp/media/{$mediaId}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$this->apiKey}",
                "Accept: application/json"
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("YCloud downloadMedia: failed to fetch info for media {$mediaId}, HTTP {$httpCode}. Response: {$response}");
            return null;
        }

        $data = json_decode($response, true);
        
        // Si YCloud devuelve la URL temporal (estilo Meta)
        $downloadUrl = $data['url'] ?? null;
        
        // Si no devuelve una URL, pero responde binario directo (dependiendo de la implementación de YCloud v2)
        if (!$downloadUrl) {
            // Guardamos el response directo si no es JSON (es el archivo binario)
            if (isset($data['error'])) {
                error_log("YCloud downloadMedia error in JSON response: " . json_encode($data));
                return null;
            }
            $fileContent = $response;
        } else {
            // 2. Descargar el binario usando la URL temporal
            $ch = curl_init($downloadUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                // Nota: Meta requiere que no se envíe el header de YCloud o que sí se envíe
                CURLOPT_HTTPHEADER     => [
                    "Authorization: Bearer {$this->apiKey}"
                ],
            ]);
            $fileContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                // Intentar descargar sin header si falla (Meta a veces no requiere auth para el CDN directo)
                $ch = curl_init($downloadUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 30,
                ]);
                $fileContent = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200) {
                    error_log("YCloud downloadMedia: failed to download binary content from URL. HTTP {$httpCode}");
                    return null;
                }
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
     * Envía la solicitud HTTP a la API de YCloud.
     */
    private function request(array $payload): array
    {
        $payloadJson = json_encode($payload);
        error_log("YCloud API Request: " . $payloadJson);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payloadJson,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "X-API-Key: {$this->apiKey}", // YCloud acepta X-API-Key o Authorization
                "Authorization: Bearer {$this->apiKey}"
            ],
        ]);
        
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            error_log("YCloud request curl error: {$error}");
            return ['success' => false, 'error' => $error];
        }

        error_log("YCloud API Response (HTTP {$httpCode}): " . $response);

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            return ['success' => false, 'error' => $decoded['message'] ?? 'API Error', 'code' => $httpCode];
        }

        return ['success' => true, 'data' => $decoded];
    }

    /**
     * Limpia y formatea el celular al formato internacional E.164.
     */
    private function formatPhone(string $celular): string
    {
        $num = preg_replace('/\D/', '', $celular);
        
        // Si empieza con 521, cambiarlo a 52 para YCloud/WhatsApp API estándar (Meta espera 52XXXXXXXXXX sin el 1)
        if (strlen($num) === 13 && strpos($num, '521') === 0) {
            return '52' . substr($num, 3);
        }
        if (strlen($num) === 10) {
            return '52' . $num;
        }
        return $num;
    }
}
