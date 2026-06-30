<?php
/* /api/orders.php
   POST  -> place a new order (used by cart.html). Body (JSON):
            { table, customerName, note, items:[ {id,name,emoji,img,
              variant:{name,price}|null, addons:[{name,price}], notes, qty,
              basePrice} ] }
            Returns: { ok, id, status, subtotal, tax, total }
   GET   -> list orders, newest first (used by the staff/kitchen view).
            ?id=SZxxxx returns a single order.                              */

require __DIR__ . '/_bootstrap.php';

$cfg    = require __DIR__ . '/../backend/config.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $body  = json_body();
    $items = $body['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        json_out(['error' => 'empty_cart'], 400);
    }

    // Recompute totals on the server (don't trust client math).
    $subtotal = 0.0;
    $lines = [];
    foreach ($items as $it) {
        $base   = isset($it['variant']['price']) ? (float) $it['variant']['price'] : (float) ($it['basePrice'] ?? 0);
        $addons = $it['addons'] ?? [];
        $addSum = 0.0;
        $addNames = [];
        foreach ($addons as $a) { $addSum += (float) $a['price']; $addNames[] = $a['name']; }
        $unit = $base + $addSum;
        $qty  = max(1, (int) ($it['qty'] ?? 1));
        $subtotal += $unit * $qty;

        $lines[] = [
            'item_id'      => $it['id'] ?? '',
            'name'         => $it['name'] ?? '',
            'variant_name' => $it['variant']['name'] ?? null,
            'addons_text'  => $addNames ? implode(', ', $addNames) : null,
            'notes'        => $it['notes'] ?? null,
            'unit_price'   => $unit,
            'qty'          => $qty,
        ];
    }
    $tax   = round($subtotal * (float) $cfg['tax_rate'], 2);
    $total = round($subtotal + $tax, 2);

    $id  = 'SZ' . substr((string) time(), -5) . rand(10, 99);
    $now = date('Y-m-d H:i:s');

    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO orders
        (id, table_no, customer_name, note, subtotal, tax, total, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $id,
            $body['table'] ?? '',
            $body['customerName'] ?? '',
            $body['note'] ?? '',
            round($subtotal, 2), $tax, $total,
            'pending', $now, $now,
        ]);

    $li = $pdo->prepare("INSERT INTO order_items
        (order_id, item_id, name, variant_name, addons_text, notes, unit_price, qty)
        VALUES (?,?,?,?,?,?,?,?)");
    foreach ($lines as $l) {
        $li->execute([$id, $l['item_id'], $l['name'], $l['variant_name'],
                      $l['addons_text'], $l['notes'], $l['unit_price'], $l['qty']]);
    }

    // Start the status timeline.
    record_status($pdo, $id, 'pending');

    // Record how the dine-in customer chose to pay (counter / online).
    // pay_status is 'paid' (online done) or 'unpaid' (pay at counter).
    $pay_method = trim($body['payment_method'] ?? '');   // counter | fpx | ewallet
    $pay_detail = trim($body['payment_detail'] ?? '');   // bank/wallet name, or "Pay at counter"
    $pay_status = trim($body['payment_status'] ?? '');   // paid | unpaid
    if ($pay_method !== '') {
        $paid_at = $pay_status === 'paid' ? $now : null;
        $pdo->prepare("INSERT INTO payments
            (order_id, payment_method, payment_detail, amount, status, paid_at)
            VALUES (?,?,?,?,?,?)")
            ->execute([$id, $pay_method, $pay_detail, $total,
                       $pay_status !== '' ? $pay_status : 'unpaid', $paid_at]);
    }

    $pdo->commit();

    json_out([
        'ok'             => true,
        'id'             => $id,
        'status'         => 'pending',
        'subtotal'       => round($subtotal, 2),
        'tax'            => $tax,
        'total'          => $total,
        'payment_method' => $pay_method,
        'payment_status' => $pay_status !== '' ? $pay_status : ($pay_method !== '' ? 'unpaid' : ''),
    ]);
}

// ---- GET: list orders (newest first), or one order by ?id= ----
$pdo = db();
$id  = $_GET['id'] ?? null;

if ($id) {
    $o = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $o->execute([$id]);
    $order = $o->fetch();
    if (!$order) json_out(['error' => 'not_found'], 404);
    $order['items']   = fetch_items($pdo, $id);
    $order['payment'] = fetch_payment($pdo, $id);
    json_out($order);
}

$orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC, id DESC")->fetchAll();
foreach ($orders as &$ord) {
    $ord['items']   = fetch_items($pdo, $ord['id']);
    $ord['payment'] = fetch_payment($pdo, $ord['id']);
}
json_out($orders);

function fetch_items(PDO $pdo, string $orderId): array
{
    $s = $pdo->prepare("SELECT item_id, name, variant_name, addons_text, notes, unit_price, qty
                        FROM order_items WHERE order_id = ?");
    $s->execute([$orderId]);
    return $s->fetchAll();
}

/* Latest payment for an order (method + paid/unpaid), or null if none recorded. */
function fetch_payment(PDO $pdo, string $orderId): ?array
{
    $s = $pdo->prepare("SELECT payment_method, payment_detail, status, paid_at
                        FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $s->execute([$orderId]);
    $p = $s->fetch();
    return $p ?: null;
}
