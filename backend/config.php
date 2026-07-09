<?php
// config.php — Configuración central del backend

// Reporte de errores para desarrollo (desactivar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Zona horaria: México abolió el horario de verano en 2023 → UTC-6 fijo
// Etc/GMT+6 = UTC-6 (convención POSIX: el signo es invertido)
date_default_timezone_set('Etc/GMT+6');

// Configuración de la Base de Datos (SiteGround)
define('DB_HOST', 'localhost');
define('DB_USER', 'u919bgucjtnc4');
define('DB_PASS', '{1A1sN(k{tde');
define('DB_NAME', 'db5iwnlmwudfbp');

// Configuración de YCloud (API WhatsApp)
define('YCLOUD_API_KEY',        'f741a05b8a87de4a6877fa3c90b33f35');
define('YCLOUD_API_URL',        'https://api.ycloud.com/v2/whatsapp/messages');
define('YCLOUD_WABA_ID',        '1015708140860983');
define('YCLOUD_FROM_PHONE',     '525549194842'); // Número remitente registrado en YCloud
define('YCLOUD_WEBHOOK_SECRET', 'whsec_a065c23ad7df49629224c6cb7e55893e');
define('YCLOUD_WEBHOOK_ID',     '6a4ef4d67d1ef4403aca0041');

// Taecel eliminado - esta plataforma no hace recargas telefónicas

// Configuración de la App
$host = $_SERVER['HTTP_HOST'] ?? 'g15k.qrewards.com.mx';
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



// Plantillas de WhatsApp (YCloud) para notificaciones fuera de ventana de 24 hrs
// PENDIENTE: configurar nombres reales una vez aprobadas en Meta Business Manager
define('YCLOUD_TEMPLATE_APROBACION', 'registro_aprobado'); // {{1}} = Nombre
define('YCLOUD_TEMPLATE_RECHAZO',    'registro_rechazado'); // {{1}} = Nombre, {{2}} = Motivo

