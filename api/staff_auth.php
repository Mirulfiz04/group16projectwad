<?php
/* /api/staff_auth.php
   POST { action: 'login', email, password } -> { ok, role, name }
   GET                                       -> { ok: true }           */

require __DIR__ . '/_bootstrap.php';

$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    json_out(['ok' => true]);
}

// POST only
$b      = json_body();
$action = $b['action'] ?? '';

if ($action === 'login') {
    $email    = trim($b['email']    ?? '');
    $password = trim($b['password'] ?? '');

    if (!$email || !$password) {
        json_out(['error' => 'missing_fields'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $staff = $stmt->fetch();

    if (!$staff) {
        json_out(['error' => 'invalid_credentials'], 401);
    }

    if (!password_verify($password, $staff['password'])) {
        json_out(['error' => 'invalid_credentials'], 401);
    }

    json_out([
        'ok'   => true,
        'role' => $staff['role'],
        'name' => $staff['name'],
    ]);
}

json_out(['error' => 'unknown_action'], 400);