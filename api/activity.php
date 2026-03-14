<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$db     = getDB();
$limit  = min((int)($_GET['limit'] ?? 20), 100);
$offset = (int)($_GET['offset'] ?? 0);
$filter = $_GET['filter'] ?? 'all';

$where  = ['deleted_at IS NULL'];
$params = [];
if ($filter === 'taken')        { $where[] = "status='taken'"; }
if ($filter === 'returned')     { $where[] = "status='returned'"; }
if ($filter === 'teacher')      { $where[] = "borrower_type='teacher'"; }
if ($filter === 'student')      { $where[] = "borrower_type='student'"; }
if ($filter === 'reading_club') { $where[] = "borrower_type='reading_club'"; }

try {
    $sql = "SELECT id, borrower_type, borrower_name, book_title, book_code,
                   status, date_taken, return_date, created_at, updated_at
            FROM borrow_records
            WHERE " . implode(' AND ', $where) . "
            ORDER BY updated_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $total_taken = $db->query(
        "SELECT COUNT(*) FROM borrow_records WHERE status='taken' AND deleted_at IS NULL"
    )->fetchColumn();
    $taken_today = $db->query(
        "SELECT COUNT(*) FROM borrow_records WHERE DATE(date_taken)=CURDATE() AND deleted_at IS NULL"
    )->fetchColumn();

    jsonResponse([
        'events'      => $rows,
        'total_taken' => (int)$total_taken,
        'taken_today' => (int)$taken_today,
    ]);
} catch (PDOException $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
