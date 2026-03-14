<?php
require_once __DIR__ . "/../db_connect.php";
header('Content-Type: application/json');

$db = getDB();

// ── Phase 1: validate ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'validate') {
    if (empty($_FILES['csv'])) jsonResponse(['error' => 'No file uploaded'], 422);
    $tmp   = $_FILES['csv']['tmp_name'];
    $handle = fopen($tmp, 'r');
    if (!$handle) jsonResponse(['error' => 'Could not read file'], 500);

    $required = ['borrower_type','borrower_name','book_title','book_code','date_taken','status'];
    $errors   = [];
    $rows     = [];
    $dupes    = [];
    $line     = 0;
    $headers  = null;

    while (($cols = fgetcsv($handle)) !== false) {
        $line++;
        if (empty($cols) || (count($cols) === 1 && str_starts_with(trim($cols[0]), '#'))) continue;
        if ($headers === null) {
            $headers = array_map('trim', $cols);
            // Validate required headers
            $missing = array_diff($required, $headers);
            if ($missing) {
                fclose($handle);
                jsonResponse(['error' => 'Missing columns: ' . implode(', ', $missing), 'line' => $line]);
            }
            continue;
        }
        if (count($cols) !== count($headers)) {
            $errors[] = "Line $line: column count mismatch";
            continue;
        }
        $row = array_combine($headers, $cols);
        $rows[] = ['line' => $line, 'data' => $row];

        // Duplicate check
        $st = $db->prepare(
            "SELECT id FROM borrow_records WHERE book_code=? AND date_taken=? AND borrower_name=? AND deleted_at IS NULL"
        );
        $st->execute([$row['book_code'], $row['date_taken'], $row['borrower_name']]);
        if ($st->fetch()) {
            $dupes[] = ['line' => $line, 'borrower_name' => $row['borrower_name'],
                        'book_code' => $row['book_code'], 'date_taken' => $row['date_taken']];
        }
    }
    fclose($handle);

    // Save validated rows to session-like temp file
    $tmpKey = md5(uniqid('import', true));
    file_put_contents(UPLOAD_DIR . $tmpKey . '.json', json_encode($rows));

    jsonResponse([
        'ok'         => true,
        'total'      => count($rows),
        'duplicates' => $dupes,
        'errors'     => $errors,
        'import_key' => $tmpKey,
    ]);
}

// ── Phase 2: apply ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'apply') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $tmpKey   = $body['import_key'] ?? '';
    $strategy = $body['strategy'] ?? 'skip'; // skip|overwrite
    $tmpFile  = UPLOAD_DIR . $tmpKey . '.json';

    if (!$tmpKey || !file_exists($tmpFile)) jsonResponse(['error' => 'Invalid import key'], 422);

    $rows    = json_decode(file_get_contents($tmpFile), true);
    $inserted = 0; $skipped = 0; $overwritten = 0;

    $stCheck = $db->prepare(
        "SELECT id FROM borrow_records WHERE book_code=? AND date_taken=? AND borrower_name=? AND deleted_at IS NULL"
    );
    $stInsert = $db->prepare(
        "INSERT INTO borrow_records 
         (borrower_type,borrower_name,book_title,book_code,date_taken,status,return_date,return_notes,notes)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $stUpdate = $db->prepare(
        "UPDATE borrow_records SET status=?, return_date=?, return_notes=?, notes=? 
         WHERE book_code=? AND date_taken=? AND borrower_name=?"
    );

    $db->beginTransaction();
    try {
        foreach ($rows as $item) {
            $d = $item['data'];
            $stCheck->execute([$d['book_code'], $d['date_taken'], $d['borrower_name']]);
            $existing = $stCheck->fetch();
            if ($existing) {
                if ($strategy === 'overwrite') {
                    $stUpdate->execute([
                        $d['status'] ?? 'taken',
                        $d['return_date'] ?? null,
                        $d['return_notes'] ?? null,
                        $d['notes'] ?? null,
                        $d['book_code'], $d['date_taken'], $d['borrower_name'],
                    ]);
                    $overwritten++;
                } else { $skipped++; }
            } else {
                $stInsert->execute([
                    $d['borrower_type'] ?? 'student',
                    $d['borrower_name'],
                    $d['book_title'],
                    $d['book_code'],
                    $d['date_taken'],
                    $d['status'] ?? 'taken',
                    $d['return_date'] ?? null,
                    $d['return_notes'] ?? null,
                    $d['notes'] ?? null,
                ]);
                $inserted++;
            }
        }
        $db->commit();
        @unlink($tmpFile);
        jsonResponse(['ok' => true, 'inserted' => $inserted, 'skipped' => $skipped, 'overwritten' => $overwritten]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Invalid action'], 400);
