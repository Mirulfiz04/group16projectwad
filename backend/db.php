<?php
/* db.php — One PDO connection for the whole backend.
   - Connects to MySQL (creating the database if needed).
   - Falls back to SQLite if MySQL is unavailable and the config allows it.
   - Creates the tables and seeds the menu from data/menu.json on first run. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg  = require __DIR__ . '/config.php';
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $driver = $cfg['driver'];
    try {
        if ($driver === 'mysql') {
            $m = $cfg['mysql'];
            $pdo = new PDO("mysql:host={$m['host']};port={$m['port']};charset=utf8mb4", $m['user'], $m['pass'], $opts);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$m['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$m['dbname']}`");
        } else {
            $pdo = new PDO('sqlite:' . $cfg['sqlite_path'], null, null, $opts);
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    } catch (Throwable $e) {
        if ($driver === 'mysql' && !empty($cfg['auto_fallback_to_sqlite'])) {
            $pdo = new PDO('sqlite:' . $cfg['sqlite_path'], null, null, $opts);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $driver = 'sqlite';
        } else {
            throw $e;
        }
    }

    ensure_schema($pdo, $driver);
    seed_menu_if_empty($pdo);
    return $pdo;
}

function ensure_schema(PDO $pdo, string $driver): void
{
    $autoId = $driver === 'mysql'
        ? 'INT AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id            VARCHAR(40)  PRIMARY KEY,
        cat           VARCHAR(40),
        name          VARCHAR(120),
        price         DECIMAL(8,2),
        emoji         VARCHAR(16),
        descr         TEXT,
        img           VARCHAR(200),
        popular       INT DEFAULT 0,
        variants_json TEXT,
        addons_json   TEXT,
        sort_order    INT DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id               VARCHAR(20) PRIMARY KEY,
        order_type       VARCHAR(10) DEFAULT 'walkin',
        table_no         VARCHAR(10),
        customer_name    VARCHAR(120),
        customer_email   VARCHAR(100),
        customer_phone   VARCHAR(20),
        delivery_address TEXT,
        note             TEXT,
        subtotal         DECIMAL(10,2),
        tax              DECIMAL(10,2),
        total            DECIMAL(10,2),
        status           VARCHAR(20) DEFAULT 'pending',
        created_at       VARCHAR(25),
        updated_at       VARCHAR(25)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id           $autoId,
        order_id     VARCHAR(20),
        item_id      VARCHAR(40),
        name         VARCHAR(120),
        variant_name VARCHAR(80),
        addons_text  VARCHAR(255),
        notes        VARCHAR(255),
        unit_price   DECIMAL(8,2),
        qty          INT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id             $autoId,
        order_id       VARCHAR(20),
        payment_method VARCHAR(20),
        payment_detail VARCHAR(100),
        amount         DECIMAL(10,2),
        status         VARCHAR(20) DEFAULT 'success',
        paid_at        VARCHAR(25)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS staff (
        id       $autoId,
        name     VARCHAR(100) NOT NULL,
        email    VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role     VARCHAR(20)  NOT NULL
    )");
}

function seed_menu_if_empty(PDO $pdo): void
{
    $count = (int) $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
    if ($count > 0) return;

    $jsonPath = __DIR__ . '/../data/menu.json';
    if (!is_file($jsonPath)) return;          // nothing to seed from
    $data = json_decode(file_get_contents($jsonPath), true);
    $menu = $data['MENU'] ?? [];

    $stmt = $pdo->prepare("INSERT INTO menu_items
        (id, cat, name, price, emoji, descr, img, popular, variants_json, addons_json, sort_order)
        VALUES (:id,:cat,:name,:price,:emoji,:descr,:img,:popular,:variants,:addons,:sort)");

    $i = 0;
    foreach ($menu as $m) {
        $stmt->execute([
            ':id'       => $m['id'],
            ':cat'      => $m['cat'] ?? '',
            ':name'     => $m['name'] ?? '',
            ':price'    => $m['price'] ?? 0,
            ':emoji'    => $m['emoji'] ?? '',
            ':descr'    => $m['desc'] ?? '',
            ':img'      => $m['img'] ?? null,
            ':popular'  => !empty($m['popular']) ? 1 : 0,
            ':variants' => isset($m['variants']) ? json_encode($m['variants']) : null,
            ':addons'   => isset($m['addons'])   ? json_encode($m['addons'])   : null,
            ':sort'     => $i++,
        ]);
    }
}
