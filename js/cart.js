/* =====================================================================
   cart.js — Shared cart + order helpers (used by every page)
   ---------------------------------------------------------------------
   Everything is kept in the browser's localStorage so the whole flow
   works without a backend. When the PHP/MySQL side is ready:
     - Cart can stay client-side (the cart usually does).
     - Order.place() should POST the order to api/orders.php and let the
       server generate the order number + store it in MySQL.
     - tracking.html should GET the live status from api/order_status.php.
   ===================================================================== */

const STORAGE_KEYS = {
  cart: "sz_cart",
  table: "sz_table",
  order: "sz_current_order",
  orders: "sz_orders",
};

/* ---------- Formatting ---------- */
function formatRM(n) {
  return RESTAURANT.currency + " " + Number(n).toFixed(2);
}

/* ---------- Thumbnail ----------
   Shows the dish photo when one exists, and falls back to the emoji if the
   image is missing or fails to load. Used by the menu cards and the cart. */
function thumbHTML(img, emoji) {
  const e = emoji || "🍽️";
  if (img) {
    return `<img src="${img}" alt="" loading="lazy"
      onerror="this.outerHTML='<span>${e}</span>'">`;
  }
  return `<span>${e}</span>`;
}

/* ---------- Backend sync (optional) ----------
   Tries to load the menu from the PHP API. If the backend isn't running
   (e.g. you opened the files directly, or are serving them statically),
   it silently keeps the built-in menu from data.js. Returns true if the
   API menu was used. */
async function syncMenuFromAPI() {
  try {
    const r = await fetch("api/menu.php", { cache: "no-store" });
    if (r.ok) {
      const j = await r.json();
      if (Array.isArray(j) && j.length) {
        MENU.length = 0;          // keep the same array reference the pages use
        j.forEach(x => MENU.push(x));
        return true;
      }
    }
  } catch (e) { /* backend not available — use built-in menu */ }
  return false;
}

/* ---------- Table number (comes from the QR code: index.html?table=7) ---------- */
const Table = {
  get() { return localStorage.getItem(STORAGE_KEYS.table) || ""; },
  set(t) { localStorage.setItem(STORAGE_KEYS.table, String(t)); },
};

/* ---------- Cart ---------- */
const Cart = {
  get() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEYS.cart)) || []; }
    catch { return []; }
  },
  save(items) {
    localStorage.setItem(STORAGE_KEYS.cart, JSON.stringify(items));
    document.dispatchEvent(new CustomEvent("cart:changed"));
  },

  /* A signature lets two identical configurations stack into one line. */
  signature(item) {
    const addons = (item.addons || []).map(a => a.name).sort().join("|");
    return [item.id, item.variant ? item.variant.name : "", addons, item.notes || ""].join("##");
  },

  /* unitPrice = (variant price or base price) + sum of add-on prices */
  unitPrice(item) {
    const base = item.variant ? item.variant.price : item.basePrice;
    const extra = (item.addons || []).reduce((s, a) => s + a.price, 0);
    return base + extra;
  },

  add(item) {
    const items = this.get();
    const sig = this.signature(item);
    const existing = items.find(i => this.signature(i) === sig);
    if (existing) {
      existing.qty += item.qty;
    } else {
      items.push(item);
    }
    this.save(items);
  },

  setQty(sig, qty) {
    let items = this.get();
    const it = items.find(i => this.signature(i) === sig);
    if (!it) return;
    it.qty = qty;
    if (it.qty <= 0) items = items.filter(i => this.signature(i) !== sig);
    this.save(items);
  },

  remove(sig) {
    this.save(this.get().filter(i => this.signature(i) !== sig));
  },

  clear() { this.save([]); },

  count() { return this.get().reduce((s, i) => s + i.qty, 0); },

  subtotal() { return this.get().reduce((s, i) => s + this.unitPrice(i) * i.qty, 0); },

  tax() { return this.subtotal() * RESTAURANT.taxRate; },

  total() { return this.subtotal() + this.tax(); },
};

/* ---------- Order ---------- */
const Order = {
  /* Creates the order, stores it, and returns it.
     Replace the body with a fetch POST when the backend exists. */
  place({ name = "", note = "" } = {}) {
    const items = Cart.get();
    if (!items.length) return null;

    const now = Date.now();
    const order = {
      id: "SZ" + String(now).slice(-6),     // e.g. SZ483920 (server should own this later)
      table: Table.get() || "—",
      customerName: name,
      note,
      items,
      subtotal: Cart.subtotal(),
      tax: Cart.tax(),
      total: Cart.total(),
      status: "pending",                    // pending → preparing → ready → completed
      placedAt: now,
      history: [{ status: "pending", at: now }],
    };

    localStorage.setItem(STORAGE_KEYS.order, JSON.stringify(order));
    const all = JSON.parse(localStorage.getItem(STORAGE_KEYS.orders) || "[]");
    all.push(order);
    localStorage.setItem(STORAGE_KEYS.orders, JSON.stringify(all));

    Cart.clear();
    return order;
  },

  current() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEYS.order)); }
    catch { return null; }
  },

  /* Demo-only: advance the status. In production the kitchen/staff app
     updates this in MySQL and tracking.html polls api/order_status.php. */
  advance() {
    const o = this.current();
    if (!o) return null;
    const flow = ["pending", "preparing", "ready", "completed"];
    const idx = flow.indexOf(o.status);
    if (idx < flow.length - 1) {
      o.status = flow[idx + 1];
      o.history.push({ status: o.status, at: Date.now() });
      localStorage.setItem(STORAGE_KEYS.order, JSON.stringify(o));
    }
    return o;
  },
};

/* ---------- Tiny toast helper ---------- */
function toast(msg) {
  let el = document.querySelector(".toast");
  if (!el) {
    el = document.createElement("div");
    el.className = "toast";
    document.body.appendChild(el);
  }
  el.textContent = msg;
  el.classList.add("show");
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove("show"), 1800);
}

/* ---------- Keep any .cart-badge in sync on every page ---------- */
function refreshCartBadges() {
  const n = Cart.count();
  document.querySelectorAll(".cart-badge").forEach(b => {
    b.textContent = n;
    b.style.display = n > 0 ? "grid" : "none";
  });
}
document.addEventListener("cart:changed", refreshCartBadges);
document.addEventListener("DOMContentLoaded", refreshCartBadges);
