<?php
/**
 * system_health.php – Database statistics and system health
 * GET → returns DB stats, table counts, sizes
 */
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);

$db = getDB();

try {
    $stats = [];

    // Table row counts
    $tables = [
        'teachers' => 'Teachers',
        'students' => 'Students',
        'reading_club_members' => 'Reading Club Members',
        'books' => 'Books',
        'borrow_records' => 'Borrow Records',
        'archive_records' => 'Archive Records',
        'audit_log' => 'Audit Log',
        'overdue_blacklist' => 'Blacklisted Members',
        'overdue_notifications' => 'Overdue Notifications',
        'system_backups' => 'Backups'
    ];

    $tableCounts = [];
    foreach ($tables as $table => $label) {
        try {
            $st = $db->query("SELECT COUNT(*) FROM $table");
            $tableCounts[] = [
                'table' => $table,
                'label' => $label,
                'count' => (int)$st->fetchColumn()
            ];
        } catch (PDOException $e) {
            $tableCounts[] = [
                'table' => $table,
                'label' => $label,
                'count' => 0,
                'error' => 'Table not found'
            ];
        }
    }
    $stats['tables'] = $tableCounts;

    // Database size
    try {
        $st = $db->query(
            "SELECT SUM(data_length + index_length) as total_size
             FROM information_schema.tables
             WHERE table_schema = DATABASE()"
        );
        $row = $st->fetch();
        $stats['db_size_bytes'] = (int)($row['total_size'] ?? 0);
        $stats['db_size_human'] = formatBytes((int)($row['total_size'] ?? 0));
    } catch (PDOException $e) {
        $stats['db_size_bytes'] = 0;
        $stats['db_size_human'] = 'Unknown';
    }

    // Active borrows
    try {
        $st = $db->query("SELECT COUNT(*) FROM borrow_records WHERE status='taken' AND deleted_at IS NULL");
        $stats['active_borrows'] = (int)$st->fetchColumn();
    } catch (PDOException $e) { $stats['active_borrows'] = 0; }

    // Overdue count
    try {
        $rpT = (int)getSetting('return_period_teacher', '30');
        $rpS = (int)getSetting('return_period_student', '14');
        $rpC = (int)getSetting('return_period_reading_club', '14');
        $st = $db->prepare(
            "SELECT COUNT(*) FROM borrow_records
             WHERE status='taken' AND deleted_at IS NULL AND (
                (borrower_type='teacher' AND date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
                OR (borrower_type='student' AND date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
                OR (borrower_type='reading_club' AND date_taken <= DATE_SUB(NOW(), INTERVAL ? DAY))
             )"
        );
        $st->execute([$rpT, $rpS, $rpC]);
        $stats['overdue_count'] = (int)$st->fetchColumn();
    } catch (PDOException $e) { $stats['overdue_count'] = 0; }

    // Today's borrows
    try {
        $st = $db->query("SELECT COUNT(*) FROM borrow_records WHERE DATE(date_taken) = CURDATE() AND deleted_at IS NULL");
        $stats['today_borrows'] = (int)$st->fetchColumn();
    } catch (PDOException $e) { $stats['today_borrows'] = 0; }

    // Blacklisted count
    try {
        $st = $db->query("SELECT COUNT(*) FROM overdue_blacklist WHERE is_active=1 AND (expires_at IS NULL OR expires_at > NOW())");
        $stats['blacklisted_count'] = (int)$st->fetchColumn();
    } catch (PDOException $e) { $stats['blacklisted_count'] = 0; }

    // PHP & MySQL info
    $stats['php_version'] = phpversion();
    try {
        $stats['mysql_version'] = $db->query("SELECT VERSION()")->fetchColumn();
    } catch (PDOException $e) { $stats['mysql_version'] = 'Unknown'; }

    $stats['app_version'] = getSetting('app_version', '6.0.0');
    $stats['schema_version'] = getSetting('schema_version', '6');

    jsonResponse($stats);

} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

function formatBytes(int $bytes): string {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
