<?php
/* /api/mark_paid.php
   POST { id }  -> mark an order's payment as paid.
   Used by the staff view to settle a "pay at counter" order once the
   customer has paid. Returns { ok, id, payment_status }.                 */

require __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['error' => 'method_not_allowed'], 405);
}

$b  = json_body();
$id = trim($b['id'] ?? '');
if ($id === '') json_out(['error' => 'missing_id'], 400);

$pdo = db();

// Order must exist.
$chk = $pdo->prepare("SELECT total FROM orders WHERE id = ?");
$chk->execute([$id]);
$total = $chk->fetchColumn();
if ($total === false) json_out(['error' => 'not_found'], 404);

$now = date('Y-m-d H:i:s');

// Update the latest payment row, or create one if the order has none.
$find = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$find->execute([$id]);
$payId = $find->fetchColumn();

if ($payId) {
    $pdo->prepare("UPDATE payments SET status = 'paid', paid_at = ? WHERE id = ?")
        ->execute([$now, $payId]);
} else {
    $pdo->prepare("INSERT INTO payments
        (order_id, payment_method, payment_detail, amount, status, paid_at)
        VALUES (?, 'counter', 'Pay at counter', ?, 'paid', ?)")
        ->execute([$id, (float) $total, $now]);
}

json_out(['ok' => true, 'id' => $id, 'payment_status' => 'paid']);
