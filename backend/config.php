<?php
// config.php — Configuración central del backend

// Reporte de errores para desarrollo (desactivar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Configuración de la Base de Datos (SiteGround)
define('DB_HOST', 'localhost');
define('DB_USER', 'uqvv7yh80ny7a');
define('DB_PASS', '&om1^7^1icg,');
define('DB_NAME', 'db3nb29ylqgkun');

// Configuración de YCloud (API WhatsApp)
define('YCLOUD_API_KEY', 'b61e82606d294400975813a06184830d');
define('YCLOUD_API_URL', 'https://api.ycloud.com/v2/whatsapp/messages');
define('YCLOUD_WABA_ID', '3912557032207779');
define('YCLOUD_FROM_PHONE', '525537041142'); // Número remitente formateado
define('YCLOUD_WEBHOOK_SECRET', 'whsec_4565af4e9cc244ae83cd00562cc43395'); // Firma de validación de webhook

// Credenciales de Taecel (Recargas de Tiempo Aire)
define('TAECEL_KEY', 'M1Ss74dU5Gx87KCW9mCz2Imi7bc8d6adbbdb9f57410848fa9ce325a54AeAd2k04dsciF6nmEvuo7qyu37xLuP');
define('TAECEL_NIP', 'f82dc3d9102a7591fd37a5593dc5ab17T44ui7Pib2');
define('TAECEL_API_URL', 'https://taecel.com/app/api/');

// Configuración de la App
$host = $_SERVER['HTTP_HOST'] ?? 'clasicoslafe.qrewards.com.mx';
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
define('APP_URL', $proto . '://' . $host . '/backend');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('ADMIN_USERS', [
    'admin' => '$2y$10$6m2rOEvn.f67K.yK5pQ89.z8/J6P8/hJ9sF0B3N.v5m1c2N5v5m1c', // Se verifica con password_verify en login.php
    'operaciones' => '$2y$10$Y1s4u9P3b2i7L6q8d7F4ui7Pb2i7L6q8d7F4ui7Pb2i7L6q8d7F4u', // Se verifica con password_verify en login.php
    'AnahiGA' => '$2y$12$owIdz1IgXK0Hd3mgNXrGRuVjZAO5hYObCgk3OjNr2zU43XadgFcYC',
    'Mreyes' => '$2y$12$rlLxXRey/Zfj.99QWMtp0uvbonWQL09c5arK5294I3Bew8dm0CHoS'
]);
// Para simplificar la generación dinámica de hashes si es necesario:
// Hash para 'admin': password_hash('Cerrillera2026!', PASSWORD_DEFAULT)
// Hash para 'operaciones': password_hash('Operaciones2026!', PASSWORD_DEFAULT)
define('ADMIN_PASS_ADMIN', 'Cerrillera2026!');
define('ADMIN_PASS_OPERACIONES', 'Operaciones2026!');

// Configurar zona horaria de México
date_default_timezone_set('America/Mexico_City');

// Plantillas oficiales de WhatsApp (YCloud) para notificaciones fuera de la ventana de 24 horas
define('YCLOUD_TEMPLATE_APROBACION', 'registro_aprobado'); // Nombre de la plantilla en Meta/YCloud (ej. variables: {{1}} = Nombre, {{2}} = Monto, {{3}} = Folio)
define('YCLOUD_TEMPLATE_RECHAZO', 'registro_rechazado');       // Nombre de la plantilla en Meta/YCloud (ej. variables: {{1}} = Nombre, {{2}} = Motivo)

// Configuración de 360dialog (Meta-compliant v2 API)
define('META_ACCESS_TOKEN', '3I4O3QydIWXXH14JVtlwRlKqAK');
define('META_TEMPLATE_APROBACION', 'registro_aprobado');
define('META_TEMPLATE_RECHAZO', 'registro_rechazado');

