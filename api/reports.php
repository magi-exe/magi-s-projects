<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$db   = getDB();
$type = $_GET['type'] ?? 'summary';

// Date range filter helper
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

function dateRangeWhere(string $dateCol, string $dateFrom, string $dateTo, array &$params): string {
    $clauses = [];
    if ($dateFrom) { $clauses[] = "$dateCol >= ?"; $params[] = $dateFrom; }
    if ($dateTo)   { $clauses[] = "$dateCol <= ?"; $params[] = $dateTo . ' 23:59:59'; }
    return $clauses ? (' AND ' . implode(' AND ', $clauses)) : '';
}

try {
    switch ($type) {
        case 'summary':
            $total      = $db->query("SELECT COUNT(*) FROM borrow_records WHERE deleted_at IS NULL")->fetchColumn();
            $taken      = $db->query("SELECT COUNT(*) FROM borrow_records WHERE status='taken' AND deleted_at IS NULL")->fetchColumn();
            $returned   = $db->query("SELECT COUNT(*) FROM borrow_records WHERE status='returned' AND deleted_at IS NULL")->fetchColumn();

            $rpT = (int)getSetting('return_period_teacher', getSetting('return_period', '14'));
            $rpS = (int)getSetting('return_period_student', getSetting('return_period', '14'));
            $rpC = (int)getSetting('return_period_reading_club', getSetting('return_period', '14'));
            $overdue = $db->prepare(
                "SELECT COUNT(*) FROM borrow_records WHERE status='taken' AND deleted_at IS NULL AND (
                    (borrower_type='teacher' AND date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
                    OR (borrower_type='student' AND date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
                    OR (borrower_type='reading_club' AND date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
                )"
            );
            $overdue->execute([$rpT, $rpS, $rpC]);
            $overdueCount = $overdue->fetchColumn();

            $teachers   = $db->query("SELECT COUNT(DISTINCT borrower_id) FROM borrow_records WHERE borrower_type='teacher' AND deleted_at IS NULL")->fetchColumn();
            $students   = $db->query("SELECT COUNT(DISTINCT borrower_name) FROM borrow_records WHERE borrower_type='student' AND deleted_at IS NULL")->fetchColumn();
            $books      = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();
            jsonResponse(compact('total','taken','returned','overdueCount','teachers','students','books'));
            break;

        case 'most_borrowed':
            $params = [];
            $dateFilter = dateRangeWhere('date_taken', $dateFrom, $dateTo, $params);
            $st = $db->prepare(
                "SELECT book_title, book_code, COUNT(*) AS borrow_count
                 FROM borrow_records WHERE deleted_at IS NULL $dateFilter
                 GROUP BY book_code, book_title ORDER BY borrow_count DESC LIMIT 10"
            );
            $st->execute($params);
            jsonResponse($st->fetchAll());
            break;

        case 'most_active_borrowers':
            $params = [];
            $dateFilter = dateRangeWhere('date_taken', $dateFrom, $dateTo, $params);
            $st = $db->prepare(
                "SELECT borrower_name, borrower_type, COUNT(*) AS borrow_count
                 FROM borrow_records WHERE deleted_at IS NULL $dateFilter
                 GROUP BY borrower_name, borrower_type ORDER BY borrow_count DESC LIMIT 10"
            );
            $st->execute($params);
            jsonResponse($st->fetchAll());
            break;

        case 'daily_borrows':
            $days = (int)($_GET['days'] ?? 30);
            $params = [$days];
            $dateFilter = dateRangeWhere('date_taken', $dateFrom, $dateTo, $params);
            $st = $db->prepare(
                "SELECT DATE(date_taken) AS day, COUNT(*) AS count
                 FROM borrow_records WHERE deleted_at IS NULL
                   AND date_taken >= DATE_SUB(CURDATE(), INTERVAL ? DAY) $dateFilter
                 GROUP BY DATE(date_taken) ORDER BY day ASC"
            );
            $st->execute($params);
            jsonResponse($st->fetchAll());
            break;

        case 'overdue_by_class':
            $rpS = (int)getSetting('return_period_student', getSetting('return_period', '14'));
            $st = $db->prepare(
                "SELECT s.class, COUNT(*) AS overdue_count
                 FROM borrow_records br
                 LEFT JOIN students s ON s.name = br.borrower_name
                 WHERE br.status='taken' AND br.deleted_at IS NULL
                   AND br.date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY)
                   AND br.borrower_type = 'student'
                 GROUP BY s.class ORDER BY overdue_count DESC"
            );
            $st->execute([$rpS]);
            jsonResponse($st->fetchAll());
            break;

        case 'overdue_by_dept':
            $rpT = (int)getSetting('return_period_teacher', getSetting('return_period', '14'));
            $st = $db->prepare(
                "SELECT t.department, COUNT(*) AS overdue_count
                 FROM borrow_records br
                 LEFT JOIN teachers t ON t.id = br.borrower_id
                 WHERE br.status='taken' AND br.deleted_at IS NULL
                   AND br.date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY)
                   AND br.borrower_type = 'teacher'
                 GROUP BY t.department ORDER BY overdue_count DESC"
            );
            $st->execute([$rpT]);
            jsonResponse($st->fetchAll());
            break;

        case 'monthly':
            $params = [];
            $dateFilter = dateRangeWhere('date_taken', $dateFrom, $dateTo, $params);
            $st = $db->prepare(
                "SELECT DATE_FORMAT(date_taken,'%Y-%m') AS month, COUNT(*) AS count
                 FROM borrow_records WHERE deleted_at IS NULL $dateFilter
                 GROUP BY month ORDER BY month DESC LIMIT 12"
            );
            $st->execute($params);
            jsonResponse(array_reverse($st->fetchAll()));
            break;

        // ── NEW: Peak borrowing hours ───────────────────────
        case 'peak_hours':
            $params = [];
            $dateFilter = dateRangeWhere('date_taken', $dateFrom, $dateTo, $params);
            $st = $db->prepare(
                "SELECT HOUR(date_taken) AS hour, COUNT(*) AS count
                 FROM borrow_records WHERE deleted_at IS NULL $dateFilter
                 GROUP BY HOUR(date_taken) ORDER BY hour ASC"
            );
            $st->execute($params);
            $rows = $st->fetchAll();
            // Fill in missing hours with 0
            $hourMap = array_fill(0, 24, 0);
            foreach ($rows as $r) { $hourMap[(int)$r['hour']] = (int)$r['count']; }
            $result = [];
            for ($h = 6; $h <= 20; $h++) {
                $label = ($h < 12 ? $h . ' AM' : ($h === 12 ? '12 PM' : ($h - 12) . ' PM'));
                $result[] = ['hour' => $h, 'label' => $label, 'count' => $hourMap[$h]];
            }
            jsonResponse($result);
            break;

        // ── NEW: Peak borrowing days ────────────────────────
        case 'peak_days':
            $params = [];
            $dateFilter = dateRangeWhere('date_taken', $dateFrom, $dateTo, $params);
            $st = $db->prepare(
                "SELECT DAYOFWEEK(date_taken) AS dow, COUNT(*) AS count
                 FROM borrow_records WHERE deleted_at IS NULL $dateFilter
                 GROUP BY DAYOFWEEK(date_taken) ORDER BY dow ASC"
            );
            $st->execute($params);
            $rows = $st->fetchAll();
            $dayNames = [1=>'Sunday',2=>'Monday',3=>'Tuesday',4=>'Wednesday',5=>'Thursday',6=>'Friday',7=>'Saturday'];
            $dayMap = array_fill(1, 7, 0);
            foreach ($rows as $r) { $dayMap[(int)$r['dow']] = (int)$r['count']; }
            $result = [];
            foreach ($dayMap as $d => $c) { $result[] = ['day' => $dayNames[$d], 'count' => $c]; }
            jsonResponse($result);
            break;

        // ── NEW: Borrows by type with date range ────────────
        case 'borrows_by_type':
            $params = [];
            $dateFilter = dateRangeWhere('date_taken', $dateFrom, $dateTo, $params);
            $st = $db->prepare(
                "SELECT borrower_type, COUNT(*) AS count
                 FROM borrow_records WHERE deleted_at IS NULL $dateFilter
                 GROUP BY borrower_type"
            );
            $st->execute($params);
            jsonResponse($st->fetchAll());
            break;

        default:
            jsonResponse(['error' => 'Unknown type'], 400);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
