<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

ensureAdminPassword();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check') {
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
    jsonResponse(['role' => $role, 'ok' => ($role !== null)]);
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = [];
    $action = isset($data['action']) ? $data['action'] : '';

    if ($action === 'login') {
        $role     = isset($data['role']) ? $data['role'] : 'admin';
        $password = isset($data['password']) ? $data['password'] : '';

        // Guest login removed in v6
        if ($role === 'admin') {
            $hash = getSetting('admin_password_hash', '');
            if (!empty($hash) && password_verify($password, $hash)) {
                $_SESSION['role'] = 'admin';
                jsonResponse(['ok' => true, 'role' => 'admin']);
            }
            jsonResponse(['ok' => false, 'error' => 'Incorrect password'], 401);
        }
    }

    if ($action === 'logout') {
        session_unset(); session_destroy();
        jsonResponse(['ok' => true]);
    }

    if ($action === 'change_password') {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            jsonResponse(['error' => 'Admin only'], 403);
        $current = isset($data['current']) ? $data['current'] : '';
        $newPass = isset($data['new'])     ? $data['new']     : '';
        $hash    = getSetting('admin_password_hash', '');
        if (!password_verify($current, $hash)) jsonResponse(['error' => 'Current password is incorrect'], 401);
        if (strlen($newPass) < 6) jsonResponse(['error' => 'New password must be at least 6 characters'], 422);
        setSetting('admin_password_hash', password_hash($newPass, PASSWORD_DEFAULT));
        storeRecoverablePassword($newPass);
        jsonResponse(['ok' => true]);
    }

    // Forgot password flow
    if ($action === 'forgot_password') {
        jsonResponse([
            'ok' => true,
            'message' => 'Contact the developer for a recovery key.',
            'developer_contact' => '0903932959',
            'instructions' => 'Call or message the developer to receive your recovery key.'
        ]);
    }

    // Developer recovery – verify dev password and reveal current admin password
    if ($action === 'dev_recovery') {
        $devPassword = isset($data['dev_password']) ? $data['dev_password'] : '';
        $correctDevPass = 'jedi_man';
        if ($devPassword !== $correctDevPass) {
            jsonResponse(['ok' => false, 'error' => 'Invalid developer password'], 401);
        }
        // Get the stored hash and we need to show the current password
        // Since we can't reverse a hash, we store the plain password temporarily
        // Actually, for this to work, we need to store the current password readable
        // The system will store the last-set password in a recoverable way for dev only
        $recoverable = getSetting('admin_password_recoverable', '');
        if (empty($recoverable)) {
            jsonResponse([
                'ok' => true,
                'message' => 'Password recovery not available. The password was set before this feature was enabled. Please reset it manually in the database.',
                'password' => null
            ]);
        }
        jsonResponse([
            'ok' => true,
            'password' => $recoverable,
            'message' => 'Current admin password retrieved successfully.'
        ]);
    }
}

jsonResponse(['error' => 'Invalid request'], 400);
