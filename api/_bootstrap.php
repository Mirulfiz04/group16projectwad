<?php
/* _bootstrap.php — shared setup for every API endpoint:
   JSON headers, CORS, error handling, and helpers. */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Browsers send a preflight OPTIONS request for POST — answer it and stop.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../backend/db.php';

/** Send data as JSON and stop. */
function json_out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Read and decode a JSON request body. */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Append a status change to an order's history (with a server timestamp). */
function record_status(PDO $pdo, string $orderId, string $status): void
{
    $pdo->prepare("INSERT INTO order_status_history (order_id, status, at)
                   VALUES (?, ?, ?)")
        ->execute([$orderId, $status, date('Y-m-d H:i:s')]);
}

/** Full status history (oldest first) for an order. */
function fetch_status_history(PDO $pdo, string $orderId): array
{
    $s = $pdo->prepare("SELECT status, at FROM order_status_history
                        WHERE order_id = ? ORDER BY id ASC");
    $s->execute([$orderId]);
    return $s->fetchAll();
}

// Turn any uncaught error into a clean JSON 500 instead of an HTML page.
set_exception_handler(function (Throwable $e) {
    json_out(['error' => 'server_error', 'message' => $e->getMessage()], 500);
});
