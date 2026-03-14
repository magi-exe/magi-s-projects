<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$db = getDB();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $st  = $db->query("SELECT `key`, `value` FROM settings");
        $rows = $st->fetchAll();
        $out  = [];
        foreach ($rows as $r) $out[$r['key']] = $r['value'];
        jsonResponse($out);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $st   = $db->prepare(
            "INSERT INTO settings (`key`, `value`) VALUES (?,?)
             ON DUPLICATE KEY UPDATE `value`=?"
        );
        foreach ($data as $k => $v) {
            $st->execute([$k, $v, $v]);
        }
        jsonResponse(['ok' => true]);

    } else {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
