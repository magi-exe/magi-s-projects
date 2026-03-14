<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) jsonResponse(['results' => []]);

$db     = getDB();
$like   = '%' . $q . '%';
$limit  = 12;
$results = [];

try {
    // Search borrow records
    $st = $db->prepare(
        "SELECT id, borrower_type, borrower_name, book_title, book_code, status, date_taken
         FROM borrow_records
         WHERE deleted_at IS NULL AND (borrower_name LIKE ? OR book_title LIKE ? OR book_code LIKE ?)
         ORDER BY updated_at DESC LIMIT ?"
    );
    $st->execute([$like, $like, $like, $limit]);
    foreach ($st->fetchAll() as $row) {
        $results[] = ['type' => 'borrow', 'id' => $row['id'],
            'title' => $row['borrower_name'] . ' — ' . $row['book_title'],
            'sub'   => $row['book_code'] . ' · ' . ucfirst($row['status']),
            'badge' => $row['borrower_type'], 'status' => $row['status'],
            'link'  => $row['borrower_type'] === 'teacher' ? 'teachers.html' : 'students.html'];
    }

    // Search books inventory
    $st2 = $db->prepare(
        "SELECT id, title, code, author, category FROM books
         WHERE title LIKE ? OR code LIKE ? OR author LIKE ? ORDER BY title LIMIT 6"
    );
    $st2->execute([$like, $like, $like]);
    foreach ($st2->fetchAll() as $row) {
        $results[] = ['type' => 'book', 'id' => $row['id'],
            'title' => $row['title'],
            'sub'   => $row['code'] . ($row['author'] ? ' · ' . $row['author'] : ''),
            'badge' => 'book', 'link' => 'books.html'];
    }

    // Search teachers
    $st3 = $db->prepare("SELECT id, name, department FROM teachers WHERE name LIKE ? ORDER BY name LIMIT 5");
    $st3->execute([$like]);
    foreach ($st3->fetchAll() as $row) {
        $results[] = ['type' => 'teacher', 'id' => $row['id'],
            'title' => $row['name'], 'sub' => $row['department'] ?? 'Teacher',
            'badge' => 'teacher', 'link' => 'teachers.html'];
    }

    jsonResponse(['results' => array_slice($results, 0, 20)]);
} catch (PDOException $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
