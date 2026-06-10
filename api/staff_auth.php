<?php
/* /api/staff_auth.php
   POST { action: 'login', email, password } -> { ok, role, name }
   POST { action: 'logout' }                 -> { ok }
   GET                                       -> { loggedIn, role, name }  */

session_start();
require __DIR__ . '/_bootstrap.php';

$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Seed default staff accounts if not yet present
$count = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
if ((int)$count === 0) {
    $accounts = [
        ['Kitchen Staff', 'kitchen@suptulangzz.com', 'kitchen123', 'kitchen'],
        ['Waiter',        'waiter@suptulangzz.com',  'waiter123',  'waiter'],
    ];
    $ins = $pdo->prepare("INSERT INTO staff (name, email, password, role) VALUES (?,?,?,?)");
    foreach ($accounts as $a) {
        $ins->execute([$a[0], $a[1], password_hash($a[2], PASSWORD_DEFAULT), $a[3]]);
    }
}

if ($method === 'GET') {
    if (!empty($_SESSION['staff_id'])) {
        json_out([
            'loggedIn' => true,
            'role'     => $_SESSION['staff_role'],
            'name'     => $_SESSION['staff_name'],
        ]);
    } else {
        json_out(['loggedIn' => false]);
    }
}

// POST
$b      = json_body();
$action = $b['action'] ?? '';

if ($action === 'logout') {
    session_destroy();
    json_out(['ok' => true]);
}

if ($action === 'login') {
    $email    = trim($b['email']    ?? '');
    $password = trim($b['password'] ?? '');

    if (!$email || !$password) json_out(['error' => 'missing_fields'], 400);

    $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $staff = $stmt->fetch();

    if (!$staff || !password_verify($password, $staff['password'])) {
        json_out(['error' => 'invalid_credentials'], 401);
    }

    $_SESSION['staff_id']   = $staff['id'];
    $_SESSION['staff_role'] = $staff['role'];
    $_SESSION['staff_name'] = $staff['name'];

    json_out([
        'ok'   => true,
        'role' => $staff['role'],
        'name' => $staff['name'],
    ]);
}

json_out(['error' => 'unknown_action'], 400);