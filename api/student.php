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
                $st = $db->prepare("SELECT * FROM students WHERE id=?");
                $st->execute([$id]);
                jsonResponse($st->fetch() ?: []);
            } elseif ($q !== '') {
                $st = $db->prepare(
                    "SELECT s.*,
                     (SELECT COUNT(*) FROM borrow_records br
                      WHERE br.borrower_type='student' AND br.borrower_name=s.name
                        AND br.status='taken' AND br.deleted_at IS NULL) AS current_borrows
                     FROM students s WHERE s.name LIKE ? ORDER BY s.borrow_count DESC LIMIT 10"
                );
                $st->execute(['%' . $q . '%']);
                jsonResponse($st->fetchAll());
            } else {
                $st = $db->query(
                    "SELECT s.*,
                     (SELECT COUNT(*) FROM borrow_records br
                      WHERE br.borrower_type='student' AND br.borrower_name=s.name
                        AND br.status='taken' AND br.deleted_at IS NULL) AS current_borrows
                     FROM students s ORDER BY s.borrow_count DESC, s.name ASC LIMIT 50"
                );
                jsonResponse($st->fetchAll());
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $name = trim($data['name'] ?? '');
            if (!$name) jsonResponse(['error' => 'Name required'], 422);
            $stChk = $db->prepare("SELECT id FROM students WHERE name=?");
            $stChk->execute([$name]);
            $existing = $stChk->fetch();
            if ($existing) {
                $db->prepare(
                    "UPDATE students SET borrow_count=borrow_count+1,
                     class=COALESCE(?,class) WHERE id=?"
                )->execute([$data['class'] ?? null, $existing['id']]);
                jsonResponse(['id' => $existing['id'], 'created' => false]);
            } else {
                $st2 = $db->prepare("INSERT INTO students (name, class, borrow_count) VALUES (?,?,1)");
                $st2->execute([$name, $data['class'] ?? null]);
                jsonResponse(['id' => $db->lastInsertId(), 'created' => true], 201);
            }
            break;

        case 'PUT':
            $id   = (int)($_GET['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $db->prepare(
                "UPDATE students SET name=COALESCE(?,name), class=COALESCE(?,class) WHERE id=?"
            )->execute([$data['name'] ?? null, $data['class'] ?? null, $id]);
            jsonResponse(['ok' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
