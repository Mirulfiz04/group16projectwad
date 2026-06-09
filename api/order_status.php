<?php
/* /api/order_status.php
   GET  ?id=SZxxxx           -> { id, status, table_no, total, created_at, updated_at }
                               (the tracking page polls this)
   POST { id, status }       -> update the status
                               (the kitchen/staff view calls this)
   Valid statuses: pending -> preparing -> ready -> completed             */

require __DIR__ . '/_bootstrap.php';

const VALID = ['pending', 'preparing', 'ready', 'completed'];
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $b = json_body();
    $id = $b['id'] ?? '';
    $status = $b['status'] ?? '';
    if (!in_array($status, VALID, true)) json_out(['error' => 'bad_status'], 400);

    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$status, date('Y-m-d H:i:s'), $id]);
    if ($stmt->rowCount() === 0) {
        // rowCount can be 0 if status was unchanged; confirm the order exists.
        $chk = $pdo->prepare("SELECT 1 FROM orders WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) json_out(['error' => 'not_found'], 404);
    }
    json_out(['ok' => true, 'id' => $id, 'status' => $status]);
}

// GET
$id = $_GET['id'] ?? '';
$stmt = $pdo->prepare("SELECT id, status, table_no, total, created_at, updated_at FROM orders WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) json_out(['error' => 'not_found'], 404);
json_out($row);
