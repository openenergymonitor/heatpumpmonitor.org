// Emitter capacity statistics, shared by the emitter page (emitter.js) and the
// MyHeatpump dashboard's Measurements card: episode rows from signature/list
// to rated output at a mean water to room ΔT, kWh-weighted percentiles, and a
// weighted KDE with prominence-based peak detection. Plain functions, no DOM.

// ---- Episode transform -------------------------------------------------
// Keep only episodes with the feeds the radiator equation needs, and carry the
// raw quantities; the rated-output estimate itself is recomputed in refresh()
// so parameter changes don't need a reload.
function transform(rows) {
    var out = [];
    for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        var heat = (r.heat === null || r.heat === undefined) ? NaN : +r.heat;
        var flowT = +r.flowT;
        var returnT = (r.returnT === null || r.returnT === undefined) ? NaN : +r.returnT;
        var roomT = (r.roomT === null || r.roomT === undefined) ? NaN : +r.roomT;
        var dur = +r.duration;
        if (isNaN(heat) || heat <= 0) continue;
        if (isNaN(flowT) || isNaN(returnT) || isNaN(roomT)) continue;
        out.push({
            start: +r.start_time,
            end: +r.end_time,
            dur: Math.round(dur / 60),                   // minutes
            heat: heat,                                  // W
            flowT: flowT,                                // °C
            returnT: returnT,                            // °C
            mwt: (flowT + returnT) / 2,                  // °C
            roomT: roomT,                                // °C
            kwh: heat * dur / 3.6e6                      // heat energy delivered
        });
    }
    return out;
}

// Rated output (kW at the rated ΔT) for one episode under parameters p, or NaN
// if the episode's radiator ΔT is below the minimum.
function ratedKw(e, p) {
    var dt = e.mwt - e.roomT;
    if (dt < p.minDT) return NaN;
    return (e.heat / Math.pow(dt / p.ratedDT, p.n)) / 1000;
}

// ---- Weighted statistics -----------------------------------------------
// pts: array of { kw, kwh }.
function weightedMean(pts) {
    var sw = 0, swx = 0;
    pts.forEach(function (d) { sw += d.kwh; swx += d.kwh * d.kw; });
    return sw > 0 ? swx / sw : NaN;
}

function weightedMedian(pts) {
    if (!pts.length) return NaN;
    var s = pts.slice().sort(function (a, b) { return a.kw - b.kw; });
    var tot = s.reduce(function (sum, d) { return sum + d.kwh; }, 0);
    var acc = 0;
    for (var i = 0; i < s.length; i++) {
        acc += s[i].kwh;
        if (acc >= tot / 2) return s[i].kw;
    }
    return s[s.length - 1].kw;
}

// Weighted percentile (0..1) of kw, by kwh weight.
function weightedPercentile(pts, q) {
    if (!pts.length) return NaN;
    var s = pts.slice().sort(function (a, b) { return a.kw - b.kw; });
    var tot = s.reduce(function (sum, d) { return sum + d.kwh; }, 0);
    var acc = 0;
    for (var i = 0; i < s.length; i++) {
        acc += s[i].kwh;
        if (acc >= tot * q) return s[i].kw;
    }
    return s[s.length - 1].kw;
}

// ---- Histogram ---------------------------------------------------------
// Bin points into fixed-width bins from 0 to a kWh-weighted 99th percentile
// cap (extreme low-ΔT episodes produce huge rated estimates that would
// otherwise stretch the axis). Returns bins plus the clipped tail's totals.
function makeHistogram(pts, binW) {
    var cap = weightedPercentile(pts, 0.99);
    var nbins = Math.max(1, Math.ceil(cap / binW));
    var kwh = new Array(nbins).fill(0);
    var count = new Array(nbins).fill(0);
    var clippedKwh = 0, clippedN = 0;
    pts.forEach(function (d) {
        var i = Math.floor(d.kw / binW);
        if (i >= nbins) { clippedKwh += d.kwh; clippedN++; return; }
        kwh[i] += d.kwh;
        count[i]++;
    });
    return { kwh: kwh, count: count, binW: binW, nbins: nbins, clippedKwh: clippedKwh, clippedN: clippedN };
}

// ---- Peak detection ----------------------------------------------------
// Peaks are found on a kWh-weighted gaussian kernel density estimate of the
// rated-output samples, not on the raw histogram bars: the KDE is independent
// of bin edges, so binning noise disappears. Candidate peaks are then ranked
// by PROMINENCE — how far a peak rises above the highest saddle connecting it
// to taller terrain — which is what separates a genuinely distinct mode from
// a noisy shoulder on the flank of a bigger peak.

