<?php
/* GET /api/menu.php
   Returns the menu as a JSON array in the same shape the frontend uses
   (js/data.js). The menu page fetches this and, if it succeeds, uses the
   database menu instead of the built-in one. */

require __DIR__ . '/_bootstrap.php';

$rows = db()->query("SELECT * FROM menu_items ORDER BY sort_order ASC")->fetchAll();

$menu = array_map(function ($r) {
    $item = [
        'id'      => $r['id'],
        'cat'     => $r['cat'],
        'name'    => $r['name'],
        'price'   => (float) $r['price'],
        'emoji'   => $r['emoji'],
        'desc'    => $r['descr'] ?: null,
        'img'     => $r['img'] ?: null,
        'popular' => (bool) $r['popular'],
    ];
    if (!empty($r['variants_json'])) $item['variants'] = json_decode($r['variants_json'], true);
    if (!empty($r['addons_json']))   $item['addons']   = json_decode($r['addons_json'], true);
    return $item;
}, $rows);

json_out($menu);
