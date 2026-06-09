"""
generate_qr.py — Make a QR code for each table.

Each QR encodes the landing page URL with the table number, e.g.
    http://10.131.79.184:8000/index.html?table=7
so when a customer scans it, the app opens already knowing their table.

Usage:
    python tools/generate_qr.py                         # uses defaults below
    python tools/generate_qr.py --base http://mysite.com/index.html --tables 20

Outputs:
    qr/table-1.png ... qr/table-N.png   (individual QR images)
    qr/print.html                       (printable sheet of table tents)

IMPORTANT: the BASE url must be reachable from the customer's PHONE.
  - Same Wi-Fi demo: use your PC's LAN IP (auto-detected default below) and
    serve with  php -S 0.0.0.0:8000  (or XAMPP).  Phone + PC on same network.
  - Real deployment: use your hosted domain, e.g. https://suptulangzz.com/index.html
  - localhost will NOT work from a phone (it points to the phone itself).
"""
import argparse, os, socket
import qrcode


def lan_ip():
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "127.0.0.1"


def main():
    default_base = f"http://{lan_ip()}:8000/index.html"
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default=default_base,
                    help="Landing page URL (table number is appended). Default: %(default)s")
    ap.add_argument("--tables", type=int, default=12, help="How many tables (default 12)")
    ap.add_argument("--restaurant", default="SUP TULANG ZZ")
    args = ap.parse_args()

    here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    out = os.path.join(here, "qr")
    os.makedirs(out, exist_ok=True)

    sep = "&" if "?" in args.base else "?"
    cards = []
    for n in range(1, args.tables + 1):
        url = f"{args.base}{sep}table={n}"
        img = qrcode.make(url)
        path = os.path.join(out, f"table-{n}.png")
        img.save(path)
        cards.append((n, url))
        print(f"  table {n:>2} -> {url}")

    # Printable sheet of table tents
    tents = "\n".join(
        f'''    <div class="tent">
      <div class="brand">{args.restaurant}</div>
      <div class="scan">📱 Scan to order</div>
      <img src="table-{n}.png" alt="QR table {n}">
      <div class="tableno">TABLE {n}</div>
      <div class="hint">Scan with your phone camera to view the menu &amp; order</div>
    </div>''' for n, _ in cards)

    html = f"""<!DOCTYPE html><html><head><meta charset="utf-8">
<title>{args.restaurant} — Table QR Codes</title>
<style>
  body {{ font-family: "Segoe UI", Arial, sans-serif; background:#eee; margin:0; padding:16px; }}
  .grid {{ display:grid; grid-template-columns:repeat(2,1fr); gap:16px; max-width:900px; margin:0 auto; }}
  .tent {{ background:#fff; border:2px dashed #d6342b; border-radius:14px; padding:20px; text-align:center;
           page-break-inside:avoid; }}
  .brand {{ color:#d6342b; font-weight:800; font-size:22px; letter-spacing:1px; }}
  .scan {{ color:#f3941f; font-weight:700; margin:6px 0 10px; }}
  .tent img {{ width:200px; height:200px; }}
  .tableno {{ font-size:28px; font-weight:800; margin-top:8px; }}
  .hint {{ color:#777; font-size:12px; margin-top:6px; }}
  @media print {{ body {{ background:#fff; }} }}
</style></head>
<body>
  <div class="grid">
{tents}
  </div>
</body></html>"""
    with open(os.path.join(out, "print.html"), "w", encoding="utf-8") as f:
        f.write(html)

    print(f"\nDone. {args.tables} QR codes in: {out}")
    print("Open qr/print.html and print it (Ctrl+P) to get table tents.")
    print(f"Base URL used: {args.base}")


if __name__ == "__main__":
    main()
