<?php
/**
 * backup.php – Database backup & restore
 * GET                     → list backups
 * POST  ?action=create    → create new backup
 * POST  ?action=restore   → restore from backup (multipart file upload)
 * DELETE ?id=X            → delete a backup file
 */
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    jsonResponse(['error' => 'Admin only'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$backupDir = __DIR__ . '/../uploads/backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            // List backups
            try {
                $st = $db->query("SELECT * FROM system_backups ORDER BY created_at DESC LIMIT 50");
                jsonResponse(['data' => $st->fetchAll()]);
            } catch (PDOException $e) {
                // Table may not exist yet
                jsonResponse(['data' => []]);
            }
            break;

        case 'POST':
            $action = $_GET['action'] ?? ($_POST['action'] ?? '');

            if ($action === 'create') {
                $tables = ['teachers', 'students', 'reading_club_members', 'books',
                           'borrow_records', 'archive_records', 'audit_log', 'settings',
                           'overdue_blacklist', 'overdue_notifications'];
                $filename = 'comboni_backup_' . date('Ymd_His') . '.sql';
                $filepath = $backupDir . '/' . $filename;
                $sql = "-- Comboni Library Backup\n-- Created: " . date('Y-m-d H:i:s') . "\n\n";

                foreach ($tables as $table) {
                    try {
                        $st = $db->query("SELECT * FROM $table");
                        $rows = $st->fetchAll();
                        if (empty($rows)) continue;

                        $sql .= "-- Table: $table\n";
                        $sql .= "DELETE FROM `$table`;\n";

                        foreach ($rows as $row) {
                            $vals = array_map(function($v) use ($db) {
                                return $v === null ? 'NULL' : $db->quote($v);
                            }, array_values($row));
                            $cols = '`' . implode('`,`', array_keys($row)) . '`';
                            $sql .= "INSERT INTO `$table` ($cols) VALUES (" . implode(',', $vals) . ");\n";
                        }
                        $sql .= "\n";
                    } catch (PDOException $e) {
                        // Table doesn't exist, skip
                    }
                }

                file_put_contents($filepath, $sql);
                $size = filesize($filepath);

                try {
                    $db->prepare(
                        "INSERT INTO system_backups (filename, size_bytes, tables_included) VALUES (?, ?, ?)"
                    )->execute([$filename, $size, implode(',', $tables)]);
                } catch (PDOException $e) {
                    // system_backups table may not exist
                }

                jsonResponse(['ok' => true, 'filename' => $filename, 'size' => $size]);
            }

            if ($action === 'restore') {
                if (empty($_FILES['backup_file'])) {
                    jsonResponse(['error' => 'No file uploaded'], 422);
                }
                $file = $_FILES['backup_file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    jsonResponse(['error' => 'Upload failed'], 422);
                }
                $content = file_get_contents($file['tmp_name']);
                if (strpos($content, '-- Comboni Library Backup') === false) {
                    jsonResponse(['error' => 'Invalid backup file'], 422);
                }

                // Execute SQL statements
                $statements = array_filter(
                    array_map('trim', explode(";\n", $content)),
                    function($s) { return $s && !str_starts_with($s, '--'); }
                );
                $executed = 0;
                $errors = 0;
                foreach ($statements as $stmt) {
                    try {
                        $db->exec($stmt);
                        $executed++;
                    } catch (PDOException $e) {
                        $errors++;
                    }
                }
                jsonResponse(['ok' => true, 'executed' => $executed, 'errors' => $errors]);
            }

            jsonResponse(['error' => 'Invalid action'], 422);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            try {
                $st = $db->prepare("SELECT filename FROM system_backups WHERE id=?");
                $st->execute([$id]);
                $row = $st->fetch();
                if ($row) {
                    $filepath = $backupDir . '/' . $row['filename'];
                    if (file_exists($filepath)) unlink($filepath);
                    $db->prepare("DELETE FROM system_backups WHERE id=?")->execute([$id]);
                }
            } catch (PDOException $e) {}
            jsonResponse(['ok' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
