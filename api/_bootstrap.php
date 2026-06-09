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

// Turn any uncaught error into a clean JSON 500 instead of an HTML page.
set_exception_handler(function (Throwable $e) {
    json_out(['error' => 'server_error', 'message' => $e->getMessage()], 500);
});
