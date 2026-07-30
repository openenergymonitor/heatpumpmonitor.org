#!/usr/bin/env python3
"""Build the compact UK+Ireland coastline asset used by the home page hero
map (www/Modules/home/hero_map_geometry.json, consumed by Home::hero_map()).

Projects Natural Earth 50m country polygons to Web Mercator, clips to a
UK+Ireland viewport, simplifies with Douglas-Peucker and emits JSON:
{width, height, lon_min, lon_max, lat_min, lat_max, paths:[...]}.
The PHP side re-uses the same projection params to place system dots, so
if the viewport or projection here changes, the output file must be
regenerated and committed — PHP reads it as-is.

One-off usage (only needed if the viewport/simplification is changed):
  curl -sL -o ne50m.json https://raw.githubusercontent.com/martynafford/natural-earth-geojson/master/50m/cultural/ne_50m_admin_0_countries.json
  python3 build_hero_map_geometry.py
  cp hero_map_geometry.json ../www/Modules/home/hero_map_geometry.json"""

import json, math

SRC = "ne50m.json"
OUT = "hero_map_geometry.json"

# Countries to draw (land that appears in the viewport)
KEEP = {"United Kingdom", "Ireland", "Isle of Man", "Guernsey", "Jersey",
        "France", "Belgium", "Netherlands", "Germany"}

# Viewport (lon/lat). Orkney included, Shetland omitted; northern France,
# Belgium and the Dutch coast appear on the right for geographic context
# (and catch the Benelux systems), like the full map page.
LON_MIN, LON_MAX = -10.9, 5.6
LAT_MIN, LAT_MAX = 49.5, 59.5

WIDTH = 520.0

def merc_y(lat):
    return math.log(math.tan(math.pi / 4 + math.radians(lat) / 2))

MY_MIN, MY_MAX = merc_y(LAT_MIN), merc_y(LAT_MAX)
SCALE = WIDTH / (LON_MAX - LON_MIN)
HEIGHT = round((MY_MAX - MY_MIN) / math.radians(LON_MAX - LON_MIN) * WIDTH, 1)

def project(lon, lat):
    x = (lon - LON_MIN) * SCALE
    y = (MY_MAX - merc_y(lat)) / (MY_MAX - MY_MIN) * HEIGHT
    return x, y

# --- Douglas-Peucker simplification in projected px space ---
def dp(points, tol):
    if len(points) < 3:
        return points
    keep = [False] * len(points)
    keep[0] = keep[-1] = True
    stack = [(0, len(points) - 1)]
    while stack:
        i0, i1 = stack.pop()
        x0, y0 = points[i0]
        x1, y1 = points[i1]
        dx, dy = x1 - x0, y1 - y0
        seg2 = dx * dx + dy * dy
        dmax, imax = -1.0, -1
        for i in range(i0 + 1, i1):
            px, py = points[i]
            if seg2 == 0:
                d2 = (px - x0) ** 2 + (py - y0) ** 2
            else:
                t = ((px - x0) * dx + (py - y0) * dy) / seg2
                t = max(0.0, min(1.0, t))
                d2 = (px - (x0 + t * dx)) ** 2 + (py - (y0 + t * dy)) ** 2
            if d2 > dmax:
                dmax, imax = d2, i
        if dmax > tol * tol:
            keep[imax] = True
            stack.append((i0, imax))
            stack.append((imax, i1))
    return [p for p, k in zip(points, keep) if k]

def ring_area(pts):
    a = 0.0
    for i in range(len(pts)):
        x0, y0 = pts[i]
        x1, y1 = pts[(i + 1) % len(pts)]
        a += x0 * y1 - x1 * y0
    return abs(a) / 2

# Sutherland-Hodgman: clip a ring to the viewport (with margin) so rings
# that mostly lie outside it (e.g. France) don't carry thousands of
# irrelevant points — which both bloats the file and lets the rounding of
# the emitted path segments accumulate into visible distortion.
CLIP_MARGIN = 12.0

def clip_ring(pts):
    lo_x, hi_x = -CLIP_MARGIN, WIDTH + CLIP_MARGIN
    lo_y, hi_y = -CLIP_MARGIN, HEIGHT + CLIP_MARGIN
    edges = [
        (lambda p: p[0] >= lo_x, lambda a, b: (lo_x, a[1] + (b[1] - a[1]) * (lo_x - a[0]) / (b[0] - a[0]))),
        (lambda p: p[0] <= hi_x, lambda a, b: (hi_x, a[1] + (b[1] - a[1]) * (hi_x - a[0]) / (b[0] - a[0]))),
        (lambda p: p[1] >= lo_y, lambda a, b: (a[0] + (b[0] - a[0]) * (lo_y - a[1]) / (b[1] - a[1]), lo_y)),
        (lambda p: p[1] <= hi_y, lambda a, b: (a[0] + (b[0] - a[0]) * (hi_y - a[1]) / (b[1] - a[1]), hi_y)),
    ]
    for inside, intersect in edges:
        if not pts:
            return []
        out = []
        for i in range(len(pts)):
            a, b = pts[i - 1], pts[i]
            if inside(b):
                if not inside(a):
                    out.append(intersect(a, b))
                out.append(b)
            elif inside(a):
                out.append(intersect(a, b))
        pts = out
    return pts

data = json.load(open(SRC))
paths = []
n_pts_in = n_pts_out = 0

for f in data["features"]:
    name = f["properties"]["ADMIN"]
    if name not in KEEP:
        continue
    geom = f["geometry"]
    polys = geom["coordinates"] if geom["type"] == "MultiPolygon" else [geom["coordinates"]]
    for poly in polys:
        for ring in poly:
            pts = clip_ring([project(lon, lat) for lon, lat in ring])
            if not pts:
                continue
            n_pts_in += len(ring)
            pts = dp(pts + [pts[0]], 0.45)[:-1]  # close for DP, reopen
            if ring_area(pts) < 4:   # drop sub-4px² islets
                continue
            n_pts_out += len(pts)
            # Relative path commands at 1 decimal place. Deltas are taken
            # between the ROUNDED points so rounding error never accumulates
            # along the ring.
            d = []
            lx = ly = 0.0
            for i, (x, y) in enumerate(pts):
                rx, ry = round(x, 1), round(y, 1)
                if i == 0:
                    d.append(f"M{rx:g} {ry:g}")
                else:
                    dx, dy = round(rx - lx, 1), round(ry - ly, 1)
                    if dx or dy:
                        d.append(f"l{dx:g} {dy:g}")
                    else:
                        continue
                lx, ly = rx, ry
            d.append("Z")
            paths.append("".join(d))

out = {
    "width": WIDTH,
    "height": HEIGHT,
    "lon_min": LON_MIN,
    "lon_max": LON_MAX,
    "lat_min": LAT_MIN,
    "lat_max": LAT_MAX,
    "paths": paths,
}
json.dump(out, open(OUT, "w"), separators=(",", ":"))
size = len(open(OUT).read())
print(f"viewBox 0 0 {WIDTH} {HEIGHT} | rings {len(paths)} | pts {n_pts_in} -> {n_pts_out} | {size/1024:.1f} kB")
