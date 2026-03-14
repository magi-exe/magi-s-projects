<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES   => false];
        try { $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts); }
        catch (PDOException $e) {
            http_response_code(500); header('Content-Type: application/json');
            echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]); exit;
        }
    }
    return $pdo;
}

function getSetting(string $key, string $default = ''): string {
    try {
        $st = getDB()->prepare("SELECT `value` FROM settings WHERE `key`=?");
        $st->execute([$key]); $row = $st->fetch();
        return $row ? (string)$row['value'] : $default;
    } catch (PDOException $e) { return $default; }
}

function setSetting(string $key, string $value): void {
    getDB()->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=?")
           ->execute([$key, $value, $value]);
}

function jsonResponse($data, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json');
    echo json_encode($data); exit;
}

function auditLog(int $recordId, string $field, $old, $new): void {
    try {
        $editor = getSetting('editor_name', 'Librarian');
        getDB()->prepare("INSERT INTO audit_log(record_id,field_changed,old_value,new_value,editor_name) VALUES(?,?,?,?,?)")
               ->execute([$recordId, $field, (string)$old, (string)$new, $editor]);
    } catch (PDOException $e) {}
}

// Initialize admin password hash on first run
function ensureAdminPassword(): void {
    $hash = getSetting('admin_password_hash', '');
    if (empty($hash)) {
        setSetting('admin_password_hash', password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT));
        setSetting('admin_password_recoverable', DEFAULT_ADMIN_PASSWORD);
    }
}

// Store recoverable password (called when password is changed)
function storeRecoverablePassword(string $plainPassword): void {
    setSetting('admin_password_recoverable', $plainPassword);
}
