<?php
/**
 * reading_club.php – CRUD for reading club members
 * GET    ?q=...           → search
 * GET    ?id=X            → single member
 * GET    (no params)      → list all (paginated)
 * POST                    → create
 * PUT    ?id=X            → update
 * DELETE ?id=X            → delete
 */
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

try {
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id) {
                $st = $db->prepare("SELECT * FROM reading_club_members WHERE id=?");
                $st->execute([$id]);
                $row = $st->fetch();
                if (!$row) jsonResponse(['error' => 'Not found'], 404);
                jsonResponse($row);
            }
            $q = $_GET['q'] ?? '';
            $limit  = min((int)($_GET['limit'] ?? 50), 200);
            $offset = (int)($_GET['offset'] ?? 0);
            if ($q) {
                $st = $db->prepare("SELECT * FROM reading_club_members WHERE name LIKE ? ORDER BY name ASC LIMIT ? OFFSET ?");
                $st->execute(['%' . $q . '%', $limit, $offset]);
            } else {
                $st = $db->prepare("SELECT * FROM reading_club_members ORDER BY name ASC LIMIT ? OFFSET ?");
                $st->execute([$limit, $offset]);
            }
            $rows = $st->fetchAll();
            $cntSql = $q
                ? "SELECT COUNT(*) FROM reading_club_members WHERE name LIKE ?"
                : "SELECT COUNT(*) FROM reading_club_members";
            $cst = $db->prepare($cntSql);
            $cst->execute($q ? ['%' . $q . '%'] : []);
            jsonResponse(['data' => $rows, 'total' => (int)$cst->fetchColumn()]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $name = trim($data['name'] ?? '');
            if (!$name) jsonResponse(['error' => 'Name is required'], 422);
            $st = $db->prepare(
                "INSERT INTO reading_club_members (name, class) VALUES (?, ?)"
            );
            $st->execute([$name, $data['class'] ?? null]);
            $newId = $db->lastInsertId();
            $st2 = $db->prepare("SELECT * FROM reading_club_members WHERE id=?");
            $st2->execute([$newId]);
            jsonResponse($st2->fetch(), 201);
            break;

        case 'PUT':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $fields = []; $vals = [];
            foreach (['name', 'class'] as $f) {
                if (array_key_exists($f, $data)) {
                    $fields[] = "$f=?";
                    $vals[] = $data[$f];
                }
            }
            if (!$fields) jsonResponse(['ok' => true]);
            $vals[] = $id;
            $db->prepare("UPDATE reading_club_members SET " . implode(',', $fields) . " WHERE id=?")->execute($vals);
            jsonResponse(['ok' => true]);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $db->prepare("DELETE FROM reading_club_members WHERE id=?")->execute([$id]);
            jsonResponse(['ok' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
