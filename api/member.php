<?php
/**
 * member.php – Unified member lookup across all types
 * GET ?type=teacher|student|reading_club&id=X  → single member by DB id
 * GET ?type=...&member_id=X                    → lookup by member's school ID
 * GET ?type=...&q=X                            → search by name
 * GET ?type=...&id=X&history=1                 → borrow history for member
 */
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);

$type = $_GET['type'] ?? '';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q    = $_GET['q'] ?? '';
$history = isset($_GET['history']);

try {
    // ── Borrow history for a member ─────────────────────────
    if ($history && $id && $type) {
        $limit  = min((int)($_GET['limit'] ?? 50), 200);
        $offset = (int)($_GET['offset'] ?? 0);
        $st = $db->prepare(
            "SELECT br.*, DATEDIFF(COALESCE(br.return_date, NOW()), br.date_taken) AS days_stayed
             FROM borrow_records br
             WHERE br.borrower_type=? AND br.borrower_id=? AND br.deleted_at IS NULL
             ORDER BY br.date_taken DESC LIMIT ? OFFSET ?"
        );
        $st->execute([$type, $id, $limit, $offset]);
        $rows = $st->fetchAll();

        $cst = $db->prepare(
            "SELECT COUNT(*) FROM borrow_records
             WHERE borrower_type=? AND borrower_id=? AND deleted_at IS NULL"
        );
        $cst->execute([$type, $id]);
        jsonResponse(['data' => $rows, 'total' => (int)$cst->fetchColumn()]);
    }

    // ── Single member by ID ─────────────────────────────────
    if ($id && $type) {
        $table = memberTable($type);
        if (!$table) jsonResponse(['error' => 'Invalid type'], 422);
        $st = $db->prepare("SELECT * FROM $table WHERE id=?");
        $st->execute([$id]);
        $member = $st->fetch();
        if (!$member) jsonResponse(['error' => 'Member not found'], 404);
        $member['member_type'] = $type;
        // Add borrow stats
        $bst = $db->prepare(
            "SELECT COUNT(*) as total_borrows,
                    SUM(CASE WHEN status='taken' THEN 1 ELSE 0 END) as active_borrows
             FROM borrow_records WHERE borrower_type=? AND borrower_id=? AND deleted_at IS NULL"
        );
        $bst->execute([$type, $id]);
        $stats = $bst->fetch();
        $member['total_borrows'] = (int)($stats['total_borrows'] ?? 0);
        $member['active_borrows'] = (int)($stats['active_borrows'] ?? 0);
        jsonResponse($member);
    }

    // ── Search across type ──────────────────────────────────
    if ($q && $type) {
        $table = memberTable($type);
        if (!$table) jsonResponse(['error' => 'Invalid type'], 422);
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $st = $db->prepare("SELECT * FROM $table WHERE name LIKE ? ORDER BY name ASC LIMIT ?");
        $st->execute(['%' . $q . '%', $limit]);
        jsonResponse($st->fetchAll());
    }

    // ── Search across ALL types ─────────────────────────────
    if ($q && !$type) {
        $results = [];
        foreach (['teacher' => 'teachers', 'student' => 'students', 'reading_club' => 'reading_club_members'] as $t => $tbl) {
            try {
                $st = $db->prepare("SELECT *, '$t' as member_type FROM $tbl WHERE name LIKE ? ORDER BY name ASC LIMIT 10");
                $st->execute(['%' . $q . '%']);
                $results = array_merge($results, $st->fetchAll());
            } catch (PDOException $e) { /* table may not exist yet */ }
        }
        jsonResponse($results);
    }

    jsonResponse(['error' => 'Provide type+id or type+q parameter'], 422);

} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}

function memberTable(string $type): ?string {
    $map = ['teacher' => 'teachers', 'student' => 'students', 'reading_club' => 'reading_club_members'];
    return $map[$type] ?? null;
}
