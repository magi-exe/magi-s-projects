<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $limit  = min((int)($_GET['limit'] ?? 50), 200);
        $offset = (int)($_GET['offset'] ?? 0);
        $q      = $_GET['q'] ?? '';
        $where  = []; $params = [];
        if ($q) { $where[] = '(borrower_name LIKE ? OR book_title LIKE ? OR book_code LIKE ?)';
                  $params  = array_merge($params, ['%'.$q.'%','%'.$q.'%','%'.$q.'%']); }
        $wSql   = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $total  = $db->prepare("SELECT COUNT(*) FROM archive_records $wSql");
        $total->execute($params); $totalCount = (int)$total->fetchColumn();
        $st     = $db->prepare(
            "SELECT * FROM archive_records $wSql ORDER BY archived_at DESC LIMIT ? OFFSET ?"
        );
        $st->execute(array_merge($params, [$limit, $offset]));
        jsonResponse(['data' => $st->fetchAll(), 'total' => $totalCount]);
    }

    if ($method === 'POST') {
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $data['action'] ?? '';

        if ($action === 'restore') {
            $id = (int)($data['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $row = $db->prepare("SELECT * FROM archive_records WHERE id=?");
            $row->execute([$id]); $rec = $row->fetch();
            if (!$rec) jsonResponse(['error' => 'Not found'], 404);
            $db->prepare(
                "INSERT INTO borrow_records
                 (id,borrower_type,borrower_id,borrower_name,book_id,book_title,book_code,
                  date_taken,status,return_date,return_notes,notes,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE deleted_at=NULL, updated_at=NOW()"
            )->execute([$rec['id'],$rec['borrower_type'],$rec['borrower_id'],$rec['borrower_name'],
                        $rec['book_id'],$rec['book_title'],$rec['book_code'],$rec['date_taken'],
                        $rec['status'],$rec['return_date'],$rec['return_notes'],$rec['notes'],$rec['created_at']]);
            $db->prepare("DELETE FROM archive_records WHERE id=?")->execute([$id]);
            jsonResponse(['ok' => true]);
        }

        if ($action === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $db->prepare("DELETE FROM archive_records WHERE id=?")->execute([$id]);
            jsonResponse(['ok' => true]);
        }

        if ($action === 'restore_all') {
            $db->exec(
                "INSERT IGNORE INTO borrow_records
                 (id,borrower_type,borrower_id,borrower_name,book_id,book_title,book_code,
                  date_taken,status,return_date,return_notes,notes,created_at,updated_at)
                 SELECT id,borrower_type,borrower_id,borrower_name,book_id,book_title,book_code,
                        date_taken,status,return_date,return_notes,notes,created_at,NOW()
                 FROM archive_records"
            );
            $db->exec("DELETE FROM archive_records");
            jsonResponse(['ok' => true]);
        }
    }

    jsonResponse(['error' => 'Invalid request'], 400);
} catch (PDOException $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
