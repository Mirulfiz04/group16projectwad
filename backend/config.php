<?php
/* config.php — Database settings.
   ---------------------------------------------------------------------
   For the assignment you use MySQL (set up easily with XAMPP):
     1. Start Apache + MySQL in XAMPP.
     2. Put this whole project folder in  C:\xampp\htdocs\
     3. Open  http://localhost/suptulang-zz-order/index.html
   The tables + menu are created automatically on first API call.

   If MySQL can't be reached, the API falls back to a local SQLite file
   (backend/data.sqlite) so the app still runs for a quick demo.        */

return [
    'driver' => 'mysql',          // 'mysql' (assignment) or 'sqlite' (no setup)

    'mysql' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'dbname' => 'suptulang_zz',
        'user'   => 'root',       // XAMPP default user
        'pass'   => '',           // XAMPP default has no password
    ],

    'sqlite_path'             => __DIR__ . '/data.sqlite',
    'auto_fallback_to_sqlite' => true,   // if MySQL is down, use SQLite instead

    'tax_rate' => 0.06,           // SST 6% (keep in sync with js/data.js)
];
