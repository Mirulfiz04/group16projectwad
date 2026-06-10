<?php
/* /api/payment.php
   POST { order_id, payment_method, payment_detail, amount }
        -> saves payment record, returns { ok, payment_id }
   GET  ?order_id=SZxxxx
        -> returns payment record for that order               */

require __DIR__ . '/_bootstrap.php';

$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $b = json_body();

    $order_id       = trim($b['order_id']       ?? '');
    $payment_method = trim($b['payment_method'] ?? '');
    $payment_detail = trim($b['payment_detail'] ?? '');
    $amount         = (float) ($b['amount']     ?? 0);

    if (!$order_id || !$payment_method || !$payment_detail || $amount <= 0) {
        json_out(['error' => 'missing_fields'], 400);
    }

    if (!in_array($payment_method, ['fpx', 'ewallet'], true)) {
        json_out(['error' => 'invalid_method'], 400);
    }

    // Confirm order exists
    $chk = $pdo->prepare("SELECT id, total FROM orders WHERE id = ?");
    $chk->execute([$order_id]);
    $order = $chk->fetch();
    if (!$order) json_out(['error' => 'order_not_found'], 404);

    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO payments
        (order_id, payment_method, payment_detail, amount, status, paid_at)
        VALUES (?, ?, ?, ?, 'success', ?)");
    $stmt->execute([$order_id, $payment_method, $payment_detail, $amount, $now]);

    $payment_id = $pdo->lastInsertId();

    // Update order status to pending (now paid, waiting kitchen)
    $pdo->prepare("UPDATE orders SET status = 'pending', updated_at = ? WHERE id = ?")
        ->execute([$now, $order_id]);

    json_out(['ok' => true, 'payment_id' => $payment_id]);
}

// GET
$order_id = trim($_GET['order_id'] ?? '');
if (!$order_id) json_out(['error' => 'missing_order_id'], 400);

$stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY paid_at DESC LIMIT 1");
$stmt->execute([$order_id]);
$payment = $stmt->fetch();
if (!$payment) json_out(['error' => 'not_found'], 404);

json_out($payment);