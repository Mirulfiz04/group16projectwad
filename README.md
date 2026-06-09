# Sup Tulang ZZ — Restaurant Ordering System

Customer self-ordering web app for **Project 1A: Restaurant Order Management System**.
A walk-in customer scans the QR code on their table, browses the menu, orders, and
tracks the order live. Built mobile-first with **HTML / CSS / JavaScript** and a
**PHP + MySQL** backend.

> **My part (Member 1 – Customer / QR Ordering):** the customer pages — landing,
> menu, food details modal, cart, order tracking — plus menu display, add-to-cart UI
> and responsive mobile design. The QR codes, dish photos and backend below are extras
> added to make the group demo run end-to-end.

## Pages

| File            | Page                      | Notes |
|-----------------|---------------------------|-------|
| `index.html`    | Landing (after QR scan)   | Reads the table number from the QR URL |
| `menu.html`     | Menu + Food Details Modal | Category tabs, search, photos, options/add-ons/qty/notes |
| `cart.html`     | Cart                      | Edit quantities, customer name, Subtotal + SST + Total, place order |
| `tracking.html` | Order tracking            | Live timeline: Received → In Progress → Ready → Completed |
| `staff.html`    | Kitchen / Staff (bonus)   | See live orders and update their status |

---

## Quick start (no setup) — for the customer pages

Just open `index.html` (double-click, or VS Code **Live Server**). The app works on its
own: the menu is built in, the cart is saved in the browser, and the order status
auto-advances so you can see the tracking timeline. To test a table, open
`index.html?table=7`.

## Full setup with PHP + MySQL — for the whole system

Easiest with **XAMPP** (standard for the assignment):

1. Install & open **XAMPP**, start **Apache** and **MySQL**.
2. Copy this whole `suptulang-zz-order` folder into `C:\xampp\htdocs\`.
3. Open <http://localhost/suptulang-zz-order/index.html>.

That's it — on the first API call the backend **auto-creates the database, tables, and
seeds all 125 menu items** (from `data/menu.json`). No manual SQL import needed.
(If you prefer, you can still import `backend/schema.sql` via phpMyAdmin for your report.)

DB settings are in `backend/config.php` (defaults match XAMPP: user `root`, no password).
If MySQL can't be reached it automatically falls back to a local SQLite file so the demo
never breaks.

> Want to run it from PHP's built-in server instead of XAMPP?
> `php -S 0.0.0.0:8000 -t .` then open <http://localhost:8000/index.html>.

---

## Table QR codes

```
python tools/generate_qr.py                       # 12 tables, auto-detects your PC's IP
python tools/generate_qr.py --base https://yoursite.com/index.html --tables 20
```

Creates `qr/table-1.png … table-N.png` plus `qr/print.html` (a printable sheet of table
tents — open it and press Ctrl+P).

**Important:** the QR must point to a URL the customer's **phone** can reach:
- *Same Wi-Fi demo:* use your PC's LAN IP (the default) and serve with XAMPP or
  `php -S 0.0.0.0:8000`. Phone and PC on the same network.
- *Real deployment:* pass `--base https://your-domain/index.html`.
- `localhost` will **not** work from a phone.

## Dish photos

25 popular dishes already have real photos in `images/` (downloaded with
`tools/fetch_images.py`). The code shows the photo when `images/<item-id>.jpg` exists and
falls back to the dish emoji otherwise.

- **Replace a photo:** drop your own JPG over `images/<item-id>.jpg` (same name). Done.
- **Add a photo to another dish:** save `images/<id>.jpg` and add `img: "images/<id>.jpg"`
  to that item in `js/data.js` (item ids are listed there).
- Re-download the sample set anytime: `python tools/fetch_images.py`.

---

## How the backend connects to the frontend

The frontend uses the backend automatically when it's available, and silently falls back
to its built-in behaviour when it isn't:

| Frontend | Calls | Falls back to |
|----------|-------|---------------|
| `menu.html` | `GET api/menu.php` (menu from DB) | built-in `js/data.js` menu |
| `cart.html` | `POST api/orders.php` (save order) | local order in the browser |
| `tracking.html` | `GET api/order_status.php?id=` (poll status) | demo auto-advance |
| `staff.html` | `GET api/orders.php`, `POST api/order_status.php` | — (needs PHP) |

### API reference

- `GET  api/menu.php` → array of menu items.
- `POST api/orders.php` → place order `{table, customerName, note, items[]}`; returns
  `{id, status, subtotal, tax, total}` (totals are recomputed server-side).
- `GET  api/orders.php` → all orders (newest first); `?id=SZxxxx` for one.
- `GET  api/order_status.php?id=SZxxxx` → `{id, status, …}`.
- `POST api/order_status.php` → update status `{id, status}`
  (`pending → preparing → ready → completed`).

---

## Project structure

```
suptulang-zz-order/
├── index.html  menu.html  cart.html  tracking.html   # customer pages (my part)
├── staff.html                                         # kitchen/staff view (bonus)
├── css/style.css
├── js/
│   ├── data.js     # built-in menu (125 items) — also exported to data/menu.json
│   └── cart.js     # shared cart + order + API-sync helpers
├── api/            # menu.php · orders.php · order_status.php · _bootstrap.php
├── backend/        # config.php · db.php · schema.sql  (MySQL, SQLite fallback)
├── data/menu.json  # menu the DB is seeded from (generated from data.js)
├── images/         # dish photos (<item-id>.jpg)
├── qr/             # table QR codes + printable sheet
└── tools/          # generate_qr.py · fetch_images.py · export_menu.js
```

If you edit the menu in `js/data.js`, re-export it for the DB seeder with:
`node tools/export_menu.js` (then delete `backend/data.sqlite` / drop the MySQL DB so it
re-seeds).
