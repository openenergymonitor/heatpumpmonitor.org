#!/usr/bin/env python3
"""Fetch SCOP-by-flow-temperature tables from the MCS product directory.

The directory's search page calls a WordPress AJAX endpoint that returns each
product as JSON, with SCOPValues as a PHP-serialised array of SCOP (average
climate) at every flow temperature from 35 to 65 °C (0 where not declared).

Writes mcs_scop_arotherm_plus.csv (230 V aroTHERM Plus units, one row per size).
Other makes can be fetched by passing search terms, e.g. "Vitocal 150" "Ecodan",
which prints their tables instead.

Usage: python fetch_mcs_scop.py [search term ...]
"""

import csv
import json
import re
import sys
import urllib.parse
import urllib.request

from hpmon_analysis import ROOT

UA = {"User-Agent": "Mozilla/5.0"}
TEMPS = range(35, 66)


def get(url):
    return urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=60).read().decode()


def search(term):
    nonce = re.search(r'"nonce":"(\w+)"', get("https://mcscertified.com/product-directory/")).group(1)
    out, page = [], 1
    while True:
        q = urllib.parse.urlencode({"form_type": "products", "search": term, "manufacturer": "",
                                    "technology_type[]": "Air Source Heat Pump",
                                    "results_per_page": 100, "page": page})
        d = json.loads(get(f"https://mcscertified.com/wp-admin/admin-ajax.php?action=filter_products&nonce={nonce}&{q}"))["data"]
        out += d["data"]
        if page >= int(d["pagination"]["total_pages"]):
            return out
        page += 1


def scop(serialised):
    return {int(t): float(v) for t, v in re.findall(r'i:(\d+);d:([\d.]+);', serialised or "") if float(v) > 0}


def main():
    if len(sys.argv) > 1:
        for term in sys.argv[1:]:
            for p in search(term):
                s = scop(p["SCOPValues"])
                if s:
                    print(f"{p['Manufacturer'][:20]:20s} {p['ModelNumber'][:60]:60s} "
                          + " ".join(f"{s.get(t, ''):>4}" for t in (35, 40, 45, 50, 55, 60, 65)))
        return

    rows = []
    for p in search("aroTHERM Plus"):
        s = scop(p["SCOPValues"])
        if p["Name"] == "aroTHERM Plus" and "230V" in p["ModelNumber"] and s:
            kw = float(re.search(r"([\d.]+)kW", p["ModelNumber"]).group(1))
            rows.append([kw, p["ModelNumber"]] + [s.get(t, "") for t in TEMPS])
    rows.sort()
    with open(ROOT / "mcs_scop_arotherm_plus.csv", "w", newline="") as f:
        w = csv.writer(f)
        w.writerow(["hp_output", "mcs_model"] + [f"scop_{t}" for t in TEMPS])
        w.writerows(rows)
    print(f"wrote {len(rows)} models to mcs_scop_arotherm_plus.csv")


if __name__ == "__main__":
    main()
