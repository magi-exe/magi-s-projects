<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$db = getDB();
$recordId = isset($_GET['record_id']) ? (int)$_GET['record_id'] : 0;
if ($recordId) {
    $st = $db->prepare("SELECT * FROM audit_log WHERE record_id=? ORDER BY timestamp DESC");
    $st->execute([$recordId]);
} else {
    $limit = min((int)($_GET['limit'] ?? 50), 500);
    $st = $db->prepare("SELECT * FROM audit_log ORDER BY timestamp DESC LIMIT ?");
    $st->execute([$limit]);
}
jsonResponse($st->fetchAll());
