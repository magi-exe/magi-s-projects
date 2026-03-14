<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $q      = $_GET['q'] ?? '';
            $cat    = $_GET['category'] ?? '';
            $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $limit  = min((int)($_GET['limit'] ?? 50), 200);
            $offset = (int)($_GET['offset'] ?? 0);

            if ($id) {
                $st = $db->prepare("SELECT b.*, 
                    (SELECT COUNT(*) FROM borrow_records br WHERE br.book_code=b.code AND br.status='taken' AND br.deleted_at IS NULL) AS borrowed_count
                    FROM books b WHERE b.id=?");
                $st->execute([$id]); jsonResponse($st->fetch() ?: []);
            }

            $where = []; $params = [];
            if ($q)   { $where[] = '(b.title LIKE ? OR b.code LIKE ? OR b.author LIKE ?)';
                        $params  = array_merge($params, ['%'.$q.'%','%'.$q.'%','%'.$q.'%']); }
            if ($cat) { $where[] = 'b.category=?'; $params[] = $cat; }
            $wSql = $where ? 'WHERE '.implode(' AND ',$where) : '';

            $total = $db->prepare("SELECT COUNT(*) FROM books b $wSql");
            $total->execute($params);
            $totalCount = (int)$total->fetchColumn();

            $st = $db->prepare(
                "SELECT b.*,
                 (SELECT COUNT(*) FROM borrow_records br WHERE br.book_code=b.code AND br.status='taken' AND br.deleted_at IS NULL) AS borrowed_count
                 FROM books b $wSql ORDER BY b.title ASC LIMIT ? OFFSET ?"
            );
            $st->execute(array_merge($params, [$limit, $offset]));
            jsonResponse(['data' => $st->fetchAll(), 'total' => $totalCount]);
            break;

        case 'POST':
            $data  = json_decode(file_get_contents('php://input'), true) ?? [];
            $title = trim($data['title'] ?? '');
            $code  = trim($data['code']  ?? '');
            if (!$title || !$code) jsonResponse(['error' => 'Title and code required'], 422);

            $chk = $db->prepare("SELECT id FROM books WHERE code=?");
            $chk->execute([$code]); $existing = $chk->fetch();
            if ($existing) jsonResponse(['error' => 'Book code already exists', 'id' => $existing['id']], 409);

            $st = $db->prepare(
                "INSERT INTO books(title,code,author,isbn,category,shelf_location,quantity,description)
                 VALUES(?,?,?,?,?,?,?,?)"
            );
            $st->execute([$title,$code,
                $data['author'] ?? null, $data['isbn'] ?? null,
                $data['category'] ?? null, $data['shelf_location'] ?? null,
                (int)($data['quantity'] ?? 1), $data['description'] ?? null]);
            jsonResponse(['id' => $db->lastInsertId(), 'ok' => true], 201);
            break;

        case 'PUT':
            $id   = (int)($_GET['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $fields = []; $vals = [];
            foreach (['title','code','author','isbn','category','shelf_location','quantity','description'] as $f) {
                if (array_key_exists($f,$data)) { $fields[] = "$f=?"; $vals[] = $data[$f]; }
            }
            if (!$fields) jsonResponse(['ok' => true]);
            $vals[] = $id;
            $db->prepare("UPDATE books SET ".implode(',',$fields)." WHERE id=?")->execute($vals);
            jsonResponse(['ok' => true]);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required'], 422);
            $db->prepare("DELETE FROM books WHERE id=?")->execute([$id]);
            jsonResponse(['ok' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
