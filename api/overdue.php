<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$db     = getDB();
$limit  = min((int)($_GET['limit'] ?? 50), 200);
$offset = (int)($_GET['offset'] ?? 0);
$filter = $_GET['filter'] ?? 'all';
$sort   = $_GET['sort'] ?? 'days_overdue';
$dir    = (($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

// Get per-type return periods
$rpTeacher = (int)getSetting('return_period_teacher', getSetting('return_period', '14'));
$rpStudent = (int)getSetting('return_period_student', getSetting('return_period', '14'));
$rpClub    = (int)getSetting('return_period_reading_club', getSetting('return_period', '14'));

try {
    $where  = ["br.status='taken'", "br.deleted_at IS NULL"];
    $params = [];

    // Build overdue condition using per-type return period
    $where[] = "(
        (br.borrower_type='teacher' AND br.date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
        OR (br.borrower_type='student' AND br.date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
        OR (br.borrower_type='reading_club' AND br.date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
    )";
    $params[] = $rpTeacher;
    $params[] = $rpStudent;
    $params[] = $rpClub;

    if ($filter === 'teacher')      { $where[] = "br.borrower_type='teacher'"; }
    if ($filter === 'student')      { $where[] = "br.borrower_type='student'"; }
    if ($filter === 'reading_club') { $where[] = "br.borrower_type='reading_club'"; }

    if (!empty($_GET['search'])) {
        $where[] = "(br.borrower_name LIKE ? OR br.book_title LIKE ? OR br.book_code LIKE ?)";
        $s = '%' . $_GET['search'] . '%';
        $params[] = $s; $params[] = $s; $params[] = $s;
    }

    $allowed_sort = ['borrower_name','book_title','book_code','date_taken','days_overdue','borrower_type'];
    $sortCol = in_array($sort, $allowed_sort) ? $sort : 'days_overdue';
    if ($sortCol === 'days_overdue') $sortCol = 'DATEDIFF(NOW(), br.date_taken)';
    else $sortCol = "br.$sortCol";

    $sql = "SELECT br.*,
            DATEDIFF(NOW(), br.date_taken) AS days_out,
            CASE br.borrower_type
                WHEN 'teacher' THEN DATEDIFF(NOW(), br.date_taken) - ?
                WHEN 'student' THEN DATEDIFF(NOW(), br.date_taken) - ?
                WHEN 'reading_club' THEN DATEDIFF(NOW(), br.date_taken) - ?
            END AS days_overdue
            FROM borrow_records br
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $sortCol $dir LIMIT ? OFFSET ?";
    $params[] = $rpTeacher;
    $params[] = $rpStudent;
    $params[] = $rpClub;
    $params[] = $limit;
    $params[] = $offset;

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    // Count query (without limit/offset and without the days_overdue sort params)
    $cntParams = array_slice($params, 0, -5); // remove sort rp + limit + offset
    $cntSql = "SELECT COUNT(*) FROM borrow_records br WHERE " . implode(' AND ', $where);
    $cntSt  = $db->prepare($cntSql);
    $cntSt->execute($cntParams);
    $total = (int)$cntSt->fetchColumn();

    // Stats per type
    $baseWhere = ["status='taken'", "deleted_at IS NULL"];
    $teacherOverdue = $db->prepare(
        "SELECT COUNT(*) FROM borrow_records WHERE status='taken' AND deleted_at IS NULL
         AND borrower_type='teacher' AND date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY)"
    );
    $teacherOverdue->execute([$rpTeacher]);

    $studentOverdue = $db->prepare(
        "SELECT COUNT(*) FROM borrow_records WHERE status='taken' AND deleted_at IS NULL
         AND borrower_type='student' AND date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY)"
    );
    $studentOverdue->execute([$rpStudent]);

    $clubOverdue = $db->prepare(
        "SELECT COUNT(*) FROM borrow_records WHERE status='taken' AND deleted_at IS NULL
         AND borrower_type='reading_club' AND date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY)"
    );
    $clubOverdue->execute([$rpClub]);

    jsonResponse([
        'data'  => $rows,
        'total' => $total,
        'stats' => [
            'teacher'      => (int)$teacherOverdue->fetchColumn(),
            'student'      => (int)$studentOverdue->fetchColumn(),
            'reading_club' => (int)$clubOverdue->fetchColumn(),
        ],
        'return_periods' => [
            'teacher'      => $rpTeacher,
            'student'      => $rpStudent,
            'reading_club' => $rpClub,
        ]
    ]);
} catch (PDOException $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
