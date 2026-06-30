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

    // Read the current status first so we only log a real change.
    $cur = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $cur->execute([$id]);
    $old = $cur->fetchColumn();
    if ($old === false) json_out(['error' => 'not_found'], 404);

    $pdo->prepare("UPDATE orders SET status = ?, updated_at = ? WHERE id = ?")
        ->execute([$status, date('Y-m-d H:i:s'), $id]);

    if ($old !== $status) record_status($pdo, $id, $status);

    json_out(['ok' => true, 'id' => $id, 'status' => $status]);
}

// GET
$id = $_GET['id'] ?? '';
$stmt = $pdo->prepare("SELECT id, status, table_no, total, created_at, updated_at FROM orders WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) json_out(['error' => 'not_found'], 404);

// Include the latest payment so the tracking page can reflect "paid at counter".
$p = $pdo->prepare("SELECT payment_method, payment_detail, status, paid_at
                    FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$p->execute([$id]);
$row['payment'] = $p->fetch() ?: null;

// Real status timeline (oldest first) so the tracker shows accurate times.
$row['history'] = fetch_status_history($pdo, $id);

json_out($row);
