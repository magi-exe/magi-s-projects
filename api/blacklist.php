<?php
/**
 * blacklist.php – Overdue blacklist management
 * GET                     → list active blacklisted members
 * GET  ?check=1&type=X&id=Y  → check if member is blacklisted
 * POST                    → add to blacklist
 * PUT  ?id=X              → update blacklist entry
 * DELETE ?id=X            → remove from blacklist
 */
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

try {
    switch ($method) {
        case 'GET':
            // Check if specific member is blacklisted
            if (isset($_GET['check'])) {
                $type = $_GET['type'] ?? '';
                $bid  = (int)($_GET['borrower_id'] ?? 0);
                if (!$type || !$bid) jsonResponse(['blacklisted' => false]);
                $st = $db->prepare(
                    "SELECT * FROM overdue_blacklist
                     WHERE borrower_type=? AND borrower_id=? AND is_active=1
                     AND (expires_at IS NULL OR expires_at > NOW())"
                );
                $st->execute([$type, $bid]);
                $row = $st->fetch();
                jsonResponse(['blacklisted' => (bool)$row, 'entry' => $row ?: null]);
            }

            // List all active blacklisted
            $limit  = min((int)($_GET['limit'] ?? 50), 200);
            $offset = (int)($_GET['offset'] ?? 0);
            $filter = $_GET['type'] ?? '';
            $where = ['is_active=1', '(expires_at IS NULL OR expires_at > NOW())'];
            $params = [];
            if ($filter) {
                $where[] = 'borrower_type=?';
                $params[] = $filter;
            }
            $sql = "SELECT * FROM overdue_blacklist WHERE " . implode(' AND ', $where) .
                   " ORDER BY blacklisted_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $st = $db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();

            $cntParams = array_slice($params, 0, -2);
            $cntSql = "SELECT COUNT(*) FROM overdue_blacklist WHERE " . implode(' AND ', $where);
            $cst = $db->prepare($cntSql);
            $cst->execute($cntParams);

            jsonResponse(['data' => $rows, 'total' => (int)$cst->fetchColumn()]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $type = $data['borrower_type'] ?? '';
            $bid  = (int)($data['borrower_id'] ?? 0);
            $name = trim($data['borrower_name'] ?? '');
            if (!$type || !$name) jsonResponse(['error' => 'borrower_type and borrower_name required'], 422);

            // Check if already blacklisted
            if ($bid) {
                $chk = $db->prepare(
                    "SELECT id FROM overdue_blacklist WHERE borrower_type=? AND borrower_id=? AND is_active=1"
                );
                $chk->execute([$type, $bid]);
                if ($chk->fetch()) jsonResponse(['error' => 'Member is already blacklisted'], 409);
            }

            $banDays = (int)getSetting('blacklist_ban_duration_days', '30');
            $expires = $data['expires_at'] ?? date('Y-m-d H:i:s', strtotime("+{$banDays} days"));

            $st = $db->prepare(
                "INSERT INTO overdue_blacklist (borrower_type, borrower_id, borrower_name, reason, expires_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $st->execute([
                $type, $bid ?: null, $name,
                $data['reason'] ?? 'Long overdue',
                $expires,
                getSetting('editor_name', 'Librarian')
            ]);

            // Log notification
            if (isset($data['borrow_record_id'])) {
                $db->prepare(
                    "INSERT INTO overdue_notifications (borrow_record_id, borrower_type, borrower_id, borrower_name, notification_type, message)
                     VALUES (?, ?, ?, ?, 'blacklisted', ?)"
                )->execute([
                    (int)$data['borrow_record_id'], $type, $bid ?: null, $name,
                    "Member blacklisted: " . ($data['reason'] ?? 'Long overdue')
                ]);
            }

            jsonResponse(['ok' => true, 'id' => $db->lastInsertId()], 201);
            break;

        case 'PUT':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $fields = []; $vals = [];
            foreach (['reason', 'expires_at', 'is_active'] as $f) {
                if (array_key_exists($f, $data)) { $fields[] = "$f=?"; $vals[] = $data[$f]; }
            }
            if (!$fields) jsonResponse(['ok' => true]);
            $vals[] = $id;
            $db->prepare("UPDATE overdue_blacklist SET " . implode(',', $fields) . " WHERE id=?")->execute($vals);
            jsonResponse(['ok' => true]);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            // Soft-deactivate
            $db->prepare("UPDATE overdue_blacklist SET is_active=0 WHERE id=?")->execute([$id]);
            jsonResponse(['ok' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
