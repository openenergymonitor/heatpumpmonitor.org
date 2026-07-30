<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

global $path;
?>
<style>
/* ===================================================================
   Economics explorer: heat pump vs boiler whole-life cost
   Ported from Modules/home/reference.html onto the home page design
   system. Built as a single self-contained section so it can be
   dropped into home_view.php later: the block marked "shared
   scaffolding" duplicates home_view.php styles and can simply be
   deleted when merged; everything else is namespaced .hpm-economics-*.
   =================================================================== */

/* ---- shared scaffolding (copied from home_view.php — drop on merge) ---- */
.hpm-home {
    --hpm-blue: #44b3e2;          /* existing brand blue */
    --hpm-blue-deep: #2187ba;     /* brand blue, darkened for text/buttons */
    --hpm-ink: #14344a;           /* headings, near-black with blue bias */
    --hpm-ink-soft: #3d5a6e;      /* body text */
    --hpm-muted: #3d5a6e;         /* captions, secondary */
    --hpm-sky: #eef6fb;           /* section ground */
    --hpm-sky-deep: #ddeef8;
    --hpm-card: #ffffff;
    --hpm-line: #d9e8f1;
    --hpm-teal: #2a9d8f;          /* data: underfloor / low temp */
    --hpm-amber: #c97716;         /* data: radiators / higher temp */
    --hpm-panel-1: #0d5c6d;       /* featured panel gradient */
    --hpm-panel-2: #1d8a80;

    color: var(--hpm-ink-soft);
    font-size: 1.0625rem;
    line-height: 1.6;
    background: var(--hpm-card);
}
.hpm-home h1, .hpm-home h2, .hpm-home h3, .hpm-home h4 {
    color: var(--hpm-ink);
    text-wrap: balance;
}
.hpm-section { padding: 4.5rem 0; }
.hpm-section-sky { background: var(--hpm-sky); }
.hpm-eyebrow {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--hpm-muted);
}
.hpm-eyebrow-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    border-radius: 0.6rem;
    background: var(--hpm-panel-1);
    color: #fff;
    font-size: 0.8125rem;
    letter-spacing: 0.05em;
}
.hpm-display {
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.08;
    font-size: clamp(2rem, 4.2vw, 3rem);
    max-width: 22ch;
}
.hpm-display .hpm-accent { color: var(--hpm-blue-deep); }
.hpm-lead {
    max-width: 62ch;
    font-size: 1.125rem;
}
.hpm-chart-card {
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 1rem;
    padding: 1.75rem;
    box-shadow: 0 4px 16px rgba(20, 52, 74, 0.05);
}
.hpm-chart-card h3 {
    font-size: 1.1875rem;
    font-weight: 700;
    margin-bottom: 0.15rem;
}
.hpm-chart-sub {
    font-size: 0.9375rem;
    color: var(--hpm-muted);
    margin-bottom: 1.25rem;
}
.hpm-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 1.5rem;
    margin-top: 1rem;
    font-size: 0.9375rem;
    color: var(--hpm-ink-soft);
}
.hpm-legend span {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.hpm-legend .hpm-swatch {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
}
.hpm-seg {
    display: inline-flex;
    gap: 0.25rem;
    padding: 0.25rem;
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 0.75rem;
}
.hpm-seg button {
    border: none;
    background: transparent;
    border-radius: 0.5rem;
    padding: 0.35rem 1rem;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--hpm-ink-soft);
    cursor: pointer;
}
.hpm-seg button.active { background: var(--hpm-blue-deep); color: #fff; }
.hpm-seg button:focus-visible {
    outline: 3px solid rgba(68, 179, 226, 0.55);
    outline-offset: 2px;
}
.hpm-finder-footnote {
    margin-top: 1.25rem;
    font-size: 0.875rem;
    color: var(--hpm-muted);
    max-width: 75ch;
}

/* ---- economics: semantic accents ---- */
.hpm-economics {
    /* heat pump = brand blue, boiler = amber (matches home page data colours).
       Two strengths of each: the plain token for graphics (bars, dots, slider
       fills), and an -ink variant dark enough for small text on white. */
    --economics-hp: #1e7ba8;
    --economics-gas: #b56a0e;
    --economics-hp-ink: #175d80;
    --economics-gas-ink: #8f5307;
    --economics-teal-ink: #1f7a6d;
    /* slider tracks & input borders need to be darker than --hpm-line to
       register against white cards */
    --economics-track: #c5d9e7;
    --economics-line-strong: #b9d2e2;
}
.hpm-economics .hpm-display .hpm-accent-gas { color: var(--economics-gas); }
.hpm-economics-pos { color: var(--economics-hp-ink); }
.hpm-economics-neg { color: var(--economics-gas-ink); }

/* ---- economics: verdict band — the header of the hero card ----
   The model's answer lives at the top of the chart card it explains.
   It stays sticky *within* the card, so it keeps answering while the
   tall card scrolls past, then hands over naturally. */
.hpm-economics-hero { padding: 0; }
.hpm-economics-hero-body { padding: 1.4rem 1.75rem 1.75rem; }
.hpm-economics-verdict-band {
    position: sticky;
    top: 0;
    z-index: 30;
    display: flex;
    align-items: center;
    gap: 0.4rem 1.4rem;
    flex-wrap: wrap;
    padding: 0.95rem 1.75rem;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: saturate(1.1) blur(8px);
    border-bottom: 1px solid var(--hpm-line);
    border-radius: calc(1rem - 1px) calc(1rem - 1px) 0 0;
}
.hpm-economics-verdict-main {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.3rem;
    min-width: 0;
}
.hpm-economics-verdict-label {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    font-size: 0.9375rem;
    color: var(--hpm-ink-soft);
    font-weight: 500;
}
.hpm-economics-verdict-num {
    font-size: 1.9rem;
    font-weight: 800;
    letter-spacing: -0.015em;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.hpm-economics-verdict-side { margin-left: auto; display: flex; gap: 1.6rem; align-items: baseline; }
.hpm-economics-verdict-stat { display: flex; flex-direction: column; gap: 0.1rem; text-align: right; }
.hpm-economics-verdict-stat .k {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: var(--hpm-muted);
    font-weight: 700;
}
.hpm-economics-verdict-stat .v { font-size: 1.0625rem; font-weight: 700; font-variant-numeric: tabular-nums; }

/* info icon + hover/focus tooltip (pure CSS — no bootstrap JS needed) */
.hpm-economics-tip {
    position: relative;
    display: inline-flex;
    margin-left: 0.25rem;
    color: var(--hpm-muted);
    cursor: help;
    vertical-align: baseline;
}
.hpm-economics-tip:hover, .hpm-economics-tip:focus { color: var(--hpm-blue-deep); }
.hpm-economics-tip:focus-visible {
    outline: 3px solid rgba(68, 179, 226, 0.55);
    outline-offset: 2px;
    border-radius: 50%;
}
.hpm-economics-tip .tipbox {
    position: absolute;
    bottom: calc(100% + 0.55rem);
    left: -0.75rem;
    width: 18rem;
    padding: 0.7rem 0.85rem;
    background: var(--hpm-ink);
    color: #fff;
    font-size: 0.8125rem;
    font-weight: 500;
    line-height: 1.5;
    text-align: left;
    text-transform: none;
    letter-spacing: normal;
    white-space: normal;
    border-radius: 0.6rem;
    box-shadow: 0 10px 28px rgba(20, 52, 74, 0.3);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.12s ease;
    pointer-events: none;
    z-index: 60;
}
.hpm-economics-tip:hover .tipbox, .hpm-economics-tip:focus .tipbox, .hpm-economics-tip:focus-within .tipbox {
    opacity: 1;
    visibility: visible;
}

/* ---- economics: headline levers ---- */
.hpm-economics-hl-cell + .hpm-economics-hl-cell {
    border-top: 1px solid var(--hpm-line);
    margin-top: 1.1rem;
    padding-top: 1rem;
}
.hpm-economics-hl-k {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hpm-muted);
    font-weight: 700;
}
.hpm-economics-hl-k .pip {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 0.2rem;
    background: var(--economics-hp);
    flex: 0 0 auto;
}
.hpm-economics-hl-k .pip.gas { background: var(--economics-gas); }
.hpm-economics-hl-k .pip.ink { background: var(--hpm-ink); }
.hpm-economics-hl-vals {
    display: flex;
    align-items: baseline;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin: 0.5rem 0 0.6rem;
}
.hpm-economics-hl-big {
    font-size: 1.75rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    line-height: 1;
    color: var(--hpm-ink);
    font-variant-numeric: tabular-nums;
}
.hpm-economics-hl-big.hp { color: var(--economics-hp-ink); }
.hpm-economics-hl-big.gas { color: var(--economics-gas-ink); }
.hpm-economics-hl-arrow { font-size: 1.1rem; color: var(--hpm-muted); }
.hpm-economics-hl-note { font-size: 0.875rem; color: var(--hpm-ink-soft); }
.hpm-economics-hl-marks {
    display: flex;
    justify-content: space-between;
    font-size: 0.8125rem;
    color: var(--hpm-muted);
    font-variant-numeric: tabular-nums;
}
.hpm-economics-hl-foot { font-size: 0.875rem; color: var(--hpm-muted); margin-top: 0.45rem; }
.hpm-economics-hl-grant {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-top: 0.6rem;
    font-size: 0.875rem;
    color: var(--hpm-ink-soft);
}
.hpm-economics-hl-grant .hpm-economics-inwrap input { width: 6rem; font-size: 0.8125rem; padding: 0.35rem 0.5rem; }

input[type=range].hpm-economics-slider {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 0.45rem;
    border-radius: 0.25rem;
    margin: 0.25rem 0 0.3rem;
    background: var(--economics-track);
    outline: none;
    cursor: pointer;
    display: block;
}
.hpm-economics-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 0.45rem;
    background: var(--hpm-card);
    border: 2px solid var(--economics-hp);
    box-shadow: 0 1px 3px rgba(20, 52, 74, 0.28);
    cursor: grab;
}
.hpm-economics-slider:active::-webkit-slider-thumb { cursor: grabbing; }
.hpm-economics-slider::-moz-range-thumb {
    width: 1rem;
    height: 1rem;
    border-radius: 0.4rem;
    background: var(--hpm-card);
    border: 2px solid var(--economics-hp);
    box-shadow: 0 1px 3px rgba(20, 52, 74, 0.28);
    cursor: grab;
}
.hpm-economics-slider:focus-visible {
    outline: 3px solid rgba(68, 179, 226, 0.55);
    outline-offset: 3px;
}

