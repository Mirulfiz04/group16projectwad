"""
fetch_images.py — Download a real photo for selected dishes.

Photos come from loremflickr.com (real, Creative-Commons Flickr photos matched
by keyword) and are saved locally to  images/<item-id>.jpg  so the app does not
depend on the internet at runtime.

The menu code shows the photo when images/<id>.jpg exists, and falls back to the
dish emoji when it does not — so you can add or replace any photo just by dropping
a file named after the item id (see js/data.js for ids).

Usage:  python tools/fetch_images.py
"""
import os, time, urllib.request
from PIL import Image
from io import BytesIO

# item id  ->  search keywords (comma separated)
DISHES = {
    "sig-gearbox":     "mutton,soup",
    "sig-kambing":     "lamb,soup",
    "sig-mr-gearbox":  "noodle,soup",
    "sar-nl-basmathi": "nasilemak",
    "sar-nl-rendang":  "rendang",
    "sar-nasi-ayam":   "chicken,rice",
    "sar-laksa":       "laksa",
    "sar-roti-bakar":  "toast,kaya",
    "rc-kosong":       "roti,canai",
    "rc-double-jantan":"roti,canai,egg",
    "rc-pisang":       "banana,pancake",
    "set-siakap":      "fried,fish,rice",
    "ikan-tiga-rasa":  "steamed,fish,chili",
    "ikan-sotong-bakar":"grilled,squid",
    "ng-kampung":      "fried,rice",
    "mg-ckt":          "fried,noodles",
    "ac-ty-ayam":      "tomyum,soup",
    "ac-mee-bandung":  "noodle,soup,prawn",
    "w-chicken-chop":  "chicken,chop",
    "w-smash-beef":    "cheeseburger",
    "w-aglio":         "spaghetti",
    "w-fries":         "french,fries",
    "d-teh-tarik":     "milk,tea",
    "d-cendol":        "cendol,dessert",
    "d-jus-orange":    "orange,juice",
}

UA = {"User-Agent": "Mozilla/5.0 (menu-image-fetcher)"}


def fetch(keywords, w=600, h=400, tries=3):
    url = f"https://loremflickr.com/{w}/{h}/{keywords}"
    last = None
    for _ in range(tries):
        try:
            req = urllib.request.Request(url, headers=UA)
            data = urllib.request.urlopen(req, timeout=25).read()
            im = Image.open(BytesIO(data))   # validate it's a real image
            im.verify()
            return data
        except Exception as e:
            last = e
            time.sleep(1.5)
    raise last


def main():
    here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    out = os.path.join(here, "images")
    os.makedirs(out, exist_ok=True)

    ok, fail = 0, 0
    for item_id, kw in DISHES.items():
        path = os.path.join(out, f"{item_id}.jpg")
        try:
            data = fetch(kw)
            with open(path, "wb") as f:
                f.write(data)
            print(f"  [OK]   {item_id:<18} ({kw})  {len(data)//1024} KB")
            ok += 1
        except Exception as e:
            print(f"  [FAIL] {item_id:<18} ({kw})  {e}")
            fail += 1
        time.sleep(0.4)

    print(f"\nDone. {ok} downloaded, {fail} failed -> {out}")
    print("Tip: don't like a photo? Replace images/<id>.jpg with your own, same name.")


if __name__ == "__main__":
    main()
