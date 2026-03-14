<?php
require_once __DIR__ . '/../db_connect.php';

$db           = getDB();
$schoolName   = getSetting('school_name', 'Comboni Library');
$schemaVer    = getSetting('schema_version', '1');
$appVer       = getSetting('app_version', '1.0.0');
$now          = date('Ymd-Hi');
$filename     = "Comboni-Library-$now.csv";

// Build query
$where  = ['deleted_at IS NULL'];
$params = [];

if (!empty($_GET['status']))        { $where[] = 'status=?';            $params[] = $_GET['status']; }
if (!empty($_GET['borrower_type'])) { $where[] = 'borrower_type=?';     $params[] = $_GET['borrower_type']; }
if (!empty($_GET['date_from']))     { $where[] = 'date_taken >= ?';      $params[] = $_GET['date_from']; }
if (!empty($_GET['date_to']))       { $where[] = 'date_taken <= ?';      $params[] = $_GET['date_to'].' 23:59:59'; }

$archive = !empty($_GET['archive']);
$table   = $archive ? 'archive_records' : 'borrow_records';

$sql = "SELECT id, borrower_type, borrower_name, book_title, book_code, date_taken,
               status, return_date, return_notes, notes, created_at, updated_at
        FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY date_taken DESC";
$st = $db->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');
// Metadata header
fputcsv($out, ["# schema_version=$schemaVer", "app_version=$appVer",
               "export_date=" . date('Y-m-d H:i:s'), "school=$schoolName"]);
// Column headers
fputcsv($out, ['id','borrower_type','borrower_name','book_title','book_code',
               'date_taken','status','return_date','return_notes','notes',
               'created_at','updated_at']);
foreach ($rows as $row) fputcsv($out, $row);
fclose($out);
exit;