// Weighted gaussian KDE of pts (by kwh weight), evaluated at m evenly spaced
// grid points spanning [0, xmax]. Returns { x: grid, d: density } with the
// density normalised so it integrates to 1 over all points.
function kdeCurve(pts, bw, xmax, m) {
    var xs = new Array(m);
    var d = new Array(m).fill(0);
    for (var i = 0; i < m; i++) xs[i] = (i + 0.5) * xmax / m;
    var totW = 0;
    pts.forEach(function (p) { totW += p.kwh; });
    if (totW <= 0 || bw <= 0) return { x: xs, d: d };
    var inv = 1 / (bw * Math.sqrt(2 * Math.PI));
    pts.forEach(function (p) {
        // gaussians decay fast: only evaluate within ±4 bandwidths
        var lo = Math.max(0, Math.floor((p.kw - 4 * bw) / xmax * m));
        var hi = Math.min(m - 1, Math.ceil((p.kw + 4 * bw) / xmax * m));
        for (var i = lo; i <= hi; i++) {
            var z = (xs[i] - p.kw) / bw;
            d[i] += p.kwh * inv * Math.exp(-0.5 * z * z);
        }
    });
    for (var i = 0; i < m; i++) d[i] /= totW;
    return { x: xs, d: d };
}

// Automatic KDE bandwidth: Silverman's rule with the effective sample size of
// the kWh weights, halved because Silverman's rule assumes a single gaussian
// and over-smooths multimodal data. Never narrower than half a display bin.
function autoBandwidth(pts, binW) {
    var sw = 0, swx = 0, swx2 = 0, sw2 = 0;
    pts.forEach(function (p) {
        sw += p.kwh; swx += p.kwh * p.kw; swx2 += p.kwh * p.kw * p.kw; sw2 += p.kwh * p.kwh;
    });
    if (sw <= 0) return binW;
    var mean = swx / sw;
    var v = swx2 / sw - mean * mean;
    var sigma = v > 0 ? Math.sqrt(v) : 0;
    var neff = sw * sw / sw2;
    var h = 0.53 * sigma * Math.pow(neff, -0.2);
    return Math.max(binW / 2, h || binW);
}

// Prominence of the local maximum at grid index i: height above the higher of
// the two saddles found by walking out on each side until terrain taller than
// the peak is met (or the edge of the grid).
function peakProminence(d, i) {
    var h = d[i];
    var minL = h, minR = h;
    var j;
    for (j = i - 1; j >= 0 && d[j] <= h; j--) if (d[j] < minL) minL = d[j];
    for (j = i + 1; j < d.length && d[j] <= h; j++) if (d[j] < minR) minR = d[j];
    return h - Math.max(minL, minR);
}

// Find up to 4 peaks of the KDE with prominence of at least 10% of the tallest
// density. Each peak's kWh is the energy of the episodes falling between the
// density minima that separate it from its neighbouring peaks, so the shares
// of the reported peaks sum to the total. Returns { peaks, curve }.
function findPeaks(pts, bw, xmax) {
    var m = 512;
    var k = kdeCurve(pts, bw, xmax, m);
    var d = k.d;
    var dmax = Math.max.apply(null, d);
    if (!(dmax > 0)) return { peaks: [], curve: k };

    var cand = [];
    for (var i = 1; i < m - 1; i++) {
        if (d[i] > d[i - 1] && d[i] >= d[i + 1]) {
            var prom = peakProminence(d, i);
            if (prom >= 0.1 * dmax) cand.push({ idx: i, kw: k.x[i], height: d[i], prom: prom });
        }
    }
    cand.sort(function (a, b) { return b.prom - a.prom; });
    var kept = cand.slice(0, 4).sort(function (a, b) { return a.kw - b.kw; });

    // attribution boundaries: the density minimum between adjacent kept peaks
    var bounds = [0];
    for (var i = 0; i < kept.length - 1; i++) {
        var lo = kept[i].idx, hi = kept[i + 1].idx, minj = lo;
        for (var j = lo; j <= hi; j++) if (d[j] < d[minj]) minj = j;
        bounds.push(k.x[minj]);
    }
    bounds.push(Infinity);
    kept.forEach(function (p, i) {
        p.kwh = 0;
        pts.forEach(function (q) {
            if (q.kw >= bounds[i] && q.kw < bounds[i + 1]) p.kwh += q.kwh;
        });
    });
    return { peaks: kept, curve: k };
}