/* ---- economics: numeric inputs ---- */
.hpm-economics-inwrap { position: relative; display: flex; align-items: center; gap: 0.4rem; }
.hpm-economics-inwrap input {
    width: 6.4rem;
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hpm-ink);
    font-variant-numeric: tabular-nums;
    text-align: right;
    padding: 0.4rem 0.55rem;
    border: 1px solid var(--economics-line-strong);
    border-radius: 0.5rem;
    background: var(--hpm-card);
    -moz-appearance: textfield;
}
.hpm-economics-inwrap input:focus {
    outline: 3px solid rgba(68, 179, 226, 0.55);
    outline-offset: 0;
    border-color: var(--hpm-blue);
}
.hpm-economics-inwrap input::-webkit-outer-spin-button,
.hpm-economics-inwrap input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.hpm-economics-inwrap .unit {
    font-size: 0.8125rem;
    color: var(--hpm-muted);
    min-width: 2.1rem;
    font-weight: 600;
}
.hpm-economics-inwrap .prefix {
    position: absolute;
    left: 0.55rem;
    font-size: 0.8125rem;
    color: var(--hpm-muted);
    font-weight: 600;
    pointer-events: none;
}
.hpm-economics-inwrap .prefix + input { padding-left: 1.5rem; }

/* boiler fuel segmented control (amber active state) */
.hpm-economics-fuelseg { display: flex; margin-top: 0.6rem; }
.hpm-economics-fuelseg button { flex: 1; }
.hpm-economics-fuelseg button.active { background: var(--economics-gas); }

/* ---- economics: lever tabs (heat pump / boiler settings) ---- */
.hpm-economics-levertabs { display: flex; }
.hpm-economics-levertabs button { flex: 1; }
.hpm-economics-levertabs button.gasbtn.active { background: var(--economics-gas); }
input[type=range].hpm-economics-slider.gas::-webkit-slider-thumb { border-color: var(--economics-gas); }
input[type=range].hpm-economics-slider.gas::-moz-range-thumb { border-color: var(--economics-gas); }

/* ---- economics: breakdown bars ---- */
.hpm-economics-bars { display: flex; flex-direction: column; gap: 1rem; }
.hpm-economics-barrow-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.45rem;
}
.hpm-economics-barrow-head .swatch {
    width: 0.7rem;
    height: 0.7rem;
    border-radius: 0.2rem;
}
.hpm-economics-barrow-head .swatch.gas { background: var(--economics-gas); }
.hpm-economics-barrow-head .swatch.hp { background: var(--economics-hp); }
.hpm-economics-barrow-head .bname { font-weight: 700; font-size: 1rem; color: var(--hpm-ink); }
.hpm-economics-barrow-head .btotal {
    margin-left: auto;
    font-size: 1.0625rem;
    font-weight: 800;
    color: var(--hpm-ink);
    font-variant-numeric: tabular-nums;
}
.hpm-economics-bartrack {
    display: flex;
    height: 2.1rem;
    border-radius: 0.45rem;
    overflow: hidden;
    background: var(--hpm-sky);
    box-shadow: inset 0 0 0 1px var(--economics-line-strong);
}
.hpm-economics-barseg {
    height: 100%;
    border-right: 2px solid var(--hpm-card);
    transition: width 0.12s linear;
    min-width: 0;
    cursor: default;
}
.hpm-economics-barseg:last-child { border-right: none; }
.hpm-economics-baraxis {
    display: flex;
    justify-content: space-between;
    font-size: 0.8125rem;
    color: var(--hpm-muted);
    margin-top: 0.2rem;
    font-variant-numeric: tabular-nums;
}
.hpm-economics-seglegend { margin-top: 0; }
.hpm-economics-seglegend .hpm-swatch {
    border-radius: 0.2rem;
    background: linear-gradient(135deg, var(--economics-gas) 50%, var(--economics-hp) 50%);
}

/* ---- economics: net position chart ---- */
.hpm-economics-netwrap { margin-top: 1.4rem; padding-top: 1.25rem; border-top: 1px solid var(--hpm-line); }
.hpm-economics-netwrap h3 { font-size: 1rem; }
svg.hpm-economics-net { width: 100%; height: auto; display: block; }
.hpm-economics-net .zero { stroke: var(--hpm-ink); stroke-width: 1.3; }
.hpm-economics-net .nline { fill: none; stroke: var(--hpm-ink); stroke-width: 2.6; stroke-linecap: round; }
.hpm-economics-net .nlabel { font-size: 13px; font-weight: 700; font-family: inherit; }
.hpm-economics-net .ntick { font-size: 12px; fill: var(--hpm-muted); font-family: inherit; }
.hpm-economics-net .bedot { fill: var(--hpm-ink); }
.hpm-economics-net .betag { font-size: 13px; font-weight: 700; fill: var(--hpm-ink); font-family: inherit; }
.hpm-economics-net .nlabel,
.hpm-economics-net .betag {
    /* halo so labels stay legible where they cross the line or fills */
    paint-order: stroke;
    stroke: var(--hpm-card);
    stroke-width: 4px;
    stroke-linejoin: round;
}
.hpm-economics-anim {
    stroke-dasharray: 900;
    stroke-dashoffset: 900;
    animation: hpm-economics-draw 1s cubic-bezier(0.4, 0.05, 0.2, 1) forwards;
}
.hpm-economics-fadein { animation: hpm-economics-fade 0.9s ease forwards; opacity: 0; }
@keyframes hpm-economics-draw { to { stroke-dashoffset: 0; } }
@keyframes hpm-economics-fade { to { opacity: 1; } }

