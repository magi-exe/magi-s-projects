<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

try {
    switch ($method) {
        case 'GET':
            $q    = $_GET['q'] ?? '';
            $code = $_GET['code'] ?? '';
            if ($code !== '') {
                $st = $db->prepare("SELECT * FROM books WHERE code=? LIMIT 1");
                $st->execute([$code]);
                jsonResponse($st->fetch() ?: []);
            } elseif ($q !== '') {
                $st = $db->prepare(
                    "SELECT * FROM books WHERE title LIKE ? OR code LIKE ? ORDER BY title LIMIT 15"
                );
                $st->execute(['%' . $q . '%', '%' . $q . '%']);
                jsonResponse($st->fetchAll());
            } else {
                jsonResponse($db->query("SELECT * FROM books ORDER BY title")->fetchAll());
            }
            break;

        case 'POST':
            $data  = json_decode(file_get_contents('php://input'), true) ?? [];
            $title = trim($data['title'] ?? '');
            $code  = trim($data['code'] ?? '');
            if (!$title || !$code) jsonResponse(['error' => 'Title and code required'], 422);
            $stChk = $db->prepare("SELECT id FROM books WHERE code=?");
            $stChk->execute([$code]);
            $existing = $stChk->fetch();
            if ($existing) {
                jsonResponse(['id' => $existing['id']], 200);
            } else {
                $st = $db->prepare("INSERT INTO books (title, code, author, isbn) VALUES (?,?,?,?)");
                $st->execute([$title, $code, $data['author'] ?? null, $data['isbn'] ?? null]);
                jsonResponse(['id' => $db->lastInsertId()], 201);
            }
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
