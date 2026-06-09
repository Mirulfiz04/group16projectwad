-- =====================================================================
-- schema.sql — MySQL schema for the Sup Tulang ZZ ordering system
-- ---------------------------------------------------------------------
-- You normally DON'T need to run this by hand: backend/db.php creates the
-- database, tables, and seeds the menu automatically on the first API call.
-- It's provided here for your report / for importing via phpMyAdmin.
--
-- Import in phpMyAdmin:  Import tab -> choose this file -> Go
-- =====================================================================

CREATE DATABASE IF NOT EXISTS suptulang_zz
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE suptulang_zz;

-- Menu items (seeded from data/menu.json) -----------------------------
CREATE TABLE IF NOT EXISTS menu_items (
  id            VARCHAR(40)  PRIMARY KEY,
  cat           VARCHAR(40),
  name          VARCHAR(120),
  price         DECIMAL(8,2),
  emoji         VARCHAR(16),
  descr         TEXT,
  img           VARCHAR(200),
  popular       TINYINT DEFAULT 0,
  variants_json TEXT,                 -- JSON array of {name, price}
  addons_json   TEXT,                 -- JSON array of {name, price}
  sort_order    INT DEFAULT 0
);

-- One row per placed order -------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id            VARCHAR(20) PRIMARY KEY,   -- e.g. SZ483920
  table_no      VARCHAR(10),
  customer_name VARCHAR(120),
  note          TEXT,
  subtotal      DECIMAL(10,2),
  tax           DECIMAL(10,2),
  total         DECIMAL(10,2),
  status        VARCHAR(20) DEFAULT 'pending', -- pending|preparing|ready|completed
  created_at    VARCHAR(25),
  updated_at    VARCHAR(25)
);

-- Line items for each order ------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     VARCHAR(20),
  item_id      VARCHAR(40),
  name         VARCHAR(120),
  variant_name VARCHAR(80),
  addons_text  VARCHAR(255),
  notes        VARCHAR(255),
  unit_price   DECIMAL(8,2),
  qty          INT,
  CONSTRAINT fk_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE INDEX idx_orders_status   ON orders(status);
CREATE INDEX idx_items_order     ON order_items(order_id);