/* ---- economics: selected-system panel (third column) ---- */
.hpm-economics-syscard {
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 1rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 16px rgba(20, 52, 74, 0.05);
}
.hpm-economics-syspanel { position: sticky; top: 0.75rem; }
@media (max-width: 1199.98px) { .hpm-economics-syspanel { position: static; } }
.hpm-economics-syscard .photo {
    position: relative;
    aspect-ratio: 16 / 9;
    background: var(--hpm-sky-deep);
}
@media (min-width: 1200px) { .hpm-economics-syspanel .photo { aspect-ratio: 4 / 3; } }
.hpm-economics-syscard .photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.hpm-economics-syscard .photo .ph {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    font-size: 2.4rem;
    color: var(--hpm-blue-deep);
    opacity: 0.35;
}
.hpm-economics-syscard .photo .hx {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    padding: 0.15rem 0.6rem;
    border-radius: 2rem;
    background: rgba(255, 255, 255, 0.92);
    font-size: 0.78125rem;
    font-weight: 700;
}
.hpm-economics-syscard .body {
    padding: 1.1rem 1.25rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
}
.hpm-economics-syscard h3 { font-size: 1.0625rem; font-weight: 700; margin: 0; }
.hpm-economics-syscard .model { font-size: 0.875rem; color: var(--hpm-muted); }
.hpm-economics-installer {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--hpm-ink-soft);
    text-decoration: none;
    min-width: 0;
}
.hpm-economics-installer img {
    height: 1.5rem;
    width: auto;
    max-width: 5.5rem;
    object-fit: contain;
    flex: 0 0 auto;
}
.hpm-economics-installer span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.hpm-economics-installer[href]:hover { color: var(--hpm-blue-deep); }
.hpm-economics-syscard .hpm-economics-installer { margin-top: 0.15rem; }
.hpm-economics-syscard .desc { font-size: 0.9375rem; margin: 0.15rem 0 0; }
.hpm-economics-syscard .params {
    display: flex;
    gap: 1.25rem;
    margin-top: auto;
    padding-top: 0.8rem;
    border-top: 1px solid var(--hpm-line);
}
.hpm-economics-syscard .params .k {
    display: block;
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hpm-muted);
    font-weight: 700;
}
.hpm-economics-syscard .params .v {
    font-size: 1.0625rem;
    font-weight: 800;
    color: var(--hpm-ink);
    font-variant-numeric: tabular-nums;
}
.hpm-economics-syscard .cta {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
    margin-top: 0.7rem;
    font-size: 0.9375rem;
    font-weight: 600;
}
.hpm-economics-syscard .cta .apply { color: var(--economics-teal-ink); }
.hpm-economics-syscard .cta a { color: var(--hpm-muted); text-decoration: none; white-space: nowrap; }
.hpm-economics-syscard .cta a:hover { color: var(--hpm-blue-deep); }
.hpm-economics-clear {
    flex: 0 0 auto;
    background: none;
    border: none;
    padding: 0.15rem 0.3rem;
    border-radius: 0.4rem;
    color: var(--hpm-muted);
    font-size: 0.9rem;
    line-height: 1;
    cursor: pointer;
}
.hpm-economics-clear:hover { color: var(--hpm-ink); background: var(--hpm-sky); }

