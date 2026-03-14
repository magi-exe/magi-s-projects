<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

try {
    switch ($method) {
        case 'GET':
            $q  = $_GET['q'] ?? '';
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id) {
                $st = $db->prepare("SELECT * FROM teachers WHERE id = ?");
                $st->execute([$id]);
                jsonResponse($st->fetch() ?: []);
            } elseif ($q !== '') {
                $st = $db->prepare("SELECT * FROM teachers WHERE name LIKE ? ORDER BY name LIMIT 10");
                $st->execute(['%' . $q . '%']);
                jsonResponse($st->fetchAll());
            } else {
                $st = $db->query(
                    "SELECT t.*,
                     (SELECT COUNT(*) FROM borrow_records br
                      WHERE br.borrower_id=t.id AND br.borrower_type='teacher'
                        AND br.status='taken' AND br.deleted_at IS NULL) AS current_borrows,
                     (SELECT COUNT(*) FROM borrow_records br
                      WHERE br.borrower_id=t.id AND br.borrower_type='teacher'
                        AND br.deleted_at IS NULL) AS total_borrows
                     FROM teachers t ORDER BY t.created_at DESC"
                );
                jsonResponse($st->fetchAll());
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $name = trim($data['name'] ?? '');
            if (!$name) jsonResponse(['error' => 'Name required'], 422);
            $words    = explode(' ', $name);
            $initials = '';
            foreach ($words as $w) { if ($w) $initials .= strtoupper($w[0]); }
            $initials = substr($initials, 0, 4);
            $st = $db->prepare(
                "INSERT INTO teachers (name, department, phone, initials) VALUES (?,?,?,?)"
            );
            $st->execute([$name, $data['department'] ?? null, $data['phone'] ?? null, $initials]);
            $newId = $db->lastInsertId();
            $st2   = $db->prepare("SELECT * FROM teachers WHERE id=?");
            $st2->execute([$newId]);
            jsonResponse($st2->fetch(), 201);
            break;

        case 'PUT':
            $id   = (int)($_GET['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $fields = []; $vals = [];
            foreach (['name','department','phone'] as $f) {
                if (isset($data[$f])) { $fields[] = "$f=?"; $vals[] = $data[$f]; }
            }
            if (!$fields) jsonResponse(['error' => 'Nothing to update'], 422);
            $vals[] = $id;
            $db->prepare("UPDATE teachers SET " . implode(',', $fields) . " WHERE id=?")->execute($vals);
            jsonResponse(['ok' => true]);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $db->prepare("DELETE FROM teachers WHERE id=?")->execute([$id]);
            jsonResponse(['ok' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
