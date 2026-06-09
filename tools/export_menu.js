/* export_menu.js — Convert js/data.js into data/menu.json
   so the PHP backend can seed the database from the same menu the
   frontend uses. Run:  node tools/export_menu.js   */
const fs = require("fs");
const vm = require("vm");
const path = require("path");

const root = path.dirname(__dirname);
const code = fs.readFileSync(path.join(root, "js", "data.js"), "utf8");

const ctx = {};
vm.createContext(ctx);
vm.runInContext(code + "\nthis.__data = { RESTAURANT, CATEGORIES, MENU };", ctx);

const data = ctx.__data;
fs.mkdirSync(path.join(root, "data"), { recursive: true });
fs.writeFileSync(path.join(root, "data", "menu.json"), JSON.stringify(data, null, 2));
console.log("Wrote data/menu.json:", data.MENU.length, "items,", data.CATEGORIES.length, "categories");
