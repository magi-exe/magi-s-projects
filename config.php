<?php
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'comboni_library');
define('DB_CHARSET', 'utf8mb4');
define('APP_VERSION',    '4.0.0');
define('SCHEMA_VERSION', '4');
define('UPLOAD_DIR',     __DIR__ . '/uploads/');
define('DEFAULT_ADMIN_PASSWORD', 'Comboni-library5634');

if (!defined('NO_CORS')) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); exit;
    }
}