/* quick picks shown while no system is selected */
.hpm-economics-quickpick {
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 1rem;
    padding: 1.25rem 1.25rem 1.15rem;
    box-shadow: 0 4px 16px rgba(20, 52, 74, 0.05);
}
.hpm-economics-quickpick .qp-head {
    font-size: 0.8125rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hpm-muted);
    font-weight: 700;
    margin-bottom: 0.35rem;
}
.hpm-economics-quickpick .qp-sub { font-size: 0.875rem; margin: 0 0 0.8rem; }
.hpm-economics-quickpick .qp-list { display: grid; gap: 0.5rem; }
@media (max-width: 1199.98px) {
    .hpm-economics-quickpick .qp-list { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
}
.hpm-economics-quickpick .qp-item {
    display: grid;
    gap: 0.1rem;
    text-align: left;
    font: inherit;
    color: inherit;
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 0.75rem;
    padding: 0.6rem 0.8rem;
    cursor: pointer;
    transition: border-color 0.12s ease, background-color 0.12s ease;
}
.hpm-economics-quickpick .qp-item:hover { border-color: var(--hpm-blue); background: var(--hpm-sky); }
.hpm-economics-quickpick .qp-item:focus-visible {
    outline: 3px solid rgba(68, 179, 226, 0.55);
    outline-offset: 2px;
}
.hpm-economics-quickpick .qp-loc { font-weight: 700; color: var(--hpm-ink); font-size: 0.9375rem; line-height: 1.3; }
.hpm-economics-quickpick .qp-meta { font-size: 0.8125rem; color: var(--hpm-muted); }
.hpm-economics-hx-good { color: var(--economics-teal-ink); }
.hpm-economics-hx-part { color: var(--economics-gas-ink); }

@media (max-width: 991.98px) {
    .hpm-economics-verdict-side { margin-left: 0; }
}
@media (max-width: 767.98px) {
    .hpm-section { padding: 3rem 0; }
}
@media (prefers-reduced-motion: reduce) {
    .hpm-economics * { transition: none !important; }
    .hpm-economics-anim, .hpm-economics-fadein { animation: none; stroke-dashoffset: 0; opacity: 1; }
}

/* Hide the app until Vue has compiled the template, so raw {{ }} bindings
   never flash on screen. Vue removes v-cloak on mount. */
[v-cloak] { display: none !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<div class="hpm-home hpm-economics" id="economics" v-cloak>

    <!-- ============ Economics ============ -->
    <section class="hpm-section hpm-section-sky">
        <div class="container">
            <div class="hpm-eyebrow"><span class="hpm-eyebrow-num">05</span> Economics</div>
            <h2 class="hpm-display mb-3">What determines <span class="hpm-accent">heat pump economics?</span></h2>
            <p class="hpm-lead mb-5">
                SPF is only one of four main levers. The lifetime cost of a heat pump depends on the install cost, performance, electricity price and heat demand. This tool brings these factors together to show how they interact. Explore case studies from real monitored systems on HeatpumpMonitor.org, with a gas boiler as the reference case.
            </p>

            <!-- headline levers + hero chart + selected system -->
            <div class="row g-4">
                <!-- first column: the real system driving the model -->
                <div class="col-xl-3 col-lg-12">
                    <!-- selected system card -->
                    <div v-if="selectedSystem" class="hpm-economics-syscard hpm-economics-syspanel">
                        <div class="photo">
                            <img v-if="photos[selectedSystem.id]" :src="photos[selectedSystem.id]" :alt="'Outdoor unit at '+selectedSystem.location" loading="lazy">
                            <div v-else class="ph"><i class="bi bi-fan"></i></div>
                            <span class="hx" :class="selectedSystem.boundary===4 ? 'hpm-economics-hx-good' : 'hpm-economics-hx-part'" v-if="selectedSystem.boundary">H{{ selectedSystem.boundary }}</span>
                        </div>
                        <div class="body">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <h3>{{ selectedSystem.location }}</h3>
                                <button class="hpm-economics-clear" type="button" @click="clearSystem" title="Clear selected system" aria-label="Clear selected system"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div class="model">{{ selectedSystem.hp_manufacturer }} {{ selectedSystem.hp_model }}<span v-if="selectedSystem.hp_output"> &middot; {{ selectedSystem.hp_output }} kW</span></div>
                            <!-- href/target are dropped when there is no installer url, leaving a plain label -->
                            <a v-if="selectedSystem.installer" class="hpm-economics-installer"
                               :href="selectedSystem.installer_url || null"
                               :target="selectedSystem.installer_url ? '_blank' : null" rel="noopener"
                               :title="'Installed by '+selectedSystem.installer">
                                <img v-if="selectedSystem.installer_logo" :src="path+'theme/img/installers/'+selectedSystem.installer_logo" :alt="selectedSystem.installer+' logo'" loading="lazy">
                                <span>{{ selectedSystem.installer }}</span>
                            </a>
                            <p class="desc" v-if="selectedSystem.blurb">{{ selectedSystem.blurb }}</p>
                            <p class="desc" v-else-if="selectedSystem.property">{{ selectedSystem.property }}<span v-if="selectedSystem.floor_area"> &middot; {{ selectedSystem.floor_area }} m&sup2;</span><span v-if="selectedSystem.hp_type"> &middot; {{ selectedSystem.hp_type }}</span></p>
                            <div class="params">
                                <div><span class="k">Install</span><span class="v">{{ money(selectedSystem.cost) }}</span></div>
                                <div><span class="k">SPF</span><span class="v">{{ selectedSystem.cop.toFixed(1) }}</span></div>
                                <div><span class="k">Heat/yr</span><span class="v">{{ fmt(selectedSystem.heat) }} <small>kWh</small></span></div>
                            </div>
                            <div class="cta">
                                <span class="apply">&#10003; In the model</span>
                                <a :href="path+'system/view?id='+selectedSystem.id">View system <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- quick picks while nothing is selected -->
                    <div v-else class="hpm-economics-quickpick hpm-economics-syspanel">
                        <div class="qp-head">Try a real home</div>
                        <p class="qp-sub">Load a monitored system&rsquo;s recorded install cost, measured SPF and annual heat demand into the model.</p>
                        <div class="qp-list">
                            <button v-for="c in featuredCards" :key="c.id" type="button" class="qp-item" @click="applySystem(c)">
                                <span class="qp-loc">{{ c.location }}</span>
                                <span class="qp-meta">{{ c.hp_manufacturer }} {{ c.hp_model }} &middot; SPF {{ c.cop.toFixed(1) }} &middot; {{ money(c.cost) }}</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-4">
                    <div class="hpm-chart-card h-100">
                        <div class="hpm-economics-hl-cell">
                            <div class="hpm-economics-hl-k"><span class="pip ink"></span>Heat demand</div>
                            <div class="hpm-economics-hl-vals">
                                <span class="hpm-economics-hl-big">{{ fmt(hlDemand) }}</span>
                                <span class="hpm-economics-hl-note">kWh/yr</span>
                            </div>
                            <input type="range" class="hpm-economics-slider" min="4000" max="30000" step="500"
                                   :value="hlDemand" @input="hlDemand = +$event.target.value"
                                   :style="hlFill(hlDemand,4000,30000)" aria-label="Annual heat demand in kWh">
                            <div class="hpm-economics-hl-marks"><span>4,000</span><span>30,000</span></div>
                        </div>

                        <!-- tab between the two technologies' settings -->
                        <div class="hpm-economics-hl-cell">
                            <div class="hpm-seg hpm-economics-levertabs" role="tablist" aria-label="Show settings for">
                                <button type="button" role="tab" :class="{active: leverTab==='hp'}"
                                        :aria-selected="leverTab==='hp' ? 'true' : 'false'" @click="leverTab='hp'">Heat pump</button>
                                <button type="button" role="tab" class="gasbtn" :class="{active: leverTab==='gas'}"
                                        :aria-selected="leverTab==='gas' ? 'true' : 'false'" @click="leverTab='gas'">Boiler</button>
                            </div>
                        </div>

                        <!-- heat pump settings -->
                        <template v-if="leverTab==='hp'">
                            <div class="hpm-economics-hl-cell">
                                <div class="hpm-economics-hl-k"><span class="pip"></span>Heat pump &middot; SPF</div>
                                <div class="hpm-economics-hl-vals">
                                    <span class="hpm-economics-hl-big hp">{{ combinedScop.hp.toFixed(2) }}</span>
                                    <span class="hpm-economics-hl-note">units of heat per unit of electricity</span>
                                </div>
                                <input type="range" class="hpm-economics-slider" min="1.5" max="6" step="0.05"
                                       :value="hlScop" @input="hlScop = +$event.target.value"
                                       :style="hlFill(hlScop,1.5,6)" aria-label="Heat pump seasonal performance factor">
                                <div class="hpm-economics-hl-marks"><span>1.5</span><span>6.0</span></div>
                            </div>
                            <div class="hpm-economics-hl-cell">
                                <div class="hpm-economics-hl-k"><span class="pip"></span>Heat pump &middot; install cost</div>
                                <div class="hpm-economics-hl-vals">
                                    <span class="hpm-economics-hl-big">{{ money(s.hp.capex) }}</span>
                                    <span class="hpm-economics-hl-arrow">&rarr;</span>
                                    <span class="hpm-economics-hl-big hp">{{ money(netInstall) }}</span>
                                </div>
                                <input type="range" class="hpm-economics-slider" min="5000" max="30000" step="100"
                                       v-model.number="s.hp.capex"
                                       :style="hlFill(s.hp.capex,5000,30000)" aria-label="Heat pump install cost">
                                <div class="hpm-economics-hl-marks"><span>&pound;5k</span><span>&pound;30k</span></div>
                                <div class="hpm-economics-hl-grant">
                                    <label for="economics-grant-in">After the grant</label>
                                    <div class="hpm-economics-inwrap"><span class="prefix">&pound;</span><input id="economics-grant-in" type="number" v-model.number="s.hp.grant" step="100"></div>
                                </div>
                            </div>
                            <div class="hpm-economics-hl-cell">
                                <div class="hpm-economics-hl-k"><span class="pip"></span>Electricity price</div>
                                <div class="hpm-economics-hl-vals">
                                    <span class="hpm-economics-hl-big hp">{{ s.hp.price.toFixed(1) }}p</span>
                                    <span class="hpm-economics-hl-note">per kWh &middot; {{ r.priceRatio.toFixed(1) }}&times; the boiler fuel price</span>
                                </div>
                                <input type="range" class="hpm-economics-slider" min="10" max="26" step="0.1"
                                       v-model.number="s.hp.price"
                                       :style="hlFill(s.hp.price,10,26)" aria-label="Electricity price in pence per kWh">
                                <div class="hpm-economics-hl-marks"><span>10p</span><span>26p</span></div>
                                <div class="hpm-economics-hl-grant">
                                    <label for="economics-hp-emissions">Carbon intensity</label>
                                    <div class="hpm-economics-inwrap"><input id="economics-hp-emissions" type="number" v-model.number="s.hp.emissions" step="1"><span class="unit">g CO&#8322;e/kWh</span></div>
                                </div>
                            </div>
                        </template>

                        <!-- boiler settings -->
                        <template v-else>
                            <div class="hpm-economics-hl-cell">
                                <div class="hpm-economics-hl-k"><span class="pip gas"></span>Boiler &middot; fuel</div>
                                <div class="hpm-seg hpm-economics-fuelseg" role="radiogroup" aria-label="Boiler fuel">
                                    <button v-for="f in fuels" :key="f.key" type="button"
                                            :class="{active: s.fuel===f.key}" :aria-pressed="s.fuel===f.key ? 'true' : 'false'"
                                            @click="setFuel(f.key)">{{ f.label }}</button>
                                </div>
                            </div>
                            <div class="hpm-economics-hl-cell">
                                <div class="hpm-economics-hl-k"><span class="pip gas"></span>Boiler &middot; efficiency</div>
                                <div class="hpm-economics-hl-vals">
                                    <span class="hpm-economics-hl-big gas">{{ Math.round(gasEffPct) }}%</span>
                                    <span class="hpm-economics-hl-note">of fuel energy becomes useful heat</span>
                                </div>
                                <input type="range" class="hpm-economics-slider gas" min="60" max="100" step="1"
                                       :value="gasEffPct" @input="gasEffPct = +$event.target.value"
                                       :style="hlFill(gasEffPct,60,100,'var(--economics-gas)')" aria-label="Boiler efficiency in percent">
                                <div class="hpm-economics-hl-marks"><span>60%</span><span>100%</span></div>
                            </div>
                            <div class="hpm-economics-hl-cell">
                                <div class="hpm-economics-hl-k"><span class="pip gas"></span>Boiler &middot; fuel price</div>
                                <div class="hpm-economics-hl-vals">
                                    <span class="hpm-economics-hl-big gas">{{ s.gas.price.toFixed(1) }}p</span>
                                    <span class="hpm-economics-hl-note">per kWh &middot; electricity costs {{ r.priceRatio.toFixed(1) }}&times; more</span>
                                </div>
                                <input type="range" class="hpm-economics-slider gas" min="3" max="15" step="0.1"
                                       v-model.number="s.gas.price"
                                       :style="hlFill(s.gas.price,3,15,'var(--economics-gas)')" aria-label="Boiler fuel price in pence per kWh">
                                <div class="hpm-economics-hl-marks"><span>3p</span><span>15p</span></div>
                                <div class="hpm-economics-hl-grant">
                                    <label for="economics-standing-in">Standing charge</label>
                                    <div class="hpm-economics-inwrap"><input id="economics-standing-in" type="number" v-model.number="s.gas.standing" step="1"><span class="unit">p/day</span></div>
                                </div>
                            </div>
                            <div class="hpm-economics-hl-cell">
                                <div class="hpm-economics-hl-k"><span class="pip gas"></span>Boiler &middot; install cost</div>
                                <div class="hpm-economics-hl-vals">
                                    <span class="hpm-economics-hl-big gas">{{ money(s.gas.capex) }}</span>
                                    <span class="hpm-economics-hl-note">like-for-like replacement</span>
                                </div>
                                <input type="range" class="hpm-economics-slider gas" min="500" max="8000" step="100"
                                       v-model.number="s.gas.capex"
                                       :style="hlFill(s.gas.capex,500,8000,'var(--economics-gas)')" aria-label="Boiler install cost">
                                <div class="hpm-economics-hl-marks"><span>&pound;500</span><span>&pound;8k</span></div>
                                <div class="hpm-economics-hl-grant">
                                    <label for="economics-gas-emissions">Fuel carbon intensity</label>
                                    <div class="hpm-economics-inwrap"><input id="economics-gas-emissions" type="number" v-model.number="s.gas.emissions" step="1"><span class="unit">g CO&#8322;e/kWh</span></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- HERO: verdict band + cost breakdown + net position -->
                <div class="col-xl-6 col-lg-8">
                    <div class="hpm-chart-card hpm-economics-hero h-100" id="economics-calc">
                        <!-- verdict band: the model's answer, pinned to the top of the card -->
                        <div class="hpm-economics-verdict-band">
                            <div class="hpm-economics-verdict-main">
                                <span class="hpm-economics-verdict-label">Compared with the {{ boilerName.toLowerCase() }}, the heat pump</span>
                                <span class="hpm-economics-verdict-num" :class="r.billSaving>=0 ? 'hpm-economics-pos' : 'hpm-economics-neg'">
                                    {{ r.billSaving>=0 ? 'saves '+money(r.billSaving)+'/yr' : 'adds '+money(-r.billSaving)+'/yr' }}
                                </span>
                            </div>
                            <div class="hpm-economics-verdict-side">
                                <div class="hpm-economics-verdict-stat">
                                    <span class="k">Break-even</span>
                                    <span class="v">{{ breakevenText }}</span>
                                </div>
                                <div class="hpm-economics-verdict-stat">
                                    <span class="k">Carbon saving</span>
                                    <span class="v" :class="annualAbate>=0 ? 'hpm-economics-pos' : 'hpm-economics-neg'">{{ annualAbate.toFixed(1) }} t/yr</span>
                                </div>
                            </div>
                        </div>
                        <div class="hpm-economics-hero-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h3>What the {{ s.horizon }}-year cost is made of</h3>
                                    <div class="hpm-chart-sub mb-0">Bar length is total lifetime cost, split into upfront cost, energy and maintenance.</div>
                                </div>
                                <div class="hpm-legend hpm-economics-seglegend">
                                    <span><span class="hpm-swatch" style="opacity:1"></span>Upfront</span>
                                    <span v-if="hasFinance"><span class="hpm-swatch" style="opacity:.8"></span>Finance</span>
                                    <span><span class="hpm-swatch" style="opacity:.6"></span>Energy</span>
                                    <span><span class="hpm-swatch" style="opacity:.4"></span>Maintenance</span>
                                </div>
                            </div>

                            <div class="hpm-economics-bars">
                                <div v-for="b in bars" :key="b.key">
                                    <div class="hpm-economics-barrow-head">
                                        <span class="swatch" :class="b.key"></span>
                                        <span class="bname">{{ b.name }}</span>
                                        <span class="btotal">{{ money(b.total) }}</span>
                                    </div>
                                    <div class="hpm-economics-bartrack" role="img" :aria-label="b.name+' lifetime cost '+money(b.total)">
                                        <div class="hpm-economics-barseg" v-for="(seg,si) in b.segs" :key="si"
                                             :style="{width:(seg.value/barMax*100)+'%', background:b.color, opacity:seg.opacity}"
                                             :title="seg.label+': '+money(seg.value)"></div>
                                    </div>
                                </div>
                                <div class="hpm-economics-baraxis"><span>&pound;0</span><span>{{ kshort(barMax) }}</span></div>
                            </div>

                            <!-- net position over time -->
                            <div class="hpm-economics-netwrap">
                                <h3>Heat pump&rsquo;s position over time</h3>
                                <div class="hpm-chart-sub">{{ netCopy }}</div>
                                <!-- :view-box.camel, not :viewBox — in-DOM templates lowercase attribute names, and SVG ignores a lowercase viewbox -->
                                <svg class="hpm-economics-net" :view-box.camel="'0 0 '+NW+' '+NH" preserveAspectRatio="xMidYMid meet" role="img" :aria-label="ariaChart">
                                    <!-- signed fills -->
                                    <polygon v-for="(f,i) in netFills" :key="'f'+i" :points="f.points" :fill="f.color" :class="animate?'hpm-economics-fadein':''" opacity="0.14"></polygon>
                                    <!-- zero baseline -->
                                    <line class="zero" :x1="nx(0)" :x2="nx(xMax)" :y1="ny(0)" :y2="ny(0)"></line>
                                    <!-- net line -->
                                    <polyline class="nline" :class="animate?'hpm-economics-anim':''" :points="netLine"></polyline>
                                    <!-- endpoints -->
                                    <text class="nlabel" :x="nx(0)" :y="ny(net0)+labelDy(net0)" text-anchor="start" :fill="net0>0?'var(--economics-gas-ink)':'var(--economics-hp-ink)'">{{ netLabel(net0) }}</text>
                                    <text class="nlabel" :x="nx(xMax)" :y="ny(netH)+labelDy(netH)" text-anchor="end" :fill="netH>0?'var(--economics-gas-ink)':'var(--economics-hp-ink)'">{{ netLabel(netH) }}</text>
                                    <!-- break-even marker -->
                                    <g v-if="beIn">
                                        <circle class="bedot" :cx="nx(r.be)" :cy="ny(0)" r="4.5"></circle>
                                        <text class="betag" :x="nx(r.be)" :y="ny(0)-9" :text-anchor="r.be>xMax*0.7?'end':'middle'">break-even &middot; yr {{ r.be.toFixed(1) }}</text>
                                    </g>
                                    <!-- x ticks -->
                                    <text class="ntick" :x="nx(0)" :y="NH-4" text-anchor="start">yr 0</text>
                                    <text class="ntick" :x="nx(xMax)" :y="NH-4" text-anchor="end">yr {{ s.horizon }}</text>
                                </svg>
                            </div>

                            <!-- cost of CO2 abated — society's number, kept as a footnote -->
                            <p class="hpm-finder-footnote mb-0">
                                Cost of CO&#8322; abated: <span :class="r.abateGBP<0 ? 'hpm-economics-pos fw-semibold' : ''">{{ r.abateGBP>=0 ? '£'+Math.round(r.abateGBP)+'/t' : '−£'+Math.round(-r.abateGBP)+'/t (a net saving)' }}</span><span class="hpm-economics-tip" tabindex="0" aria-label="How the cost of carbon abated is worked out"><i class="bi bi-info-circle" aria-hidden="true"></i><span class="tipbox" role="tooltip">
                                    Society&rsquo;s view of the same numbers: counting the {{ money(s.hp.grant) }} grant as a cost,
                                    the heat pump
                                    {{ r.societal>=0 ? 'costs society '+money(r.societal)+' more' : 'saves society '+money(-r.societal) }}
                                    over {{ s.horizon }} years &mdash; its lifetime premium to society. Spread across the
                                    {{ r.abate.toFixed(1) }} tonnes of CO&#8322;e avoided, that&rsquo;s
                                    {{ r.abateGBP>=0 ? '£'+Math.round(r.abateGBP)+' ($'+Math.round(r.abateUSD)+') per tonne' : 'a net saving of £'+Math.round(-r.abateGBP)+' ($'+Math.round(-r.abateUSD)+') per tonne abated' }}.
                                </span></span>
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>

<script>

    var path = "<?php echo $path; ?>";

    // Systems with a recorded installation cost (before subtracting grant),
    // embedded server-side like the home page datasets for a fast first paint
    var economics_systems = <?php echo json_encode($economics_systems); ?>;

    // The curated selection of systems offered in the side panel, with a
    // hand-written description shown on the selected-system card. Only ids
    // present in the economics_systems dataset are rendered. Edit freely — the
    // model/SPF/cost line under each one comes from live data.
    //
    // An optional `defaults` object sets system-specific counterfactual
    // assumptions applied when the system is selected (and reset to the
    // standard defaults when a system without them is selected):
    //   fuel:        boiler fuel preset key ("gas" | "oil" | "lpg")
    //   boilerCapex: like-for-like boiler replacement cost (£)
    //   grant:       grant amount (£), or "full" for one covering the whole install
    var ECON_FEATURED = [
        {id: 685,  blurb: "A 9 kW Octopus Cosy installed by Octopus Energy Services in Pudsey, Leeds — one of the lowest installation costs recorded on HeatpumpMonitor."},
        {id: 620,  blurb: "A compact 5 kW Vaillant Arotherm+ installed by Boxt in Oxford, a good example of a low heat demand home."},
        {id: 980,  blurb: "A 9 kW Daikin Altherma 3 installed by Octopus Energy Services in Newcastle upon Tyne."},
        {id: 552,  blurb: "A 7 kW Vaillant Arotherm+ installed by On Point Plumbing in Denham, Buckinghamshire."},
        {id: 723,  blurb: "A self-installed 16 kW Samsung Gen 7 in Wymondham, Norfolk, running as a hybrid alongside the existing boiler."},
        {id: 68,   blurb: "A 10 kW Viessmann Vitocal 150-A in Sheffield installed by Damon Blakemore — one of the best performing systems on the platform."},
        {id: 1163, blurb: "A 32 kW Viessmann Vitocal 150-A cascade serving Caban Cyf a community cafe and office building in Brynrefail, Gwynedd. High temp hot water demand for the cafe drops the SPF. Grant funded by the Welsh Government.",
         // replaces an LPG twin-boiler commercial install (~£7k for redundancy);
         // the whole heat pump install was covered by a grant
         defaults: {fuel: "lpg", boilerCapex: 7000, grant: "full"}}
    ];

    // ---- default assumptions (a typical installation today) ----
    var ECON_DEFAULTS = {
        horizon: 15, spaceHeat: 8000, hotWater: 2000, heatIncrease: 0,
        financeOn: false, financeRate: 5, downpayment: 3000, financeTerm: 10,
        fuel: "gas",
        gas: {scopSpace: 0.85, scopHW: 0.85, price: 7.33, standing: 29.04, emissions: 210, capex: 3000, maint: 150},
        hp: {scopSpace: 3.91, scopHW: 3.04, price: 23.00, emissions: 75, capex: 12000, grant: 7500, maint: 150}
    };

    // Pristine snapshot of the defaults — `s` below is bound straight to
    // ECON_DEFAULTS and mutated by user edits, so system-specific defaults
    // are reverted against this copy when switching systems.
    var ECON_BASE = JSON.parse(JSON.stringify(ECON_DEFAULTS));

    // boiler fuel presets: unit price, standing charge and carbon intensity
    // (oil & LPG are delivered fuels — no standing charge)
    var ECON_FUELS = [
        {key: "gas", label: "Gas", price: 7.33, standing: 29.04, emissions: 210},
        {key: "oil", label: "Oil", price: 9.20, standing: 0, emissions: 298},
        {key: "lpg", label: "LPG", price: 9.20, standing: 0, emissions: 241}
    ];

    var app = new Vue({
        el: '#economics',
        data: {
            path: path,
            s: ECON_DEFAULTS,
            fuels: ECON_FUELS,
            leverTab: "hp",       // which technology's settings the lever panel shows
            animate: true,        // draw-in animation on first render only
            // real systems with recorded installation costs
            systems: economics_systems || [],
            selectedSystemId: null,
            photos: {},            // system id -> outdoor unit thumbnail url
            // net-position chart geometry
            NW: 560, NH: 190, NP: {l: 14, r: 14, t: 34, b: 34}
        },
        watch: {
            // hot-water SCoP entry is hidden for now — keep it tracking the
            // single visible boiler efficiency
            "s.gas.scopSpace": function(v) { this.s.gas.scopHW = v; }
        },
        mounted: function() {
            var self = this;
            setTimeout(function() { self.animate = false; }, 1200);
        },
        computed: {
            // ---------- the model ----------
            r: function() {
                var s = this.s;
                var Y = Math.max(1, s.horizon || 1), g = s.gas, h = s.hp;
                // fuel bought per year (kWh) to meet the heat demand
                var Lg = s.spaceHeat / g.scopSpace + s.hotWater / g.scopHW;
                var Lh = s.spaceHeat * (1 + s.heatIncrease / 100) / h.scopSpace + s.hotWater / h.scopHW;
                // annual running cost. Electricity standing charge is paid in both
                // scenarios so it cancels; only the boiler fuel's enters the comparison.
                var Mg = g.price / 100 * Lg + g.standing * 365 / 100;
                var Mh = h.price / 100 * Lh;
                // finance only applies when the toggle is on; otherwise a straight cash purchase
                var finDown = s.financeOn ? s.downpayment : 0;
                var fm = 1 + (s.financeOn ? s.financeRate / 100 * s.financeTerm : 0);
                var upfrontG = g.capex;
                var upfrontH = (h.capex - h.grant - finDown) * fm + finDown;
                var slopeG = Mg + g.maint, slopeH = Mh + h.maint;
                var Ng = upfrontG + slopeG * Y, Nh = upfrontH + slopeH * Y;
                // break-even: years of running savings needed to repay the upfront premium
                var denom = slopeG - slopeH;
                var be = null;
                if (upfrontH <= upfrontG) be = 0;
                else if (denom > 0) be = (upfrontH - upfrontG) / denom;
                // societal view: the grant is a real cost
                var societal = Nh + h.grant - Ng;
                var abate = (g.emissions * Lg - h.emissions * Lh) * Y / 1e6;
                var abateGBP = abate !== 0 ? societal / abate : NaN;
                return {
                    Mg: Mg, Mh: Mh, upfrontG: upfrontG, upfrontH: upfrontH, Ng: Ng, Nh: Nh,
                    premium: Nh - Ng, billSaving: Mg - Mh, be: be,
                    societal: societal, abate: abate,
                    abateGBP: isFinite(abateGBP) ? abateGBP : -1,
                    abateUSD: isFinite(abateGBP) ? abateGBP * 1.36 : -1,
                    priceRatio: g.price > 0 ? h.price / g.price : 0
                };
            },

            // ---------- combined (blended) SCoP ----------
            // useful heat delivered ÷ energy consumed, weighting space & hot water by demand
            combinedScop: function() {
                var s = this.s, g = s.gas, h = s.hp;
                var spaceG = s.spaceHeat, spaceH = s.spaceHeat * (1 + s.heatIncrease / 100);
                var gasHeat = spaceG + s.hotWater;
                var hpHeat = spaceH + s.hotWater;
                var gasUse = (g.scopSpace > 0 ? spaceG / g.scopSpace : 0) + (g.scopHW > 0 ? s.hotWater / g.scopHW : 0);
                var hpUse = (h.scopSpace > 0 ? spaceH / h.scopSpace : 0) + (h.scopHW > 0 ? s.hotWater / h.scopHW : 0);
                return { gas: gasUse > 0 ? gasHeat / gasUse : 0, hp: hpUse > 0 ? hpHeat / hpUse : 0 };
            },

            boilerName: function() {
                var s = this.s;
                var f = this.fuels.find(function(x) { return x.key === s.fuel; });
                return (f ? f.label : "Gas") + " boiler";
            },

            // boiler efficiency edited as a percentage; the hot-water SCoP tracks it via the watch above
            gasEffPct: {
                get: function() { return Math.round(this.s.gas.scopSpace * 1000) / 10; },
                set: function(v) { if (v > 0) this.s.gas.scopSpace = v / 100; }
            },

            // ---------- headline levers ----------
            // slider writes scale both SCoP fields by the same factor, so the blend
            // lands on the target while the space/water ratio is kept
            hlScop: {
                get: function() { return this.combinedScop.hp; },
                set: function(v) {
                    var cur = this.combinedScop.hp;
                    if (!(cur > 0) || !(v > 0)) return;
                    var k = v / cur;
                    this.s.hp.scopSpace = Math.round(this.s.hp.scopSpace * k * 100) / 100;
                    this.s.hp.scopHW = Math.round(this.s.hp.scopHW * k * 100) / 100;
                }
            },
            netInstall: function() { return Math.max(0, this.s.hp.capex - this.s.hp.grant); },
            // slider writes scale space heat & hot water by the same factor,
            // keeping the space/water split
            hlDemand: {
                get: function() { return (this.s.spaceHeat || 0) + (this.s.hotWater || 0); },
                set: function(v) {
                    var cur = (this.s.spaceHeat || 0) + (this.s.hotWater || 0);
                    if (!(cur > 0) || !(v > 0)) return;
                    var k = v / cur;
                    this.s.spaceHeat = Math.round(this.s.spaceHeat * k / 50) * 50;
                    this.s.hotWater = Math.round(this.s.hotWater * k / 50) * 50;
                }
            },

            // ---------- breakdown bars ----------
            hasFinance: function() { return this.s.financeOn && this.s.financeTerm > 0 && this.s.financeRate > 0; },
            barMax: function() { return Math.max(this.r.Ng, this.r.Nh, 1); },
            bars: function() {
                var s = this.s, r = this.r, g = s.gas, h = s.hp, Y = Math.max(1, s.horizon);
                var finDown = s.financeOn ? s.downpayment : 0;
                var fm = 1 + (s.financeOn ? s.financeRate / 100 * s.financeTerm : 0);
                // opacity steps run dark→light down the stack so segments read
                // as one ramp per technology (upfront darkest, upkeep lightest)
                var O = {up: 1, fin: 0.8, en: 0.6, ma: 0.4};
                var gas = { key: "gas", name: this.boilerName, color: "var(--economics-gas)", total: r.Ng, segs: [
                    {label: "Upfront", value: g.capex, opacity: O.up},
                    {label: "Energy & standing charge", value: r.Mg * Y, opacity: O.en},
                    {label: "Maintenance", value: g.maint * Y, opacity: O.ma}
                ]};
                var hUp = h.capex - h.grant;
                var hFin = (h.capex - h.grant - finDown) * (fm - 1);
                if (hFin < 0) { hUp += hFin; hFin = 0; }       // keep total = Nh
                var hpSegs = [{label: "Upfront (net of grant)", value: Math.max(0, hUp), opacity: O.up}];
                if (hFin > 0.5) hpSegs.push({label: "Finance interest", value: hFin, opacity: O.fin});
                hpSegs.push({label: "Energy", value: r.Mh * Y, opacity: O.en});
                hpSegs.push({label: "Maintenance", value: h.maint * Y, opacity: O.ma});
                var hp = { key: "hp", name: "Heat pump", color: "var(--economics-hp)", total: r.Nh, segs: hpSegs };
                return [gas, hp];
            },

            // ---------- net-position chart ----------
            xMax: function() { return Math.max(1, this.s.horizon); },
            net0: function() { return this.r.upfrontH - this.r.upfrontG; },   // premium at install
            netH: function() { return this.r.premium; },                      // difference at horizon
            netLo: function() { return Math.min(0, this.net0, this.netH); },
            netHi: function() { return Math.max(0, this.net0, this.netH); },
            beIn: function() { return this.r.be !== null && this.r.be > 0 && this.r.be <= this.xMax; },
            netLine: function() {
                return this.nx(0) + "," + this.ny(this.net0) + " " + this.nx(this.xMax) + "," + this.ny(this.netH);
            },
            netFills: function() {
                var x0 = this.nx(0), xH = this.nx(this.xMax), z = this.ny(0);
                var y0 = this.ny(this.net0), yH = this.ny(this.netH);
                var colOf = function(v) { return v >= 0 ? "var(--economics-gas)" : "var(--economics-hp)"; };
                if (this.beIn) {
                    var xc = this.nx(this.r.be);
                    return [
                        {points: x0 + "," + z + " " + x0 + "," + y0 + " " + xc + "," + z, color: colOf(this.net0)},
                        {points: xc + "," + z + " " + xH + "," + yH + " " + xH + "," + z, color: colOf(this.netH)}
                    ];
                }
                return [{points: x0 + "," + z + " " + x0 + "," + y0 + " " + xH + "," + yH + " " + xH + "," + z, color: colOf((this.net0 + this.netH) / 2)}];
            },

            // ---------- real systems ----------
            // the curated quick-pick list: hardcoded ids joined onto live data,
            // so a system that loses eligibility simply drops out
            featuredCards: function() {
                var byId = {};
                this.systems.forEach(function(sys) { byId[sys.id] = sys; });
                return ECON_FEATURED
                    .filter(function(f) { return byId[f.id]; })
                    .map(function(f) { return Object.assign({blurb: f.blurb}, byId[f.id]); });
            },
            // the system currently loaded into the model, with its featured
            // blurb attached when it has one
            selectedSystem: function() {
                var id = this.selectedSystemId;
                if (id === null) return null;
                var sys = this.systems.find(function(x) { return x.id === id; });
                if (!sys) return null;
                var featured = ECON_FEATURED.find(function(f) { return f.id === id; });
                return featured ? Object.assign({blurb: featured.blurb}, sys) : sys;
            },

            // tonnes of CO2e avoided per year — the horizon total spread back out
            annualAbate: function() {
                return this.r.abate / Math.max(1, this.s.horizon || 1);
            },
            // subtitle for the net-position chart, phrased for the current inputs
            netCopy: function() {
                var behind = this.money(Math.abs(this.net0));
                if (this.net0 > 0) {
                    if (this.beIn) {
                        return "Starts " + behind + " behind on install cost and recovers it through lower running costs, breaking even in year " + this.r.be.toFixed(1) + ".";
                    }
                    return "Starts " + behind + " behind on install cost; at these running costs the gap doesn’t close within " + this.s.horizon + " years.";
                }
                if (this.netH > 0) {
                    return "Starts " + behind + " ahead on install cost, but higher running costs erode the lead over time.";
                }
                return "Starts " + behind + " ahead on install cost and stays ahead — there is no premium to recover.";
            },
            breakevenText: function() {
                if (this.r.be === null) return "never";
                if (this.r.be === 0) return "0 yrs";
                if (this.r.be > this.s.horizon) return "~" + this.r.be.toFixed(0) + "y *";
                return this.r.be.toFixed(1) + " yrs";
            },
            ariaChart: function() {
                var p = this.r.premium;
                var verdict = p > 0 ? this.money(p) + " more expensive" : this.money(-p) + " cheaper";
                return "Over " + this.s.horizon + " years the heat pump is " + verdict + " than the "
                    + this.boilerName.toLowerCase() + ". It starts " + this.netLabel(this.net0) + " on upfront cost.";
            }
        },
        methods: {
            // Load a real system's install cost, measured SPF and annual heat
            // demand into the model, plus its counterfactual defaults (boiler
            // fuel, replacement cost, grant); other levers stay as the user set them
            applySystem: function(sys) {
                var heat = Math.max(1, Math.round(sys.heat));
                var total = (this.s.spaceHeat || 0) + (this.s.hotWater || 0);
                if (total > 0) {
                    // keep the current space/hot-water split
                    var k = heat / total;
                    this.s.spaceHeat = Math.round(this.s.spaceHeat * k);
                    this.s.hotWater = heat - this.s.spaceHeat;
                } else {
                    this.s.spaceHeat = Math.round(heat * 0.8);
                    this.s.hotWater = heat - this.s.spaceHeat;
                }
                // measured SPF already blends space heating and hot water, so
                // set both SCoP fields to it — the blend then equals the SPF
                this.s.hp.scopSpace = sys.cop;
                this.s.hp.scopHW = sys.cop;
                this.s.hp.capex = Math.round(sys.cost);
                // counterfactual assumptions: reset to the standard defaults,
                // then apply any system-specific ones (e.g. 1163 replaces a
                // commercial LPG install with a full grant)
                var featured = ECON_FEATURED.find(function(f) { return f.id === sys.id; });
                var d = (featured && featured.defaults) || {};
                this.setFuel(d.fuel || ECON_BASE.fuel);
                this.s.gas.capex = d.boilerCapex != null ? d.boilerCapex : ECON_BASE.gas.capex;
                this.s.hp.grant = d.grant === "full" ? this.s.hp.capex
                    : (d.grant != null ? d.grant : ECON_BASE.hp.grant);
                this.selectedSystemId = sys.id;
                this.leverTab = "hp";   // show the panel the loaded values land in
                this.fetchPhoto(sys.id);
                var el = document.getElementById("economics-calc");
                if (el) el.scrollIntoView({behavior: "smooth", block: "start"});
            },
            clearSystem: function() {
                this.selectedSystemId = null;
            },
            // Lazily fetch the outdoor-unit photo for a selected system. Same
            // endpoint the system pages use; public systems need no auth.
            fetchPhoto: function(id) {
                var self = this;
                if (this.photos[id]) return;
                fetch(path + "system/photos?id=" + id)
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (!d || !d.success || !d.photos || !d.photos.length) return;
                        var photo = null;
                        for (var i = 0; i < d.photos.length; i++) {
                            if (d.photos[i].photo_type == "outdoor_unit") { photo = d.photos[i]; break; }
                        }
                        if (!photo) photo = d.photos[0];
                        // prefer the ~300px thumbnail; fall back to the original
                        var url = photo.url;
                        if (photo.thumbnails && photo.thumbnails.length) {
                            var best = null;
                            photo.thumbnails.forEach(function(t) {
                                if (t.width && (!best || Math.abs(t.width - 300) < Math.abs(best.width - 300))) best = t;
                            });
                            if (best && best.url) url = best.url;
                        }
                        if (url) self.$set(self.photos, id, path + url);
                    })
                    .catch(function() { /* photo is decorative — ignore failures */ });
            },
            setFuel: function(k) {
                var f = this.fuels.find(function(x) { return x.key === k; });
                if (!f) return;
                this.s.fuel = k;
                this.s.gas.price = f.price;
                this.s.gas.standing = f.standing;
                this.s.gas.emissions = f.emissions;
            },
            hlFill: function(v, min, max, color) {
                var p = Math.min(100, Math.max(0, (v - min) / (max - min) * 100));
                return { background: "linear-gradient(90deg, " + (color || "var(--economics-hp)") + " " + p + "%, var(--economics-track) " + p + "%)" };
            },
            // net-position chart scales
            nx: function(t) { return this.NP.l + (t / this.xMax) * (this.NW - this.NP.l - this.NP.r); },
            ny: function(v) {
                var pad = Math.max(1, (this.netHi - this.netLo)) * 0.16;
                var lo = this.netLo - pad, hi = this.netHi + pad;
                return this.NP.t + (hi - v) / (hi - lo) * (this.NH - this.NP.t - this.NP.b);
            },
            labelDy: function(v) { return v >= 0 ? -8 : 16; },   // above (behind) or below (ahead) the point
            netLabel: function(v) {
                if (Math.round(Math.abs(v)) === 0) return "level";
                return this.money(Math.abs(v)) + (v > 0 ? " behind" : " ahead");
            },
            // ---------- formatting ----------
            money: function(v) {
                return "£" + Math.round(v).toLocaleString("en-GB");
            },
            fmt: function(v) {
                return Math.round(v).toLocaleString("en-GB");
            },
            kshort: function(v) {
                if (v >= 1000) return "£" + (v / 1000).toFixed(v % 1000 === 0 ? 0 : 1) + "k";
                return "£" + v;
            }
        }
    });
</script>
