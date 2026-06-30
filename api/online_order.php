<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/_bootstrap.php';
error_log(print_r(debug_backtrace(), true));
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../src/Exception.php';
require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';

$cfg    = require __DIR__ . '/../backend/config.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'POST') json_out(['error' => 'method_not_allowed'], 405);

$body  = json_body();
$items = $body['items'] ?? [];

if (!is_array($items) || count($items) === 0) json_out(['error' => 'empty_cart'], 400);

$name    = trim($body['customer_name']    ?? '');
$email   = trim($body['customer_email']   ?? '');
$phone   = trim($body['customer_phone']   ?? '');
$address = trim($body['delivery_address'] ?? '');
$dtype   = trim($body['delivery_type']    ?? 'delivery');
$note    = trim($body['note']             ?? '');
$pay_method = trim($body['payment_method'] ?? '');
$pay_detail = trim($body['payment_detail'] ?? '');

if (!$name || !$email || !$phone)       json_out(['error' => 'missing_customer_details'], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['error' => 'invalid_email'], 400);
if ($dtype === 'delivery' && !$address) json_out(['error' => 'missing_address'], 400);
if (!in_array($pay_method, ['fpx', 'ewallet'], true)) json_out(['error' => 'invalid_payment_method'], 400);

// Recompute totals server-side
$subtotal = 0.0;
$lines    = [];
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
        'item_id'      => $it['id']   ?? '',
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

// Insert order
$pdo->prepare("INSERT INTO orders
    (id, order_type, table_no, customer_name, customer_email, customer_phone,
     delivery_address, note, subtotal, tax, total, status, created_at, updated_at)
    VALUES (?, 'online', NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)")
    ->execute([
        $id, $name, $email, $phone,
        $dtype === 'delivery' ? $address : null,
        $note, round($subtotal, 2), $tax, $total, $now, $now,
    ]);

// Insert order items
$li = $pdo->prepare("INSERT INTO order_items
    (order_id, item_id, name, variant_name, addons_text, notes, unit_price, qty)
    VALUES (?,?,?,?,?,?,?,?)");
foreach ($lines as $l) {
    $li->execute([$id, $l['item_id'], $l['name'], $l['variant_name'],
                  $l['addons_text'], $l['notes'], $l['unit_price'], $l['qty']]);
}

// Insert payment
$pdo->prepare("INSERT INTO payments
    (order_id, payment_method, payment_detail, amount, status, paid_at)
    VALUES (?, ?, ?, ?, 'success', ?)")
    ->execute([$id, $pay_method, $pay_detail, $total, $now]);

$payment_id = $pdo->lastInsertId();

// Start the status timeline.
record_status($pdo, $id, 'pending');

$pdo->commit();

// Send confirmation email
try {
    send_confirmation_email($email, $name, $id, $lines, $subtotal, $tax, $total);
} catch (Throwable $e) {
    // Email failed but order already saved
}

json_out([
    'ok'         => true,
    'id'         => $id,
    'payment_id' => $payment_id,
    'subtotal'   => round($subtotal, 2),
    'tax'        => $tax,
    'total'      => $total,
]);

/* ------------------------------------------------------------------ */
function send_confirmation_email(
    string $to, string $name, string $order_id,
    array $lines, float $subtotal, float $tax, float $total
): void {
    $subject = "Order Confirmed — Sup Tulang ZZ #{$order_id}";

    // Build item rows for email
    $item_rows = '';
    foreach ($lines as $l) {
        $label = $l['variant_name'] ?? '';
        $addons = $l['addons_text'] ?? '';
        $extra = trim(implode(' · ', array_filter([$label, $addons])));
        $line_total = number_format($l['unit_price'] * $l['qty'], 2);
        $item_rows .= "
        <tr>
          <td style='padding:8px 0;border-bottom:1px solid #eee'>
            {$l['qty']}× {$l['name']}" . ($extra ? " <span style='color:#888;font-size:13px'>({$extra})</span>" : "") . "
          </td>
          <td style='padding:8px 0;border-bottom:1px solid #eee;text-align:right;font-weight:700'>
            RM {$line_total}
          </td>
        </tr>";
    }

    $subtotal_fmt = number_format($subtotal, 2);
    $tax_fmt      = number_format($tax, 2);
    $total_fmt    = number_format($total, 2);

    $html = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px'>
  <div style='max-width:520px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)'>

    <!-- Header -->
    <div style='background:#d6342b;color:#fff;text-align:center;padding:28px 20px'>
      <div style='font-size:36px'>🍲</div>
      <h1 style='margin:8px 0 4px;font-size:22px'>SUP TULANG ZZ</h1>
      <p style='margin:0;font-size:13px;opacity:.85'>Order Confirmation</p>
    </div>

    <!-- Body -->
    <div style='padding:24px 24px 20px'>
      <p style='margin:0 0 6px;font-size:15px'>Hi <strong>{$name}</strong>,</p>
      <p style='margin:0 0 20px;color:#555;font-size:14px'>
        Your order has been confirmed and is being prepared. Thank you for ordering from us!
      </p>

      <div style='background:#fff8f8;border:1px solid #f0c0c0;border-radius:10px;padding:12px 16px;margin-bottom:20px'>
        <span style='font-size:13px;color:#888'>Order ID</span>
        <div style='font-size:22px;font-weight:800;color:#d6342b'>#{$order_id}</div>
      </div>

      <h3 style='margin:0 0 10px;font-size:14px;color:#888;text-transform:uppercase;letter-spacing:.05em'>Items Ordered</h3>
      <table style='width:100%;border-collapse:collapse;font-size:14px'>
        {$item_rows}
        <tr>
          <td style='padding:8px 0;color:#888'>Subtotal</td>
          <td style='padding:8px 0;text-align:right'>RM {$subtotal_fmt}</td>
        </tr>
        <tr>
          <td style='padding:8px 0;color:#888'>SST (6%)</td>
          <td style='padding:8px 0;text-align:right'>RM {$tax_fmt}</td>
        </tr>
        <tr style='font-size:16px;font-weight:800'>
          <td style='padding:10px 0;border-top:2px solid #eee'>Total</td>
          <td style='padding:10px 0;border-top:2px solid #eee;text-align:right;color:#d6342b'>RM {$total_fmt}</td>
        </tr>
      </table>
    </div>

    <!-- Footer -->
    <div style='background:#fafafa;border-top:1px solid #eee;text-align:center;padding:18px;font-size:13px;color:#888'>
      <strong style='color:#d6342b'>Sup Tulang ZZ</strong> · Pasir Gudang, Johor<br>
      Thank you for your order 🙏
    </div>
  </div>
</body>
</html>";

    $text = "Order Confirmed — Sup Tulang ZZ\n\n"
          . "Hi {$name},\n\n"
          . "Your order #{$order_id} has been confirmed.\n\n"
          . "Total: RM {$total_fmt}\n\n"
          . "Thank you for ordering from Sup Tulang ZZ!";

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;

    $mail->Username   = 'nurainfarahin4@gmail.com';
    $mail->Password   = 'iocjvqynbqlxvndm';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom(
        'nurainfarahin4@gmail.com',
        'Sup Tulang ZZ'
    );

    $mail->addAddress($to, $name);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $text;

    $mail->send();

} catch (Exception $e) {

    error_log(
        'Mailer Error: ' . $mail->ErrorInfo
    );
}
}