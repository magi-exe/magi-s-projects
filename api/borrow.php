<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

function buildFilters(array $get): array {
    $where  = ['br.deleted_at IS NULL'];
    $params = [];
    if (!empty($get['status']))        { $where[] = 'br.status=?';              $params[] = $get['status']; }
    if (!empty($get['borrower_type'])) { $where[] = 'br.borrower_type=?';       $params[] = $get['borrower_type']; }
    if (!empty($get['borrower_id']))   { $where[] = 'br.borrower_id=?';         $params[] = (int)$get['borrower_id']; }
    if (!empty($get['borrower_name'])) { $where[] = 'br.borrower_name LIKE ?';  $params[] = '%' . $get['borrower_name'] . '%'; }
    if (!empty($get['book_code']))     { $where[] = 'br.book_code=?';           $params[] = $get['book_code']; }
    if (!empty($get['book_title']))    { $where[] = 'br.book_title LIKE ?';     $params[] = '%' . $get['book_title'] . '%'; }
    if (!empty($get['date_from']))     { $where[] = 'br.date_taken >= ?';       $params[] = $get['date_from']; }
    if (!empty($get['date_to']))       { $where[] = 'br.date_taken <= ?';       $params[] = $get['date_to'] . ' 23:59:59'; }
    if (!empty($get['search'])) {
        $where[] = '(br.borrower_name LIKE ? OR br.book_title LIKE ? OR br.book_code LIKE ?)';
        $params[] = '%' . $get['search'] . '%';
        $params[] = '%' . $get['search'] . '%';
        $params[] = '%' . $get['search'] . '%';
    }
    if (!empty($get['overdue'])) {
        $rpT = (int)getSetting('return_period_teacher', getSetting('return_period', '14'));
        $rpS = (int)getSetting('return_period_student', getSetting('return_period', '14'));
        $rpC = (int)getSetting('return_period_reading_club', getSetting('return_period', '14'));
        $where[]  = "br.status='taken' AND (
            (br.borrower_type='teacher' AND br.date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
            OR (br.borrower_type='student' AND br.date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
            OR (br.borrower_type='reading_club' AND br.date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
        )";
        $params[] = $rpT;
        $params[] = $rpS;
        $params[] = $rpC;
    }
    $allowed_sort = ['borrower_name','book_title','book_code','date_taken','status','return_date'];
    $sort  = in_array($get['sort'] ?? '', $allowed_sort) ? $get['sort'] : 'date_taken';
    $dir   = (($get['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
    $sort2 = in_array($get['sort2'] ?? '', $allowed_sort) ? $get['sort2'] : null;
    $dir2  = (($get['dir2'] ?? 'asc') === 'asc') ? 'ASC' : 'DESC';
    $orderBy = "br.$sort $dir" . ($sort2 ? ", br.$sort2 $dir2" : "");
    return [$where, $params, $orderBy];
}

// Resolve member by ID from the appropriate table
function resolveMember(PDO $db, string $type, int $memberId): ?array {
    $tableMap = [
        'teacher' => 'teachers',
        'student' => 'students',
        'reading_club' => 'reading_club_members'
    ];
    $table = $tableMap[$type] ?? null;
    if (!$table || !$memberId) return null;
    $st = $db->prepare("SELECT * FROM $table WHERE id=?");
    $st->execute([$memberId]);
    return $st->fetch() ?: null;
}

try {
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id) {
                $st = $db->prepare(
                    "SELECT br.*, DATEDIFF(COALESCE(br.return_date, NOW()), br.date_taken) AS days_stayed
                     FROM borrow_records br WHERE br.id=? AND br.deleted_at IS NULL"
                );
                $st->execute([$id]);
                jsonResponse($st->fetch() ?: []);
            }
            list($where, $params, $orderBy) = buildFilters($_GET);
            $limit  = min((int)($_GET['limit'] ?? 50), 200);
            $offset = (int)($_GET['offset'] ?? 0);
            $sql = "SELECT br.*,
                    DATEDIFF(COALESCE(br.return_date, NOW()), br.date_taken) AS days_stayed
                    FROM borrow_records br
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY $orderBy LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $st = $db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();
            $cntParams = array_slice($params, 0, -2);
            $cntSql = "SELECT COUNT(*) FROM borrow_records br WHERE " . implode(' AND ', $where);
            $cntSt  = $db->prepare($cntSql);
            $cntSt->execute($cntParams);
            jsonResponse(['data' => $rows, 'total' => (int)$cntSt->fetchColumn()]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $borrowerType = $data['borrower_type'] ?? '';
            $borrowerId   = !empty($data['borrower_id']) ? (int)$data['borrower_id'] : null;
            $borrowerName = trim($data['borrower_name'] ?? '');

            // If member_id provided with type, resolve member from DB
            if ($borrowerId && $borrowerType && !$borrowerName) {
                $member = resolveMember($db, $borrowerType, $borrowerId);
                if ($member) {
                    $borrowerName = $member['name'];
                }
            }

            // Validate required fields
            if (!$borrowerType) jsonResponse(['error' => 'borrower_type is required'], 422);
            if (!$borrowerName) jsonResponse(['error' => 'borrower_name is required'], 422);
            if (empty($data['book_title']) && empty($data['book_code'])) {
                jsonResponse(['error' => 'book_title or book_code is required'], 422);
            }

            // Resolve book by code if only code provided
            $bookTitle = trim($data['book_title'] ?? '');
            $bookCode  = trim($data['book_code'] ?? '');
            $bookId    = !empty($data['book_id']) ? (int)$data['book_id'] : null;

            if ($bookCode && !$bookTitle) {
                $bst = $db->prepare("SELECT id, title FROM books WHERE code=?");
                $bst->execute([$bookCode]);
                $book = $bst->fetch();
                if ($book) {
                    $bookTitle = $book['title'];
                    $bookId = (int)$book['id'];
                }
            }
            if (!$bookTitle) jsonResponse(['error' => 'Could not resolve book title'], 422);
            if (!$bookCode) jsonResponse(['error' => 'book_code is required'], 422);

            // Check if member is blacklisted
            if ($borrowerId) {
                try {
                    $blst = $db->prepare(
                        "SELECT id FROM overdue_blacklist
                         WHERE borrower_type=? AND borrower_id=? AND is_active=1
                         AND (expires_at IS NULL OR expires_at > NOW())"
                    );
                    $blst->execute([$borrowerType, $borrowerId]);
                    if ($blst->fetch()) {
                        jsonResponse(['error' => 'This member is blacklisted and cannot borrow books'], 403);
                    }
                } catch (PDOException $e) {
                    // blacklist table may not exist yet
                }
            }

            $dateTaken = $data['date_taken'] ?? date('Y-m-d\TH:i');

            $st = $db->prepare(
                "INSERT INTO borrow_records
                 (borrower_type,borrower_id,borrower_name,book_id,book_title,book_code,date_taken,notes)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                $borrowerType,
                $borrowerId,
                $borrowerName,
                $bookId,
                $bookTitle,
                $bookCode,
                $dateTaken,
                $data['notes'] ?? null,
            ]);
            $newId = $db->lastInsertId();

            // Update borrow count for students/reading_club
            if ($borrowerType === 'student' && $borrowerId) {
                $db->prepare("UPDATE students SET borrow_count = borrow_count + 1 WHERE id=?")->execute([$borrowerId]);
            } elseif ($borrowerType === 'student' && !$borrowerId) {
                $db->prepare(
                    "INSERT INTO students (name, borrow_count) VALUES (?, 1)
                     ON DUPLICATE KEY UPDATE borrow_count = borrow_count + 1"
                )->execute([$borrowerName]);
            } elseif ($borrowerType === 'reading_club' && $borrowerId) {
                try {
                    $db->prepare("UPDATE reading_club_members SET borrow_count = borrow_count + 1 WHERE id=?")->execute([$borrowerId]);
                } catch (PDOException $e) {}
            }

            $st2 = $db->prepare(
                "SELECT br.*, DATEDIFF(COALESCE(br.return_date,NOW()),br.date_taken) AS days_stayed
                 FROM borrow_records br WHERE br.id=?"
            );
            $st2->execute([$newId]);
            jsonResponse($st2->fetch(), 201);
            break;

        case 'PUT':
            $id   = (int)($_GET['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $cur = $db->prepare("SELECT * FROM borrow_records WHERE id=?");
            $cur->execute([$id]);
            $current = $cur->fetch();
            if (!$current) jsonResponse(['error' => 'Record not found'], 404);
            $allowed = ['borrower_name','borrower_id','book_title','book_code','date_taken',
                        'notes','status','return_date','return_notes'];
            $fields = []; $vals = [];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    if ((string)$current[$f] !== (string)$data[$f]) {
                        auditLog($id, $f, $current[$f], $data[$f]);
                    }
                    $fields[] = "$f=?";
                    $vals[]   = $data[$f];
                }
            }
            if (!$fields) jsonResponse(['ok' => true]);
            $vals[] = $id;
            $db->prepare("UPDATE borrow_records SET " . implode(',', $fields) . " WHERE id=?")->execute($vals);
            jsonResponse(['ok' => true]);
            break;

        case 'DELETE':
            $id   = (int)($_GET['id'] ?? 0);
            $hard = isset($_GET['hard']);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            if ($hard) {
                $db->prepare("DELETE FROM borrow_records WHERE id=?")->execute([$id]);
            } else {
                $db->prepare("UPDATE borrow_records SET deleted_at=NOW() WHERE id=?")->execute([$id]);
            }
            jsonResponse(['ok' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
