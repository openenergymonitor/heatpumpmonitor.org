<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

global $path;
?>
<style>
/* ===================================================================
   Home landing page — concept
   Static HTML/CSS only for now: all figures are dummy placeholders,
   data wiring + JS to follow.
   =================================================================== */

.hpm-home {
    /* Brand + page tokens */
    --hpm-blue: #44b3e2;          /* existing brand blue */
    --hpm-blue-deep: #2187ba;     /* brand blue, darkened for text/buttons */
    --hpm-ink: #14344a;           /* headings, near-black with blue bias */
    --hpm-ink-soft: #3d5a6e;      /* body text */
    --hpm-muted: #64808f;         /* captions, secondary */
    --hpm-sky: #eef6fb;           /* section ground */
    --hpm-sky-deep: #ddeef8;
    --hpm-card: #ffffff;
    --hpm-line: #d9e8f1;
    --hpm-teal: #2a9d8f;          /* data: underfloor / low temp */
    --hpm-amber: #c97716;         /* data: radiators / higher temp, + "live" */
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

/* ---- shared section scaffolding ---- */
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

/* ---- hero ---- */
.hpm-hero {
    background: linear-gradient(180deg, var(--hpm-sky-deep) 0%, var(--hpm-sky) 55%, var(--hpm-sky) 100%);
    padding: 5rem 0 4.5rem;
}
.hpm-hero-badge {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    gap: 0.5rem;
    padding: 0.4rem 1rem;
    border-radius: 2rem;
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--hpm-ink-soft);
    margin-bottom: 1.75rem;
}
.hpm-hero-badge .hpm-dot {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 50%;
    background: var(--hpm-teal);
}
.hpm-hero h1 {
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.08;
    font-size: clamp(2.2rem, 4.6vw, 3.4rem);
    max-width: 20ch;
    margin-bottom: 1.5rem;
}
.hpm-hero h1 .hpm-accent { color: var(--hpm-blue-deep); }
.hpm-gradient-text {
    color: var(--hpm-blue-deep);
}
@supports (-webkit-background-clip: text) or (background-clip: text) {
    .hpm-gradient-text {
        background-image: linear-gradient(100deg, #185f86 0%, var(--hpm-blue-deep) 35%, var(--hpm-blue) 70%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
}

/* ---- hero map card ---- */
/* The map is a single inline SVG pre-rendered server-side (home_model
   hero_map): no tiles, no map library, no data fetch — it paints with the
   rest of the page. The whole card links to the full interactive map. */
.hpm-hero-map-card {
    position: relative;
    display: block;
    height: clamp(24rem, 62vh, 34rem);
    background: linear-gradient(180deg, #e6f2fa 0%, #d5eaf6 100%);
    border: 1px solid var(--hpm-line);
    border-radius: 1.25rem;
    overflow: hidden;
    text-decoration: none;
    box-shadow: 0 18px 44px rgba(20, 52, 74, 0.12);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.hpm-hero-map-card:hover, .hpm-hero-map-card:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 24px 54px rgba(20, 52, 74, 0.18);
}
.hpm-hero-map-card:focus-visible {
    outline: 3px solid rgba(68, 179, 226, 0.55);
    outline-offset: 2px;
}
.hpm-hero-map {
    display: block;
    width: 100%;
    height: 100%;
    padding: 0.75rem 0.75rem 0.75rem 0.75rem;
}
.hpm-hero-map-land path {
    fill: #ffffff;
    stroke: #b9d6e8;
    stroke-width: 0.8;
}
.hpm-hero-map-dots circle {
    stroke: rgba(255, 255, 255, 0.9);
    stroke-width: 1.2;
    fill-opacity: 0.9;
}
.hpm-hero-map-dots {
    animation: hpm-dots-in 0.9s ease both;
}
@keyframes hpm-dots-in {
    from { opacity: 0; }
    to { opacity: 1; }
}
.hpm-hero-map-chip {
    position: absolute;
    top: 1rem;
    left: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 1rem;
    border-radius: 2rem;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid var(--hpm-line);
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--hpm-ink-soft);
    box-shadow: 0 2px 10px rgba(20, 52, 74, 0.08);
}
.hpm-hero-map-chip .hpm-dot {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 50%;
    background: var(--hpm-amber);
}
.hpm-hero-map-chip b { color: var(--hpm-ink); }
.hpm-hero-map-cta {
    position: absolute;
    left: 1.25rem;
    bottom: 1.25rem;
    display: flex;
    pointer-events: none;
}
.hpm-hero-map-card:hover .hpm-btn-primary { background: #1b729e; }

/* ---- buttons ---- */
.hpm-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.8rem 1.6rem;
    border-radius: 0.75rem;
    font-size: 1.0625rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid transparent;
    transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}
.hpm-btn-primary {
    background: var(--hpm-blue-deep);
    color: #fff;
    box-shadow: 0 6px 18px rgba(33, 135, 186, 0.35);
}
.hpm-btn-primary:hover, .hpm-btn-primary:focus-visible {
    background: #1b729e;
    color: #fff;
    transform: translateY(-1px);
}
.hpm-btn-secondary {
    background: var(--hpm-card);
    color: var(--hpm-ink);
    border-color: var(--hpm-line);
    box-shadow: 0 2px 8px rgba(20, 52, 74, 0.06);
}
.hpm-btn-secondary:hover, .hpm-btn-secondary:focus-visible {
    color: var(--hpm-blue-deep);
    border-color: var(--hpm-blue);
    transform: translateY(-1px);
}
.hpm-btn-light {
    background: rgba(255, 255, 255, 0.95);
    color: var(--hpm-panel-1);
}
.hpm-btn-light:hover, .hpm-btn-light:focus-visible {
    background: #fff;
    color: var(--hpm-panel-1);
    transform: translateY(-1px);
}
.hpm-btn:focus-visible {
    outline: 3px solid rgba(68, 179, 226, 0.55);
    outline-offset: 2px;
}

/* ---- stat tiles ---- */
.hpm-stat-card {
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 1rem;
    padding: 1.4rem 1.5rem 1.2rem;
    height: 100%;
    box-shadow: 0 4px 16px rgba(20, 52, 74, 0.05);
}
.hpm-stat-value {
    font-size: 2.4rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.1;
    color: var(--hpm-ink);
    font-variant-numeric: tabular-nums;
}
.hpm-stat-value small {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--hpm-muted);
}
.hpm-stat-value.hpm-stat-teal { color: var(--hpm-teal); }
.hpm-stat-value.hpm-stat-live { color: var(--hpm-amber); }
.hpm-stat-label {
    margin-top: 0.2rem;
    font-size: 0.9375rem;
    color: var(--hpm-muted);
}

/* ---- "Top of the SPFs" winner card ---- */
.hpm-winner-card {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    text-decoration: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.hpm-winner-card:hover {
    border-color: var(--hpm-amber);
    box-shadow: 0 8px 24px rgba(20, 52, 74, 0.12);
}
.hpm-winner-trophy {
    flex: 0 0 auto;
    width: 3.6rem;
    height: 3.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, #fdf1d7 0%, #f8dfa8 100%);
    color: #b8860b;
    font-size: 1.75rem;
}
.hpm-winner-eyebrow {
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hpm-amber);
}

/* ---- featured story panel ---- */
.hpm-featured {
    background: linear-gradient(120deg, var(--hpm-panel-1) 0%, var(--hpm-panel-2) 100%);
    border-radius: 1.5rem;
    color: rgba(255, 255, 255, 0.88);
    padding: 3rem;
    box-shadow: 0 20px 50px rgba(13, 92, 109, 0.28);
}
.hpm-featured h2 {
    color: #fff;
    font-weight: 800;
    letter-spacing: -0.02em;
    font-size: clamp(1.75rem, 3.2vw, 2.5rem);
    line-height: 1.15;
}
.hpm-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.9rem;
    border-radius: 2rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #fff;
    font-size: 0.78125rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.hpm-chip .hpm-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background: #f5c26b;
}
.hpm-chip-plain {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.78125rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.hpm-featured-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 1rem;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.07);
}
.hpm-featured-stat {
    padding: 1.1rem 1.4rem;
    border-right: 1px solid rgba(255, 255, 255, 0.18);
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
}
.hpm-featured-stat:nth-child(2n) { border-right: none; }
.hpm-featured-stat:nth-last-child(-n+2) { border-bottom: none; }
.hpm-featured-stat .hpm-num {
    font-size: 1.9rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
}
.hpm-featured-stat .hpm-lab {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.72);
}

/* leaderboard card inside featured panel */
.hpm-leaderboard {
    background: var(--hpm-card);
    border-radius: 1rem;
    color: var(--hpm-ink-soft);
    overflow: hidden;
    box-shadow: 0 12px 32px rgba(9, 51, 61, 0.35);
}
.hpm-leaderboard-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.4rem;
    border-bottom: 1px solid var(--hpm-line);
}
.hpm-leaderboard-head h3 {
    font-size: 1.0625rem;
    font-weight: 700;
    margin: 0;
}
.hpm-leaderboard-head span {
    font-size: 0.8125rem;
    color: var(--hpm-muted);
    white-space: nowrap;
}
.hpm-leaderboard ol {
    list-style: none;
    margin: 0;
    padding: 0;
}
.hpm-leaderboard li {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.4rem;
}
.hpm-leaderboard li + li { border-top: 1px solid var(--hpm-line); }
.hpm-rank {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.7rem;
    height: 1.7rem;
    border-radius: 0.5rem;
    background: var(--hpm-sky);
    color: var(--hpm-blue-deep);
    font-size: 0.8125rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.hpm-leaderboard .hpm-place {
    font-weight: 700;
    color: var(--hpm-ink);
    line-height: 1.3;
}
.hpm-leaderboard .hpm-model {
    font-size: 0.875rem;
    color: var(--hpm-muted);
    line-height: 1.3;
}
.hpm-leaderboard .hpm-val {
    margin-left: auto;
    text-align: right;
    line-height: 1.3;
}
.hpm-leaderboard .hpm-val .hpm-kwh {
    font-weight: 800;
    color: var(--hpm-ink);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.hpm-leaderboard .hpm-val .hpm-kwh .hpm-unit {
    font-size: 0.75em;
    font-weight: 600;
    color: var(--hpm-muted);
}
.hpm-leaderboard .hpm-val .hpm-temp {
    font-size: 0.875rem;
    color: var(--hpm-muted);
    font-variant-numeric: tabular-nums;
}

/* ---- chart card + scatter ---- */
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

/* ---- note cards ---- */
.hpm-note-card {
    background: var(--hpm-ink);
    color: rgba(255, 255, 255, 0.85);
    border-radius: 1rem;
    padding: 1.6rem 1.7rem;
}
.hpm-note-card .hpm-note-eyebrow {
    font-size: 0.78125rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #9fd3ec;
    margin-bottom: 0.6rem;
}
.hpm-note-card p { margin: 0; }
.hpm-note-card .hpm-note-footnote {
    margin-top: 0.9rem;
    font-size: 0.8125rem;
    color: rgba(255, 255, 255, 0.55);
}

/* ---- explore cards ---- */
.hpm-explore-card {
    display: block;
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 1rem;
    padding: 1.6rem 1.6rem 1.5rem;
    height: 100%;
    text-decoration: none;
    color: var(--hpm-ink-soft);
    box-shadow: 0 4px 16px rgba(20, 52, 74, 0.05);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.hpm-explore-card:hover, .hpm-explore-card:focus-visible {
    color: var(--hpm-ink-soft);
    border-color: var(--hpm-blue);
    box-shadow: 0 10px 28px rgba(33, 135, 186, 0.16);
    transform: translateY(-3px);
}
.hpm-explore-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.9rem;
    height: 2.9rem;
    border-radius: 0.8rem;
    background: var(--hpm-sky);
    color: var(--hpm-blue-deep);
    font-size: 1.35rem;
    margin-bottom: 1rem;
}
.hpm-explore-card h3 {
    font-size: 1.125rem;
    font-weight: 700;
    margin-bottom: 0.35rem;
}
.hpm-explore-card p {
    font-size: 0.9375rem;
    margin-bottom: 0.75rem;
}
.hpm-explore-card .hpm-go {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--hpm-blue-deep);
}

/* ---- join steps ---- */
.hpm-step-card {
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 1rem;
    padding: 1.6rem;
    height: 100%;
    box-shadow: 0 4px 16px rgba(20, 52, 74, 0.05);
}
.hpm-step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 50%;
    background: var(--hpm-blue-deep);
    color: #fff;
    font-weight: 700;
    margin-bottom: 1rem;
    font-variant-numeric: tabular-nums;
}
.hpm-step-card h3 {
    font-size: 1.125rem;
    font-weight: 700;
    margin-bottom: 0.4rem;
}
.hpm-step-card p {
    font-size: 0.9375rem;
    margin: 0;
}

/* ---- closing band ---- */
.hpm-closing {
    background: var(--hpm-ink);
    color: rgba(255, 255, 255, 0.82);
    padding: 4rem 0;
}
.hpm-closing h2 {
    color: #fff;
    font-weight: 800;
    letter-spacing: -0.02em;
    font-size: clamp(1.75rem, 3vw, 2.4rem);
}
.hpm-closing p { max-width: 62ch; }

/* ---- homes-like-yours finder ---- */
.hpm-finder-card {
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 1rem;
    padding: 1.1rem 1.75rem;
    box-shadow: 0 4px 16px rgba(20, 52, 74, 0.05);
}
.hpm-filter-row {
    display: flex;
    align-items: baseline;
    gap: 1.25rem;
    padding: 0.7rem 0;
}
.hpm-filter-row + .hpm-filter-row { border-top: 1px solid var(--hpm-sky); }
.hpm-filter-label {
    flex: 0 0 7.5rem;
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hpm-muted);
}
.hpm-chip-set {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}
.hpm-chip-toggle {
    border: 1px solid var(--hpm-line);
    background: var(--hpm-card);
    color: var(--hpm-ink-soft);
    border-radius: 2rem;
    padding: 0.28rem 0.9rem;
    font-size: 0.9063rem;
    font-weight: 600;
    line-height: 1.4;
    cursor: pointer;
    transition: background-color 0.12s ease, border-color 0.12s ease, color 0.12s ease;
}
.hpm-chip-toggle:hover { border-color: var(--hpm-blue); color: var(--hpm-blue-deep); }
.hpm-chip-toggle.active {
    background: var(--hpm-blue-deep);
    border-color: var(--hpm-blue-deep);
    color: #fff;
}
.hpm-chip-toggle:focus-visible {
    outline: 3px solid rgba(68, 179, 226, 0.55);
    outline-offset: 2px;
}
.hpm-finder-clear {
    background: none;
    border: none;
    padding: 0;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--hpm-blue-deep);
    cursor: pointer;
}
.hpm-finder-clear:hover { text-decoration: underline; }

.hpm-results-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
    margin: 2.25rem 0 1.25rem;
}
.hpm-results-head h3 {
    font-size: 1.4375rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    margin: 0;
}
.hpm-results-head .hpm-results-count { color: var(--hpm-blue-deep); }
.hpm-seg {
    display: inline-flex;
    gap: 0.25rem;
    margin-left: auto;
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
/* Compact variant for the segmented control inside a leaderboard header */
.hpm-leaderboard-head { flex-wrap: wrap; align-items: center; }
.hpm-leaderboard-head .hpm-seg { padding: 0.2rem; }
.hpm-leaderboard-head .hpm-seg button {
    padding: 0.3rem 0.65rem;
    font-size: 0.85rem;
    white-space: nowrap;
}

/* COP distribution dot strip */
.hpm-strip { width: 100%; height: auto; display: block; }
.hpm-strip .grid { stroke: #e7f0f6; stroke-width: 1; }
.hpm-strip .axis-label {
    font-size: 13px;
    fill: var(--hpm-muted);
    font-family: inherit;
}
.hpm-strip .dot-match { fill: var(--hpm-teal); stroke: #fff; stroke-width: 2; }
.hpm-strip .dot-top { fill: var(--hpm-amber); stroke: #fff; stroke-width: 2; }
.hpm-strip .mean-line { stroke: var(--hpm-ink); stroke-width: 2; opacity: 0.7; }
.hpm-strip .mean-label {
    font-size: 13px;
    font-weight: 600;
    fill: var(--hpm-ink-soft);
    font-family: inherit;
}

.hpm-strip .dot-match, .hpm-strip .dot-top { cursor: pointer; }
.hpm-leader-link {
    flex: 1 1 auto;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.4rem;
    color: inherit;
    text-decoration: none;
}
.hpm-leader-link:hover { background: var(--hpm-sky); color: inherit; }

.hpm-finder-empty {
    background: var(--hpm-card);
    border: 1px dashed var(--hpm-line);
    border-radius: 1rem;
    padding: 3rem 2rem;
    text-align: center;
    color: var(--hpm-muted);
}

/* ---- tariff unit price histogram ---- */
.hpm-hist { width: 100%; height: auto; display: block; }
.hpm-hist .grid { stroke: #e7f0f6; stroke-width: 1; }
.hpm-hist .axis-label { font-size: 13px; fill: var(--hpm-muted); font-family: inherit; }
.hpm-hist .axis-title { font-size: 13px; fill: var(--hpm-ink-soft); font-weight: 600; font-family: inherit; }
.hpm-hist .bar { fill: var(--hpm-teal); }
.hpm-hist .selection-band { fill: rgba(42, 157, 143, 0.16); }
.hpm-hist .iqr { fill: rgba(42, 157, 143, 0.16); }
.hpm-hist .median-line { stroke: var(--hpm-amber); stroke-width: 2; stroke-dasharray: 6 5; }
.hpm-hist .median-chip { fill: var(--hpm-amber); }
.hpm-hist .median-chip-text { font-size: 13px; font-weight: 700; fill: #fff; font-family: inherit; }
.hpm-hist .cap-line { stroke: var(--hpm-ink); stroke-width: 2; stroke-dasharray: 6 5; opacity: 0.7; }
.hpm-hist .cap-label { font-size: 13px; font-weight: 600; fill: var(--hpm-ink-soft); font-family: inherit; }

.hpm-finder-footnote {
    margin-top: 1.25rem;
    font-size: 0.875rem;
    color: var(--hpm-muted);
    max-width: 75ch;
}
.hpm-leaderboard-foot {
    display: block;
    padding: 0.85rem 1.4rem;
    border-top: 1px solid var(--hpm-line);
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--hpm-blue-deep);
    text-decoration: none;
}
.hpm-leaderboard-foot:hover { color: var(--hpm-blue-deep); text-decoration: underline; }
.hpm-leaderboard-note {
    padding: 0.85rem 1.4rem;
    border-top: 1px solid var(--hpm-line);
    font-size: 0.875rem;
    color: var(--hpm-muted);
}
.hpm-leaderboard-note .hpm-note-emphasis {
    display: block;
    margin-top: 0.35rem;
    color: var(--hpm-ink-soft);
}

/* ---- find an installer: embedded map preview ---- */
.hpm-map-embed {
    position: relative;
    border-radius: 1.1rem;
    overflow: hidden;
    border: 1px solid var(--hpm-line);
    background: var(--hpm-sky-deep);
    box-shadow: 0 14px 34px rgba(20, 52, 74, 0.1);
}
.hpm-map-canvas { height: 30rem; }
/* the preview is deliberately static — all interaction happens on the full map page */
.hpm-map-canvas .ol-viewport { pointer-events: none; }
.hpm-map-cta {
    position: absolute;
    inset: 0;
    z-index: 5;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 2rem;
    text-decoration: none;
    background: linear-gradient(180deg, rgba(20,52,74,0) 60%, rgba(20,52,74,0.22) 100%);
    transition: background 0.15s ease;
}
.hpm-map-cta:hover { background: linear-gradient(180deg, rgba(20,52,74,0.05) 60%, rgba(20,52,74,0.32) 100%); }
.hpm-map-cta .hpm-btn { pointer-events: none; }
.hpm-installer-strip-label {
    margin: 2rem 0 0.75rem;
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--hpm-muted);
}
.hpm-installer-strip { display: flex; flex-wrap: wrap; gap: 0.6rem; }
.hpm-installer-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.45rem 1rem;
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 2rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--hpm-ink-soft);
    text-decoration: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.hpm-installer-chip:hover {
    border-color: var(--hpm-blue);
    box-shadow: 0 2px 10px rgba(33, 135, 186, 0.18);
    color: var(--hpm-ink);
}
.hpm-installer-chip .dot { width: 0.65rem; height: 0.65rem; border-radius: 50%; flex: none; }
.hpm-installer-chip .count { color: var(--hpm-muted); font-weight: 500; }

/* ---- forum topics: Discourse-style snapshot card ---- */
.hpm-forum-card {
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(20, 52, 74, 0.05);
}
.hpm-forum-head {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem 1rem;
    padding: 1rem 1.4rem;
    border-bottom: 1px solid var(--hpm-line);
}
.hpm-forum-head h3 { font-size: 1.0625rem; font-weight: 700; margin: 0; }
.hpm-forum-src {
    font-size: 0.8125rem;
    color: var(--hpm-muted);
    white-space: nowrap;
}
.hpm-forum-cols,
.hpm-forum-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 7.6rem 3.4rem 3.4rem 4.2rem;
    gap: 1rem;
    align-items: center;
    padding: 0.85rem 1.4rem;
}
.hpm-forum-cols {
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
    border-bottom: 1px solid var(--hpm-line);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hpm-muted);
}
.hpm-forum-cols span:not(:first-child) { text-align: center; }
.hpm-forum-list { list-style: none; margin: 0; padding: 0; }
.hpm-forum-list li + li { border-top: 1px solid var(--hpm-sky); }
.hpm-forum-row {
    text-decoration: none;
    color: inherit;
    transition: background-color 0.12s ease;
}
.hpm-forum-row:hover { background: var(--hpm-sky); color: inherit; }
.hpm-forum-title {
    display: block;
    font-weight: 600;
    font-size: 1.0313rem;
    color: var(--hpm-ink);
    line-height: 1.35;
}
.hpm-forum-row:hover .hpm-forum-title { color: var(--hpm-blue-deep); }
.hpm-forum-title .bi-check-square-fill { color: var(--hpm-teal); font-size: 0.9em; }
.hpm-forum-tags { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.3rem; }
.hpm-forum-tag {
    display: inline-block;
    padding: 0.05rem 0.5rem;
    border-radius: 0.35rem;
    background: var(--hpm-sky);
    color: var(--hpm-muted);
    font-size: 0.78125rem;
    font-weight: 600;
}
.hpm-forum-posters { display: flex; gap: 0.3rem; }
.hpm-forum-posters img {
    width: 1.7rem;
    height: 1.7rem;
    border-radius: 50%;
    flex: none;
}
.hpm-forum-num {
    text-align: center;
    font-size: 0.9375rem;
    color: var(--hpm-muted);
    font-variant-numeric: tabular-nums;
}
.hpm-forum-num.hpm-forum-replies { color: var(--hpm-ink-soft); font-weight: 600; }

@media (max-width: 767.98px) {
    .hpm-section { padding: 3rem 0; }
    .hpm-hero { padding: 3.5rem 0 3rem; }
    .hpm-hero-map-card { height: 24rem; }
    .hpm-featured { padding: 1.75rem; border-radius: 1.1rem; }
    .hpm-filter-row { flex-direction: column; gap: 0.5rem; }
    .hpm-filter-label { flex: none; }
    .hpm-seg { margin-left: 0; }
    .hpm-map-canvas { height: 21rem; }
    .hpm-map-cta { padding: 1.25rem; }
    .hpm-forum-cols { display: none; }
    .hpm-forum-row { grid-template-columns: minmax(0, 1fr) auto auto; gap: 0.75rem; }
    .hpm-forum-posters, .hpm-forum-views { display: none; }
}

@media (prefers-reduced-motion: reduce) {
    .hpm-home * { transition: none !important; animation: none !important; }
}

/* Hide the app until Vue has compiled the template, so raw {{ }} bindings
   never flash on screen. Vue removes v-cloak on mount. */
[v-cloak] { display: none !important; }
</style>

<script src="<?php echo $path; ?>theme/vendor/vue-2.7.16/vue.min.js" integrity="sha384-YVYXhPGIH/Gmcr0W5Rin4PcpcsG1a4pcdUUod1CnbDEJut7XiUaJtSlNKeRLJBPk"></script>
<div class="hpm-home" id="app" v-cloak>

    <!-- ============ Hero ============ -->
    <section class="hpm-hero">
        <div class="container">
            <div class="row align-items-center g-4 g-lg-5 mb-4 mb-lg-5">
                <div class="col-lg-7 col-xl-6">
                    <span class="hpm-hero-badge"><span class="hpm-dot"></span> Open-source heat pump performance data</span>

                    <!--<h1>What makes a heat&nbsp;pump <span class="hpm-gradient-text">efficient</span> and <span class="hpm-gradient-text">cheap&nbsp;to&nbsp;run?</span></h1>-->

                    <h1>Explore our <span class="hpm-gradient-text">database</span> of installed heat&nbsp;pumps</h1>



                    <p class="hpm-lead mb-4">
                        HeatpumpMonitor.org gathers <strong>real world data from {{ mid_metered_count }} heat pumps</strong>
                        across the UK and beyond. Systems are monitored using high accuracy <b>MID-certified electric and heat meters</b> - ensuring that the data is reliable and can be compared to previous UK Gov funded trials.
                    </p>

                    <!--
                    <p class="hpm-lead mb-4">
                        Providing information about the <strong>host property</strong>, heat pump <strong>performance</strong>,
                        <strong>running cost</strong>, <strong>installer</strong> &amp; more.
                    </p>-->

                    <div class="d-flex flex-wrap gap-3">
                        <a class="hpm-btn hpm-btn-primary" href="<?php echo $path; ?>system/list">Explore the data <i class="bi bi-arrow-right"></i></a>
                        <a class="hpm-btn hpm-btn-secondary" href="#join-in"><i class="bi bi-plus-lg"></i> Add your system</a>
                    </div>
                </div>
                <?php if (!empty($hero_map['svg'])) { ?>
                <div class="col-lg-5 col-xl-6">
                    <!-- Server-rendered SVG map: instant, no tiles or map library.
                         The whole card links through to the full interactive map. -->
                    <a class="hpm-hero-map-card" href="<?php echo $path; ?>map" aria-label="Open the full interactive map of monitored systems">
                        <?php echo $hero_map['svg']; ?>
                        <span class="hpm-hero-map-chip"><span class="hpm-dot"></span> <b><?php echo (int) $hero_map['count']; ?></b>&nbsp;systems</span>
                        <!--<span class="hpm-hero-map-cta"><span class="hpm-btn hpm-btn-primary">Open the map <i class="bi bi-arrows-fullscreen"></i></span></span>-->
                    </a>
                </div>
                <?php } ?>
            </div>

            <!-- Headline stats derived live from the eligible_systems dataset -->
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="hpm-stat-card">
                        <div class="hpm-stat-value">{{ systemCount || "…" }}</div>
                        <div class="hpm-stat-label">MID H4 boundary monitored systems with at least one year of data <b>out of {{ mid_metered_count }} MID monitored systems total.</b></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="hpm-stat-card">
                        <div class="hpm-stat-value hpm-stat-teal">{{ meanSpfH4 || "…" }}</div>
                        <div class="hpm-stat-label">Mean SPF H4 - units of heat delivered per unit of electricity consumed.</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="hpm-stat-card">
                        <div class="hpm-stat-value">{{ meanSpfH4 && medianUnitRateAgile ? "£" + (10000 / meanSpfH4 * medianUnitRateAgile * 0.01).toFixed(0) : "…" }} <small>/year</small></div>
                        <div class="hpm-stat-label">Running costs for a typical 3-bed UK home. Heat demand of 10,000 kWh/year, Octopus Agile median unit rate {{ medianUnitRateAgile || "…" }} p/kWh.</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="hpm-stat-card">
                        <div class="hpm-stat-value hpm-stat-live">live</div>
                        <div class="hpm-stat-label">Detailed timeseries data updated every 10s. Totals updated daily.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Featured data story ============ -->
    <!-- Live cooling activity over the last 30 days, from the server-embedded cooling_systems dataset -->
    <section class="hpm-section hpm-section-sky pt-0">
        <div class="container">
            <div class="hpm-featured">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                    <span class="hpm-chip"><span class="hpm-dot"></span> &middot; UK heatwave</span>
                    <span class="hpm-chip-plain">Featured</span>
                </div>
                <div class="row g-4 g-lg-5 align-items-start" v-if="cooling.length">
                    <div class="col-lg-7">
                        <h2 class="mb-3"><i class="bi bi-sun" style="color:#f5c26b;"></i> Who&rsquo;s keeping cool?</h2>
                        <p class="mb-4">
                            While we usually focus on winter heating, heat pumps can also be configured (with care) to provide cooling in the summer. 
                            There are <strong>{{ cooling.length }} systems</strong> on HeatpumpMonitor that have provided active cooling over the last month.
                        </p>
                        <div class="hpm-featured-stats">
                            <div class="hpm-featured-stat">
                                <div class="hpm-num">{{ cooling.length }}</div>
                                <div class="hpm-lab">systems actively cooling</div>
                            </div>
                            <div class="hpm-featured-stat">
                                <div class="hpm-num">{{ fmt(coolingTotalKwh) }} kWh</div>
                                <div class="hpm-lab">cooling delivered, last month</div>
                            </div>
                            <div class="hpm-featured-stat">
                                <div class="hpm-num">{{ coolingAvgCop.toFixed(1) }}</div>
                                <div class="hpm-lab">average cooling COP</div>
                            </div>
                            <div class="hpm-featured-stat">
                                <div class="hpm-num">{{ fmt(coolingMaxKwh) }} kWh</div>
                                <div class="hpm-lab">top cooling, last month</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="hpm-leaderboard">
                            <div class="hpm-leaderboard-head">
                                <h3>Most cooling delivered per m&sup2;</h3>
                                <span>last 30 days &middot; COP</span>
                            </div>
                            <ol>
                                <li v-for="(h, i) in coolingTop" style="padding:0;">
                                    <a class="hpm-leader-link" :href="path + 'system/view?id=' + h.id">
                                        <span class="hpm-rank">{{ i + 1 }}</span>
                                        <span><span class="hpm-place">{{ h.location }}</span><br><span class="hpm-model">{{ homeSubtitle(h) }}</span></span>
                                        <span class="hpm-val"><span class="hpm-kwh">{{ (h.heat / h.floor).toFixed(1) }}<span class="hpm-unit"> kWh/m&sup2;</span></span><br><span class="hpm-temp">{{ h.cop ? "COP " + h.cop.toFixed(1) : "—" }}</span></span>
                                    </a>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
                <p class="mb-0" v-else-if="coolingError">Couldn&rsquo;t load live cooling data right now &mdash; please try again shortly.</p>
                <p class="mb-0" v-else>Loading live cooling data&hellip;</p>
            </div>
        </div>
    </section>


    <!-- ============ 01 · Homes like yours ============ -->
    <!-- Uses the server-embedded eligible_systems dataset,
         filtered client-side for instant response to the chips. -->
    <section class="hpm-section">
        <div class="container">
            <div class="hpm-eyebrow"><span class="hpm-eyebrow-num">01</span> Running costs</div>
            <h2 class="hpm-display mb-3">What do heat pumps <span class="hpm-accent">cost to run</span> in different homes?</h2>
            <p class="hpm-lead mb-5">
                The running cost of a heat pump is the product of three things: the <b>heat demand</b> of the home, the <b>efficiency</b> of the heat pump installation (SPF), and the <b>price of electricity</b>. The following explores real-world examples of heat pump homes on HeatpumpMonitor to see how these factors combine.
            </p>

            <!-- Filters -->
            <div class="hpm-finder-card">
                <div class="hpm-filter-row">
                    <span class="hpm-filter-label">Property</span>
                    <div class="hpm-chip-set">
                        <button type="button" v-for="o in finderOptions.property"
                                :class="['hpm-chip-toggle', {active: finder.property===o}]"
                                @click="finder.property=o">{{ o }}</button>
                    </div>
                </div>
                <div class="hpm-filter-row">
                    <span class="hpm-filter-label">Floor area</span>
                    <div class="hpm-chip-set">
                        <button type="button" v-for="o in finderOptions.floor"
                                :class="['hpm-chip-toggle', {active: finder.floor===o}]"
                                @click="finder.floor=o">{{ o }}</button>
                    </div>
                </div>
                <div class="hpm-filter-row">
                    <span class="hpm-filter-label">Age of build</span>
                    <div class="hpm-chip-set">
                        <button type="button" v-for="o in finderOptions.age"
                                :class="['hpm-chip-toggle', {active: finder.age===o}]"
                                @click="finder.age=o">{{ o }}</button>
                    </div>
                </div>
                <div class="hpm-filter-row">
                    <span class="hpm-filter-label">Insulation</span>
                    <div class="hpm-chip-set">
                        <button type="button" v-for="o in finderOptions.insulation"
                                :class="['hpm-chip-toggle', {active: finder.insulation===o}]"
                                @click="finder.insulation=o">{{ o }}</button>
                    </div>
                </div>
                <div class="hpm-filter-row">
                    <span class="hpm-filter-label">Heat pump</span>
                    <div class="hpm-chip-set">
                        <button type="button" v-for="o in finderOptions.hp"
                                :class="['hpm-chip-toggle', {active: finder.hp===o}]"
                                @click="finder.hp=o">{{ o }}</button>
                    </div>
                </div>
            </div>

            <!-- Results -->
            <div v-if="matches.length" style="min-height: 1px;">
                <div class="hpm-results-head">
                    <h3><span class="hpm-results-count">{{ matches.length }}</span> matching home{{ matches.length===1 ? '' : 's' }}</h3>
                    <button v-if="finderActive" class="hpm-finder-clear" @click="clearFinder"><i class="bi bi-x-circle"></i> Clear filters</button>
                    <div class="d-flex flex-wrap gap-2" style="margin-left:auto;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="hpm-filter-label" style="flex:none;">Tariff</span>
                            <div class="hpm-seg" role="group" aria-label="Choose tariff for cost estimates" style="margin-left:0;">
                                <button v-for="td in costTariffDefs" type="button" :class="{active: costTariff===td.key}" @click="costTariff=td.key">{{ td.short }}</button>
                            </div>
                        </div>
                        <div class="hpm-seg" role="group" aria-label="Show cheapest or highest cost homes" style="margin-left:0;">
                            <button type="button" :class="{active: showCheapest}" @click="showCheapest=true">Cheapest to run</button>
                            <button type="button" :class="{active: !showCheapest}" @click="showCheapest=false">Highest cost</button>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="hpm-stat-card">
                            <div class="hpm-stat-value hpm-stat-teal">&pound;{{ fmt(medianCost) }} <small>/year</small></div>
                            <div class="hpm-stat-label" v-if="costTariff==='cap'">median running cost at the &approx;{{ PRICE_CAP.toFixed(0) }}p price cap flat rate</div>
                            <div class="hpm-stat-label" v-else>median running cost on {{ costTariffDef.name }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="hpm-stat-card">
                            <div class="hpm-stat-value">{{ fmt(medianElec) }} <small>kWh</small></div>
                            <div class="hpm-stat-label">median electricity used per year</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="hpm-stat-card">
                            <div class="hpm-stat-value">{{ fmt(medianHeat) }} <small>kWh</small></div>
                            <div class="hpm-stat-label">median heat delivered per year</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="hpm-stat-card">
                            <div class="hpm-stat-value">{{ meanCopVal.toFixed(2) }}</div>
                            <div class="hpm-stat-label">mean SPF, range {{ worstCop.toFixed(1) }}&ndash;{{ bestCop.toFixed(1) }}</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="hpm-chart-card h-100 d-flex flex-column">
                            <h3>What homes pay in a year</h3>
                            <div class="hpm-chart-sub" v-if="costTariff==='cap'">Measured electricity use at the &approx;{{ PRICE_CAP.toFixed(0) }}p flat rate &middot; each dot is one matching home &middot; hover for detail</div>
                            <div class="hpm-chart-sub" v-else>Measured electricity use at each home&rsquo;s own achieved {{ costTariffDef.name }} rate &middot; each dot is one matching home &middot; hover and click for detail</div>
                            <div class="flex-grow-1 d-flex align-items-center" style="min-width:0;">
                            <svg class="hpm-strip" viewBox="0 0 680 235" role="img"
                                 aria-label="Dot strip of estimated annual running cost for matching homes, with the median marked. Values are listed in the table alongside.">
                                <g>
                                    <line v-for="t in costTicks" class="grid" :x1="costX(t)" y1="28" :x2="costX(t)" y2="192"></line>
                                    <line class="grid" x1="60" y1="192" x2="650" y2="192"></line>
                                    <text v-for="t in costTicks" class="axis-label" :x="costX(t)" y="214" text-anchor="middle">&pound;{{ fmt(t) }}</text>
                                </g>
                                <line class="mean-line" :x1="medianX" y1="24" :x2="medianX" y2="192"></line>
                                <text class="mean-label" :x="medianX > 500 ? medianX - 8 : medianX + 8" y="20"
                                      :text-anchor="medianX > 500 ? 'end' : 'start'">median &approx;&pound;{{ fmt(medianCost) }}/yr</text>
                                <circle v-for="d in stripDots" :class="d.top ? 'dot-top' : 'dot-match'"
                                        :cx="d.x" :cy="d.y" :r="d.r" @click="openSystem(d.id)">
                                    <title>{{ d.label }}</title>
                                </circle>
                            </svg>
                            </div>
                            <div class="hpm-legend">
                                <span><span class="hpm-swatch" style="background:#2a9d8f;"></span> Matching home</span>
                                <span><span class="hpm-swatch" style="background:#c97716;"></span> {{ showCheapest ? 'Cheapest' : 'Highest cost' }} 4 &mdash; listed alongside</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="hpm-leaderboard">
                            <div class="hpm-leaderboard-head">
                                <h3>{{ showCheapest ? 'Cheapest to run' : 'Highest running cost' }}</h3>
                                <span>est. &pound;/yr</span>
                            </div>
                            <ol>
                                <li v-for="(h, i) in fabricTopFour" style="padding:0;">
                                    <a class="hpm-leader-link" :href="path + 'system/view?id=' + h.id">
                                        <span class="hpm-rank">{{ i + 1 }}</span>
                                        <span><span class="hpm-place">{{ h.location }}</span><br><span class="hpm-model">{{ homeSubtitle(h) }}</span></span>
                                        <span class="hpm-val"><span class="hpm-kwh">&pound;{{ fmt(costOf(h)) }}</span><br><span class="hpm-temp">{{ fmt(h.elec) }} kWh elec &times; {{ costRate(h).toFixed(1) }}p</span><br><span class="hpm-temp">{{ fmt(h.heat) }} kWh heat &middot; SPF {{ h.cop.toFixed(1) }}</span></span>
                                    </a>
                                </li>
                            </ol>
                            <a class="hpm-leaderboard-foot" href="<?php echo $path; ?>system/list?mode=costs&tariff=agile">
                                Explore all {{ matches.length }} on the full system list page <i class="bi bi-arrow-right"></i>
                            </a>
                            
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="finderLoading" class="hpm-finder-empty">
                <p class="mb-0">Loading live system data&hellip;</p>
            </div>

            <div v-else-if="finderError" class="hpm-finder-empty">
                <p class="mb-0">Couldn&rsquo;t load live system data right now &mdash; please try again shortly.</p>
            </div>

            <div v-else class="hpm-finder-empty">
                <p class="mb-2"><strong>No monitored homes match that combination yet.</strong></p>
                <button class="hpm-finder-clear" @click="clearFinder"><i class="bi bi-x-circle"></i> Clear all filters</button>
            </div>

         
        </div>
    </section>

    <!-- ============ 02 · Smart tariffs ============ -->
    <!-- Effective unit price distributions on time-of-use tariffs, computed by
         replaying each system's half-hourly consumption against historic
         tariff prices. Background: community.openenergymonitor.org/t/30110 -->
    <section class="hpm-section hpm-section-sky">
        <div class="container">
            <div class="hpm-eyebrow"><span class="hpm-eyebrow-num">02</span> Time of use tariffs</div>
            <h2 class="hpm-display mb-3">How much can you save with <span class="hpm-accent">time-of-use tariffs?</span></h2>
            <p class="hpm-lead mb-5">
                The price of electricity is an important factor in the running cost of a heat pump. Time-of-use tariffs offer lower prices at certain times of day. The following explores how much HeatpumpMonitor systems would have paid on different tariffs over the last 12 months, and how they compare to the &approx;{{ PRICE_CAP.toFixed(0) }}p price cap. 
            </p>

            <div v-if="!finderLoading && !finderError">
                <div class="hpm-results-head">
                    <h3>What HeatpumpMonitor systems would pay on {{ tariffDef.name }}</h3>
                    <div class="hpm-seg" role="group" aria-label="Choose tariff">
                        <button v-for="td in tariffDefs" type="button" :class="{active: tariff===td.key}" @click="tariff=td.key">{{ td.short }}</button>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="hpm-stat-card">
                            <div class="hpm-stat-value hpm-stat-teal">{{ tariffStats.median.toFixed(1) }}<small>p</small></div>
                            <div class="hpm-stat-label">median electricity unit price - likely no or minimal demand shifting</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="hpm-stat-card">
                            <div class="hpm-stat-value hpm-stat-teal" v-if="tariffVsCap >= 1">&minus;{{ tariffVsCap.toFixed(0) }}%</div>
                            <div class="hpm-stat-value" v-else>&approx; cap</div>
                            <div class="hpm-stat-label">the median system vs the &approx;{{ PRICE_CAP.toFixed(0) }}p price cap</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="hpm-stat-card">
                            <div class="hpm-stat-value">{{ tariffStats.min.toFixed(1) }}<small>p</small></div>
                            <div class="hpm-stat-label">the cheapest system - heating at cheaper times</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="hpm-chart-card">
                            <h3>{{ tariffDef.name }}: effective unit price</h3>
                            <div class="hpm-chart-sub">{{ tariffDef.sub }} &middot; count of systems per 0.5p band &middot; hover for detail</div>
                            <svg class="hpm-hist" viewBox="0 0 680 350" role="img"
                                 :aria-label="'Histogram of effective unit prices on ' + tariffDef.name + ' across ' + tariffStats.n + ' systems. The median is ' + tariffStats.median.toFixed(1) + ' pence, against a price cap of about ' + PRICE_CAP.toFixed(0) + ' pence.'">
                                <rect class="iqr" :x="histX(tariffStats.q1)" y="18" :width="histX(tariffStats.q3) - histX(tariffStats.q1)" height="282"></rect>
                                <g>
                                    <line v-for="t in histYTicks" class="grid" x1="50" :y1="histY(t)" x2="660" :y2="histY(t)"></line>
                                    <text v-for="t in histYTicks" class="axis-label" x="42" :y="histY(t) + 4" text-anchor="end">{{ t }}</text>
                                    <text class="axis-title" x="16" y="160" transform="rotate(-90 16 160)" text-anchor="middle">Systems</text>
                                </g>
                                <g>
                                    <text v-for="t in histXTicks" class="axis-label" :x="histX(t)" y="322" text-anchor="middle">{{ t }}{{ t === histDomain.hi ? '+' : '' }}</text>
                                    <text class="axis-title" x="355" y="348" text-anchor="middle">Effective unit price (p/kWh)</text>
                                </g>
                                <g>
                                    <rect v-for="b in histBins" class="bar" :x="b.x + 1" :y="b.y" :width="b.w - 2" :height="b.h" rx="2">
                                        <title>{{ b.label }}</title>
                                    </rect>
                                </g>
                                <line class="cap-line" :x1="histX(PRICE_CAP)" y1="18" :x2="histX(PRICE_CAP)" y2="300"></line>
                                <text class="cap-label" :x="histX(PRICE_CAP) + 7" y="32" text-anchor="start">price cap &approx;{{ PRICE_CAP.toFixed(0) }}p</text>
                                <line class="median-line" :x1="histX(tariffStats.median)" y1="18" :x2="histX(tariffStats.median)" y2="300"></line>
                                <g>
                                    <rect class="median-chip" :x="histX(tariffStats.median) - 52" y="256" width="104" height="24" rx="5"></rect>
                                    <text class="median-chip-text" :x="histX(tariffStats.median)" y="272" text-anchor="middle">Median {{ tariffStats.median.toFixed(1) }}p</text>
                                </g>
                            </svg>
                            <div class="hpm-legend">
                                <span><span class="hpm-swatch" style="background:#2a9d8f;"></span> Systems per 0.5p band</span>
                                <span><span class="hpm-swatch" style="background:rgba(42,157,143,0.16); width:1rem; border-radius:3px;"></span> Interquartile range</span>
                                <span><span class="hpm-swatch" style="background:#c97716; height:0.2rem; width:1rem; border-radius:2px;"></span> Median</span>
                                <span><span class="hpm-swatch" style="background:#14344a; height:0.2rem; width:1rem; border-radius:2px;"></span> Price cap</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5 d-flex flex-column gap-4">
                        <div class="hpm-leaderboard">
                            <div class="hpm-leaderboard-head" style="flex-wrap:wrap;">
                                <h3>{{ tariffRankDef.title }}</h3>
                                <div class="hpm-seg" role="group" aria-label="Rank systems by unit cost">
                                    <button type="button" :class="{active: tariffRank==='lowest'}" @click="tariffRank='lowest'">Lowest</button>
                                    <button type="button" :class="{active: tariffRank==='median'}" @click="tariffRank='median'">On median</button>
                                    <button type="button" :class="{active: tariffRank==='highest'}" @click="tariffRank='highest'">Highest</button>
                                </div>
                            </div>
                            <ol>
                                <li v-for="(h, i) in tariffTopFive" style="padding:0;">
                                    <a class="hpm-leader-link" :href="path + 'system/view?id=' + h.id">
                                        <span class="hpm-rank">{{ i + 1 }}</span>
                                        <span><span class="hpm-place">{{ h.location }}</span><br><span class="hpm-model">{{ homeSubtitle(h) }}</span></span>
                                        <span class="hpm-val"><span class="hpm-kwh">{{ rateOf(h).toFixed(1) }}<small>p</small></span><br><span class="hpm-temp">&approx;&pound;{{ fmt(rateOf(h) * h.elec / 100) }}/yr &middot; SPF {{ h.cop.toFixed(1) }}</span></span>
                                    </a>
                                </li>
                            </ol>
                            <a class="hpm-leaderboard-foot" :href="path + 'system/list?mode=costs&tariff=' + tariff">
                                Explore all {{ tariffStats.n }} in cost mode <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <!--
                        <div class="hpm-note-card">
                            <div class="hpm-note-eyebrow">Go further &middot; solar &amp; battery storage</div>
                            <p class="mb-2">
                                Further savings are possible by combining a heat pump with battery storage and/or solar PV.
                                In our energy co-benefits model, built on real half-hourly household data, a heat pump with
                                an SPF of 4 saves &pound;134/year over gas on a flat-rate tariff and &pound;475/year on Agile.
                                Adding 4 kWp of solar PV and a 10 kWh home battery arbitraging Agile prices lifts the combined
                                saving to &approx;&pound;630/year &mdash; all figures include annualised equipment costs.
                            </p>
                            <p class="mb-0"><a href="https://community.openenergymonitor.org/t/what-are-the-co-benefits-of-solar-battery-hp-ev-tariff/30095" target="_blank" rel="noopener" style="color:#9fd3ec;">Explore the co-benefits analysis on our forum <i class="bi bi-arrow-right"></i></a></p>
                        </div>
-->
                    </div>
                </div>
            </div>

            <div v-else class="hpm-finder-empty">
                <p class="mb-0">{{ finderError ? "Couldn’t load live system data right now — please try again shortly." : "Loading live system data…" }}</p>
            </div>

            <p class="hpm-finder-footnote"> 
                The Agile tariff is currently showing savings with likely no or minimal need for demand shifting, but some owners are achieving even lower rates by running their heat pumps at cheaper times of day. Many owners prefer to run their heat pumps for comfort rather than cost which is a perfectly valid way of running a heat pump. Battery storage can also be used to shift demand rather than running the heat pump at a different times of day.
            </p>

            <!--
            <p class="hpm-finder-footnote">
                Unit prices are consumption-weighted averages: each system&rsquo;s half-hourly electricity
                use over the last 365 days, priced at the tariff&rsquo;s historic rate for each half hour
                &mdash; nobody changed how they heat. The median is what a typical &ldquo;heat when
                needed&rdquo; system would simply pay; the cheap tail is owners already timing hot water
                and heating &mdash; some with batteries &mdash; into the low-price periods. Standing
                charges are excluded. The price cap comparison uses the flat electricity unit rate of
                roughly {{ PRICE_CAP.toFixed(0) }} p/kWh, and a few systems above {{ histDomain.hi }}p
                are folded into the top band.
                <a href="https://community.openenergymonitor.org/t/heatpumpmonitor-org-time-of-use-tariff-unit-rate-distributions/30110" target="_blank" rel="noopener">The full analysis on our forum <i class="bi bi-arrow-right"></i></a>
            </p>
            -->
        </div>
    </section>

    <!-- ============ 03 · SPF distribution ============ -->
    <!-- Histogram of measured SPF across the same home/eligible_systems
         dataset used by the sections above and below. -->
    <section class="hpm-section">
        <div class="container">
            <div class="hpm-eyebrow"><span class="hpm-eyebrow-num">03</span> Performance distribution</div>
            <h2 class="hpm-display mb-3">How <span class="hpm-accent">efficient</span> are HeatpumpMonitor systems?</h2>
            <p class="hpm-lead mb-5">
                The seasonal performance factor (SPF) is the headline efficiency figure for a heat pump: the units of heat delivered per unit of electricity consumed over a full year, space heating and hot water combined. The following chart shows the performance distribution for systems on HeatpumpMonitor.
            </p>

            <div v-if="!finderLoading && !finderError">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="hpm-stat-card">
                            <div class="hpm-stat-value hpm-stat-teal">{{ spfStats.mean.toFixed(2) }}</div>
                            <div class="hpm-stat-label">mean SPF, range {{ spfStats.min.toFixed(1) }}&ndash;{{ spfStats.max.toFixed(1) }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <a v-if="spfRankedHomes.length" class="hpm-stat-card hpm-winner-card" :href="path + 'system/view?id=' + spfRankedHomes[0].id">
                            <div class="hpm-winner-trophy"><i class="bi bi-trophy-fill"></i></div>
                            <div>
                                <div class="hpm-winner-eyebrow">Top of the SCOPs</div>
                                <div class="hpm-stat-value hpm-stat-teal">{{ spfRankedHomes[0].cop.toFixed(2) }}</div>
                                <div class="hpm-stat-label">{{ spfRankedHomes[0].location }}<template v-if="spfRankedHomes[0].hp"> &middot; <span :style="{color: hpTypeColor(spfRankedHomes[0])}">{{ hpTypeLabel(spfRankedHomes[0]) }}</span></template> &middot; {{ homeSubtitle(spfRankedHomes[0]) }}</div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row g-4">
                <div class="col-lg-7">
                    <div class="hpm-chart-card">
                        <h3>Measured SPF across {{ spfStats.n }} systems</h3>
                        <div class="hpm-chart-sub">Space heating &amp; hot water combined, last 365 days &middot; count of systems per 0.1 SPF band &middot; hover for detail</div>
                        <svg class="hpm-hist" viewBox="0 0 680 350" role="img"
                             :aria-label="'Histogram of measured SPF across ' + spfStats.n + ' systems in 0.1 SPF bands. The median is ' + spfStats.median.toFixed(2) + ', and half of all systems fall between ' + spfStats.q1.toFixed(1) + ' and ' + spfStats.q3.toFixed(1) + '.'">
                            <rect v-if="spfSelectionBand" class="selection-band" :x="spfX(spfSelectionBand.lo)" y="18" :width="Math.max(6, spfX(spfSelectionBand.hi) - spfX(spfSelectionBand.lo))" height="282" rx="4"></rect>
                            <g>
                                <line v-for="t in spfYTicks" class="grid" x1="50" :y1="spfY(t)" x2="660" :y2="spfY(t)"></line>
                                <text v-for="t in spfYTicks" class="axis-label" x="42" :y="spfY(t) + 4" text-anchor="end">{{ t }}</text>
                                <text class="axis-title" x="16" y="160" transform="rotate(-90 16 160)" text-anchor="middle">Systems</text>
                            </g>
                            <g>
                                <text v-for="t in spfXTicks" class="axis-label" :x="spfX(t)" y="322" text-anchor="middle">{{ t.toFixed(1) }}</text>
                                <text class="axis-title" x="355" y="348" text-anchor="middle">Measured SPF: heat delivered per unit of electricity</text>
                            </g>
                            <g>
                                <rect v-for="b in spfBins" class="bar" :x="b.x + 1" :y="b.y" :width="b.w - 2" :height="b.h" rx="2">
                                    <title>{{ b.label }}</title>
                                </rect>
                            </g>
                            <line class="median-line" :x1="spfX(spfStats.median)" y1="18" :x2="spfX(spfStats.median)" y2="300"></line>
                            <g>
                                <rect class="median-chip" :x="spfX(spfStats.median) - 52" y="256" width="104" height="24" rx="5"></rect>
                                <text class="median-chip-text" :x="spfX(spfStats.median)" y="272" text-anchor="middle">Median {{ spfStats.median.toFixed(2) }}</text>
                            </g>
                        </svg>
                        <div class="hpm-legend">
                            <span><span class="hpm-swatch" style="background:#2a9d8f;"></span> Systems per 0.1 SPF band</span>
                            <span><span class="hpm-swatch" style="background:rgba(42,157,143,0.16); width:1rem; border-radius:3px;"></span> {{ spfSelectionBand ? spfSelectionBand.label : 'Selected ranking band' }}</span>
                            <span><span class="hpm-swatch" style="background:#c97716; height:0.2rem; width:1rem; border-radius:2px;"></span> Median</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="hpm-leaderboard">
                        <div class="hpm-leaderboard-head">
                            <h3>SPF ranking</h3>
                            <div class="hpm-seg">
                                <button :class="{active: spfLeaderMode==='top'}" @click="spfLeaderMode='top'">Top 5</button>
                                <button :class="{active: spfLeaderMode==='decile'}" @click="spfLeaderMode='decile'">Top 25%</button>
                                <button :class="{active: spfLeaderMode==='median'}" @click="spfLeaderMode='median'">Middle 50%</button>
                                <button :class="{active: spfLeaderMode==='bottom'}" @click="spfLeaderMode='bottom'">Bottom 25%</button>
                            </div>
                        </div>
                        <ol>
                            <li v-for="r in spfLeaders" style="padding:0;">
                                <a class="hpm-leader-link" :href="path + 'system/view?id=' + r.h.id">
                                    <span class="hpm-rank">{{ r.rank }}</span>
                                    <span><span class="hpm-place">{{ r.h.location }}<template v-if="r.h.hp"> &middot; <span :style="{color: hpTypeColor(r.h)}">{{ hpTypeLabel(r.h) }}</span></template></span><br><span class="hpm-model">{{ homeSubtitle(r.h) }}</span></span>
                                    <span class="hpm-val"><span class="hpm-kwh">{{ r.h.cop.toFixed(2) }}</span><br><span class="hpm-temp">SPF</span></span>
                                </a>
                            </li>
                        </ol>
                        <div class="hpm-leaderboard-note" v-if="spfLeaderNote">
                            {{ spfLeaderNote }}
                        </div>
                        <a class="hpm-leaderboard-foot" href="<?php echo $path; ?>system/list">
                            Explore all {{ spfStats.n }} on the full system list page <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                </div>

                <div class="hpm-note-card mt-4">
                    <div class="hpm-note-eyebrow">How SPF is measured</div>
                    <p>
                        We use <b>MID-approved Class 1 electricity metering</b> and <b>Class 2 heat metering</b> to measure the electricity consumed and heat delivered by each system over a full year. The SPF is the total heat delivered divided by the total electricity consumed, space heating and hot water combined. The {{ spfStats.n }} systems in this analysis are all monitored to the <b>SEPEMO H4</b> boundary - meaning that they include all electricity consumption from the outside unit, auxiliary energy such as backup or immersion heaters and any additional building circulation pumps or fans. The majority of the systems in this analysis are high temperature capable R290 units in an open-loop configuration that do not use an immersion heater and have the primary circulation pump built into the unit itself.
                    </p>
                    <p class="hpm-note-footnote">MID-approved: Certified to the accuracy classes defined in the Measuring Instruments Directive.</p>

                </div>
            </div>

            <div v-else class="hpm-finder-empty">
                <p class="mb-0">{{ finderError ? "Couldn’t load live system data right now — please try again shortly." : "Loading live system data…" }}</p>
            </div>
        </div>
    </section>


    <!-- ============ 04 · Find an installer ============ -->
    <!-- Static (non-interactive) OpenLayers preview of Modules/map, markers
         coloured by installer. OpenLayers + data load lazily when the section
         scrolls into view; all interaction is deferred to the full map page. -->

    <section class="hpm-section hpm-section-sky">
        <div class="container">
            <div class="hpm-eyebrow"><span class="hpm-eyebrow-num">04</span> Installation map</div>
            <h2 class="hpm-display mb-3">Find an installer</h2>
            <p class="hpm-lead mb-4">
               Use our map to find systems and installers near you.
            </p>

            <div class="hpm-map-embed">
                <div id="installer-map" class="hpm-map-canvas" aria-hidden="true"></div>
                <a class="hpm-map-cta" href="<?php echo $path; ?>map" aria-label="Open the full interactive map">
                    <span class="hpm-btn hpm-btn-primary">Open the full map <i class="bi bi-arrows-fullscreen"></i></span>
                </a>
            </div>
            <p class="hpm-finder-footnote">
                Locations are approximate. On the full map you can search by place name, filter
                systems and click through to each system and its installer.
            </p>

            <div id="installer-strip-label" class="hpm-installer-strip-label" style="display:none;">Installers with the most MID-metered systems</div>
            <div id="installer-strip" class="hpm-installer-strip"></div>
        </div>
    </section>

    <!-- ============ 05 · Explore the data ============ -->

    
    <section class="hpm-section">
        <div class="container">
            <div class="hpm-eyebrow"><span class="hpm-eyebrow-num">05</span> Tools &amp; data</div>
            <h2 class="hpm-display mb-3">Explore in more <span class="hpm-accent">detail</span></h2>
            <p class="hpm-lead mb-5">
                In addition to the main system list there are a number of useful tools available to explore the data in more detail.
            </p>
            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <a class="hpm-explore-card" href="<?php echo $path; ?>system/list">
                        <span class="hpm-explore-icon"><i class="bi bi-list-ul"></i></span>
                        <h3>System list</h3>
                        <p>All systems ranked by measured SPF, fabric or cost. Filter and explore over 200 different fields per system.</p>
                        <span class="hpm-go">Browse systems <i class="bi bi-arrow-right"></i></span>
                    </a>
                </div>
                <div class="col-md-6 col-xl-3">
                    <a class="hpm-explore-card" href="<?php echo $path; ?>heatpump">
                        <span class="hpm-explore-icon"><i class="bi bi-database"></i></span>
                        <h3>Heat pump database</h3>
                        <p>Search by make and model. Explore real world maximum capacity and minimum modulation test results.</p>
                        <span class="hpm-go">Look up a model <i class="bi bi-arrow-right"></i></span>
                    </a>
                </div>
                <div class="col-md-6 col-xl-3">
                    <a class="hpm-explore-card" href="<?php echo $path; ?>heatloss">
                        <span class="hpm-explore-icon"><i class="bi bi-thermometer-half"></i></span>
                        <h3>Heat demand tool</h3>
                        <p>Compare real world heat demand to the calculated value in the heat loss calculation.</p>
                        <span class="hpm-go">Open the tool <i class="bi bi-arrow-right"></i></span>
                    </a>
                </div>
                <div class="col-md-6 col-xl-3">
                    <a class="hpm-explore-card" href="https://docs.openenergymonitor.org/heatpumpmonitor">
                        <span class="hpm-explore-icon"><i class="bi bi-book"></i></span>
                        <h3>Analysis</h3>
                        <p>Correlation with performance. max output testing, over-sizing and a comparison with the Electrification of Heat trial.</p>
                        <span class="hpm-go">Read the docs <i class="bi bi-arrow-right"></i></span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 06 · From the forum ============ -->
    <!-- Latest topics in the heat pump category on the community forum,
         fetched via home/forum_topics (a cached server-side proxy — the
         Discourse API sends no CORS headers). -->

    <section class="hpm-section hpm-section-sky">
        <div class="container">
            <div class="hpm-eyebrow"><span class="hpm-eyebrow-num">06</span> Community</div>
            <h2 class="hpm-display mb-3">Join the <span class="hpm-accent">community</span></h2>
            <p class="hpm-lead mb-5">
                Join the discussion on the OpenEnergyMonitor community forum. Ask questions, share your experiences and learn from other heat pump owners and installers.
            </p>

            <div class="hpm-forum-card" v-if="forum.length">
                <div class="hpm-forum-head">
                    <h3><i class="bi bi-chat-square-text" style="color:var(--hpm-blue-deep);"></i> Latest heat pump topics</h3>
                    <span class="hpm-forum-src">community.openenergymonitor.org</span>
                </div>
                <div class="hpm-forum-cols" aria-hidden="true">
                    <span>Topic</span><span>Posters</span><span>Replies</span><span>Views</span><span>Activity</span>
                </div>
                <ol class="hpm-forum-list">
                    <li v-for="t in forum" :key="t.id">
                        <a class="hpm-forum-row" :href="t.url" target="_blank" rel="noopener">
                            <span>
                                <span class="hpm-forum-title"><i v-if="t.solved" class="bi bi-check-square-fill" title="Solved"></i> {{ t.title }}</span>
                                <span class="hpm-forum-tags" v-if="t.tags.length">
                                    <span class="hpm-forum-tag" v-for="tag in t.tags">{{ tag }}</span>
                                </span>
                            </span>
                            <span class="hpm-forum-posters">
                                <img v-for="p in t.posters.slice(0, 4)" :src="p.avatar" :alt="p.username" :title="p.username" loading="lazy">
                            </span>
                            <span class="hpm-forum-num hpm-forum-replies">{{ t.replies }}</span>
                            <span class="hpm-forum-num hpm-forum-views">{{ fmtViews(t.views) }}</span>
                            <span class="hpm-forum-num">{{ forumAgo(t.bumped_at) }}</span>
                        </a>
                    </li>
                </ol>
                <a class="hpm-leaderboard-foot" href="https://community.openenergymonitor.org/c/hardware/heatpump/47" target="_blank" rel="noopener">
                    Browse the heat pump category on the forum <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div v-else-if="forumError" class="hpm-finder-empty">
                <p class="mb-0">
                    Couldn&rsquo;t load the latest forum topics right now &mdash;
                    <a href="https://community.openenergymonitor.org/c/hardware/heatpump/47" target="_blank" rel="noopener">visit the forum directly</a>.
                </p>
            </div>

            <div v-else class="hpm-finder-empty">
                <p class="mb-0">Loading the latest forum topics&hellip;</p>
            </div>
        </div>
    </section>

    <!-- ============ 07 · Add your system ============ -->

    <section class="hpm-section" id="join-in">
        <div class="container">
            <div class="hpm-eyebrow"><span class="hpm-eyebrow-num">07</span> Join in</div>
            <h2 class="hpm-display mb-3">Monitor your heat pump. <span class="hpm-accent">Share your data.</span></h2>
            <p class="hpm-lead mb-5">
                Monitor your own heat pump to see how it performs. Add your system to the open data resource and help others learn from your real world experience.
            </p>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="hpm-step-card">
                        <span class="hpm-step-num">1</span>
                        <h3>Install monitoring</h3>
                        <p>A heat meter on the primary flow and return, an electricity meter on the heat pump supply, and our internet-connected data logging box record data every 10 seconds, giving you high-resolution data on exactly what the heat pump is doing moment to moment.
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hpm-step-card">
                        <span class="hpm-step-num">2</span>
                        <h3>Explore privately</h3>
                        <p>Use the heat pump dashboard on HeatpumpMonitor privately to explore the performance of your system. <br><br><strong>If you are an installer:</strong> track performance across multiple private systems.</p>  
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hpm-step-card">
                        <span class="hpm-step-num">3</span>
                        <h3>Share publicly (optional)</h3>
                        <p>Contribute your data to the open data public resource so that others can learn from your real world experience.</p>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3">
                <a class="hpm-btn hpm-btn-primary" href="https://shop.openenergymonitor.com/level-3-heat-pump-monitoring-bundle-emonhp">Buy it now: Level 3 Heat pump monitor <i class="bi bi-arrow-right"></i></a>
                <!-- login to view your own system -->
                <a class="hpm-btn hpm-btn-secondary" href="<?php echo $path; ?>user/login">Log in to view your dashboard <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <!-- ============ Closing band ============ -->
    
    <section class="hpm-closing">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <h2 class="mb-3">Open data &amp; open source.</h2>
                    <p class="mb-0">
                        HeatpumpMonitor.org is an <strong>OpenEnergyMonitor</strong> community initiative. With open-source code, hardware designs, and datasets, our goal is to empower homeowners, installers, designers, and researchers alike, helping everyone improve outcomes and accelerate the shift toward low-carbon heating.
                    </p>
                </div>
                <div class="col-lg-4">
                    <div class="d-flex flex-wrap gap-3 justify-content-lg-end">
                        <a class="hpm-btn hpm-btn-light" href="https://github.com/openenergymonitor/heatpumpmonitor.org"><i class="bi bi-github"></i> GitHub</a>
                        <a class="hpm-btn hpm-btn-light" href="<?php echo $path; ?>api-helper"><i class="bi bi-braces"></i> API</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
</div>

<script>

    var path = "<?php echo $path; ?>";

    // Eligible systems and cooling systems are embedded server-side rather
    // than fetched over ajax, for a faster initial load
    var eligible_systems = <?php echo json_encode($eligible_systems); ?>;
    var cooling_systems = <?php echo json_encode($cooling_systems); ?>;

    // Count of all MID metered systems, any boundary and any data length
    var mid_metered_count = <?php echo (int) $mid_metered_count; ?>;

    var FINDER_DEFAULTS = {property:"Any", floor:"Any", age:"Any", insulation:"Any", hp:"Any"};

    // Chip labels are kept short for the UI; these map them onto the exact
    // option strings stored in system_meta (see system_schema.php).
    var PROPERTY_MAP = {"Flat": "Flat / appartment"};
    var INSULATION_MAP = {
        "Fully insulated": "Fully insulated walls, floors and loft",
        "Some insulation": "Some insulation in walls and loft",
        "Cavity + loft": "Cavity wall, plus some loft insulation",
        "Uninsulated cavity": "Non-insulated cavity wall"
    };

    var app = new Vue({
        el: '#app',
        data: {
            path: path,
            // All MID metered systems, any boundary and any data length
            mid_metered_count: mid_metered_count,

            // Homes like yours
            homes: [],
            spfLeaderMode: "median",
            finderLoading: true,
            finderError: false,
            // Featured story: systems actively cooling over the last 30 days
            cooling: [],
            coolingError: false,
            // Latest heat pump topics from the community forum
            forum: [],
            forumError: false,
            finder: Object.assign({}, FINDER_DEFAULTS),
            showCheapest: true,
            costTariff: "agile",
            costTariffDefs: [
                { key: "agile", short: "Agile", name: "Octopus Agile" },
                { key: "cosy", short: "Cosy", name: "Octopus Cosy" },
                { key: "go", short: "Go", name: "Octopus Go" },
                { key: "cap", short: "Price cap", name: "the price cap" }
            ],
            finderOptions: {
                property: ["Any", "Detached", "Semi-detached", "End-terrace", "Mid-terrace", "Bungalow", "Flat"],
                floor: ["Any", "Under 80 m²", "80–120 m²", "120–160 m²", "160–220 m²", "Over 220 m²"],
                age: ["Any", "2012 or newer", "1983 to 2011", "1940 to 1982", "1900 to 1939", "Pre-1900"],
                insulation: ["Any", "Passivhaus", "Fully insulated", "Some insulation", "Cavity + loft", "Uninsulated cavity", "Solid walls"],
                hp: ["Any", "Air Source", "Ground Source"]
            },

            // Smart tariffs: unit price histogram
            // Electricity price cap flat unit rate, p/kWh (July 2026)
            PRICE_CAP: 26.11,
            tariff: "agile",
            tariffRank: "lowest",
            tariffDefs: [
                { key: "agile", short: "Agile", name: "Octopus Agile", sub: "Half-hourly wholesale-linked pricing" },
                { key: "cosy", short: "Cosy", name: "Octopus Cosy", sub: "Three cheap windows each day" },
                { key: "go", short: "Go", name: "Octopus Go", sub: "A cheap overnight window" }
            ]
        },
        mounted: function() {
            var self = this;

            this.homes = eligible_systems.map(function(s) {
                return {
                    id: s.id,
                    location: s.location || "",
                    manufacturer: s.hp_manufacturer || "",
                    model: s.hp_model || "",
                    capacity: s.hp_output,
                    floor: s.floor_area,
                    property: s.property,
                    age: s.age,
                    insulation: s.insulation,
                    hp: s.hp_type,
                    agile: s.unit_rate_agile,
                    cosy: s.unit_rate_cosy,
                    go: s.unit_rate_go,
                    cop: s.combined_cop,
                    elec: s.combined_elec_kwh,
                    heat: s.combined_heat_kwh
                };
            });
            this.finderLoading = false;

            this.cooling = cooling_systems.map(function(s) {
                return {
                    id: s.id,
                    location: s.location || "",
                    manufacturer: s.hp_manufacturer || "",
                    model: s.hp_model || "",
                    capacity: s.hp_output,
                    floor: s.floor_area,
                    cop: s.cooling_cop,
                    elec: s.cooling_elec_kwh,
                    heat: s.cooling_heat_kwh
                };
            });

            fetch(path + "home/forum_topics")
                .then(function(response) {
                    if (!response.ok) throw new Error("HTTP " + response.status);
                    return response.json();
                })
                .then(function(data) {
                    self.forum = data.slice(0, 5);
                    if (!data.length) self.forumError = true;
                })
                .catch(function(error) {
                    console.error("forum_topics:", error);
                    self.forumError = true;
                });
        },
        computed: {
            // ---- Headline stats derived from the eligible_systems dataset ----
            // The endpoint applies the same eligibility criteria as MID
            // monitored, metering boundary code 4, >330 days of data and air
            // error auto-flagging; null until the fetch completes.
            systemCount: function() {
                return this.homes.length ? this.homes.length : null;
            },
            meanSpfH4: function() {
                if (!this.homes.length) return null;
                var sum = this.homes.reduce(function(s, h) { return s + h.cop; }, 0);
                return (sum / this.homes.length).toFixed(2);
            },
            medianUnitRateAgile: function() {
                if (!this.homes.length) return null;
                return this.tariffMedianRates.agile.toFixed(1);
            },
            // ---- Featured story: active cooling over the last 30 days ----
            // Leaderboard ranked by cooling delivered per m² of floor area;
            // systems without a floor area can't be normalised so are excluded
            coolingTop: function() {
                return this.cooling.filter(function(h) { return h.floor > 0; })
                    .sort(function(a, b) { return (b.heat / b.floor) - (a.heat / a.floor); }).slice(0, 5);
            },
            coolingMaxKwh: function() {
                return this.cooling.reduce(function(max, h) { return Math.max(max, h.heat); }, 0);
            },
            coolingTotalKwh: function() {
                return this.cooling.reduce(function(sum, h) { return sum + h.heat; }, 0);
            },
            // Fleet-wide cooling COP: total heat removed over total electricity used,
            // across the systems reporting both
            coolingAvgCop: function() {
                var elec = 0, heat = 0;
                this.cooling.forEach(function(h) {
                    if (h.elec > 0 && h.heat > 0) { elec += h.elec; heat += h.heat; }
                });
                return elec > 0 ? heat / elec : 0;
            },
            finderActive: function() {
                var f = this.finder;
                return Object.keys(FINDER_DEFAULTS).some(function(k) { return f[k] !== "Any"; });
            },
            matches: function() {
                var f = this.finder;
                var floorBand = this.floorBand;
                var property = PROPERTY_MAP[f.property] || f.property;
                var insulation = INSULATION_MAP[f.insulation] || f.insulation;
                return this.homes.filter(function(h) {
                    if (f.property !== "Any" && h.property !== property) return false;
                    if (f.floor !== "Any" && (h.floor === null || floorBand(h.floor) !== f.floor)) return false;
                    if (f.age !== "Any" && h.age !== f.age) return false;
                    if (f.insulation !== "Any" && h.insulation !== insulation) return false;
                    if (f.hp !== "Any" && h.hp !== f.hp) return false;
                    return true;
                });
            },
            meanCopVal: function() {
                if (!this.matches.length) return 0;
                return this.matches.reduce(function(sum, h) { return sum + h.cop; }, 0) / this.matches.length;
            },
            medianElec: function() { return this.median(this.matches.map(function(h) { return h.elec; })); },
            medianHeat: function() { return this.median(this.matches.map(function(h) { return h.heat; })); },
            bestCop: function() { return this.matches.length ? Math.max.apply(null, this.matches.map(function(h) { return h.cop; })) : 0; },
            worstCop: function() { return this.matches.length ? Math.min.apply(null, this.matches.map(function(h) { return h.cop; })) : 0; },
            costTariffDef: function() {
                var key = this.costTariff;
                return this.costTariffDefs.filter(function(t) { return t.key === key; })[0];
            },
            // Median achieved rate per tariff across all homes — fallback for
            // the few systems without a computed rate
            tariffMedianRates: function() {
                var rates = {};
                var self = this;
                ["agile", "cosy", "go"].forEach(function(key) {
                    var v = self.homes.map(function(h) { return h[key]; })
                        .filter(function(r) { return r !== null && r > 0; })
                        .sort(function(a, b) { return a - b; });
                    rates[key] = v.length ? self.quantile(v, 0.5) : self.PRICE_CAP;
                });
                return rates;
            },
            medianCost: function() {
                var costOf = this.costOf.bind(this);
                return this.median(this.matches.map(costOf));
            },
            ranked: function() {
                var cheapest = this.showCheapest;
                var costOf = this.costOf.bind(this);
                return this.matches.slice().sort(function(a, b) {
                    return cheapest ? (costOf(a) - costOf(b)) : (costOf(b) - costOf(a));
                });
            },
            fabricTopFour: function() { return this.ranked.slice(0, 4); },
            medianX: function() { return this.costX(this.medianCost); },
            // Axis domain from the full dataset (not the matches) so the axis
            // stays put while filtering; snapped outwards to £100 steps
            costDomain: function() {
                if (!this.homes.length) return { lo: 0, hi: 1600 };
                var costOf = this.costOf.bind(this);
                var costs = this.homes.map(costOf);
                var lo = Math.floor(Math.min.apply(null, costs) / 100) * 100;
                var hi = Math.ceil(Math.max.apply(null, costs) / 100) * 100;
                if (hi - lo < 400) hi = lo + 400;
                return { lo: lo, hi: hi };
            },
            costTicks: function() {
                var d = this.costDomain;
                var span = d.hi - d.lo;
                var steps = [100, 200, 250, 500, 1000, 2000];
                var step = steps[steps.length - 1];
                for (var i = 0; i < steps.length; i++) {
                    if (span / steps[i] <= 8) { step = steps[i]; break; }
                }
                var ticks = [];
                for (var t = Math.ceil(d.lo / step) * step; t <= d.hi + 1e-9; t += step) {
                    ticks.push(t);
                }
                return ticks;
            },
            // ---- Smart tariffs: unit price histogram ----
            tariffDef: function() {
                var key = this.tariff;
                return this.tariffDefs.filter(function(t) { return t.key === key; })[0];
            },
            tariffValues: function() {
                var key = this.tariff;
                return this.homes.map(function(h) { return h[key]; })
                    .filter(function(v) { return v !== null && v > 0; })
                    .sort(function(a, b) { return a - b; });
            },
            tariffStats: function() {
                var v = this.tariffValues;
                var q = this.quantile;
                if (!v.length) return { n: 0, median: 0, mean: 0, q1: 0, q3: 0, min: 0, max: 0 };
                return {
                    n: v.length,
                    median: q(v, 0.5),
                    mean: v.reduce(function(a, b) { return a + b; }, 0) / v.length,
                    q1: q(v, 0.25),
                    q3: q(v, 0.75),
                    min: v[0],
                    max: v[v.length - 1]
                };
            },
            tariffVsCap: function() {
                return (1 - this.tariffStats.median / this.PRICE_CAP) * 100;
            },
            tariffRankDef: function() {
                return {
                    lowest: { title: "Lowest unit costs" },
                    median: { title: "Right on the median" },
                    highest: { title: "Highest unit costs" }
                }[this.tariffRank];
            },
            tariffTopFive: function() {
                var key = this.tariff;
                var mode = this.tariffRank;
                var median = this.tariffStats.median;
                var g = this.homes.filter(function(h) { return h[key] !== null && h[key] > 0; });
                if (mode === "median") {
                    g.sort(function(a, b) { return Math.abs(a[key] - median) - Math.abs(b[key] - median); });
                    return g.slice(0, 5).sort(function(a, b) { return a[key] - b[key]; });
                }
                g.sort(function(a, b) { return mode === "lowest" ? (a[key] - b[key]) : (b[key] - a[key]); });
                return g.slice(0, 5);
            },
            // Shared price axis across all three tariffs, so toggling shows the
            // distribution move relative to the price cap. Outliers fold into
            // the top band.
            histDomain: function() {
                var all = [];
                this.homes.forEach(function(h) {
                    [h.agile, h.cosy, h.go].forEach(function(v) { if (v !== null && v > 0) all.push(v); });
                });
                if (!all.length) return { lo: 10, hi: 32 };
                var lo = Math.floor(Math.min.apply(null, all));
                var hi = Math.min(Math.ceil(Math.max.apply(null, all)), Math.max(this.PRICE_CAP + 6, 32));
                return { lo: lo, hi: hi };
            },
            histXTicks: function() {
                var ticks = [];
                var d = this.histDomain;
                for (var t = Math.ceil(d.lo / 2) * 2; t <= d.hi + 1e-9; t += 2) ticks.push(t);
                return ticks;
            },
            histBins: function() {
                var d = this.histDomain;
                var binw = 0.5;
                var nbins = Math.round((d.hi - d.lo) / binw);
                var counts = [];
                for (var i = 0; i < nbins; i++) counts.push(0);
                this.tariffValues.forEach(function(v) {
                    var i = Math.min(Math.floor((v - d.lo) / binw), nbins - 1);
                    if (i < 0) i = 0;
                    counts[i]++;
                });
                var maxCount = Math.max.apply(null, counts.concat([1]));
                var self = this;
                return counts.map(function(c, i) {
                    var x0 = d.lo + i * binw;
                    var x1 = x0 + binw;
                    var last = (i === nbins - 1);
                    var y = self.histY(c);
                    return {
                        x: self.histX(x0),
                        w: self.histX(x1) - self.histX(x0),
                        y: y,
                        h: 300 - y,
                        label: x0.toFixed(1) + "–" + x1.toFixed(1) + (last ? "+ " : "p — ") + c + " system" + (c === 1 ? "" : "s"),
                        count: c
                    };
                });
            },
            histMaxCount: function() {
                var d = this.histDomain;
                var binw = 0.5;
                var nbins = Math.round((d.hi - d.lo) / binw);
                var counts = {};
                var max = 1;
                this.tariffValues.forEach(function(v) {
                    var i = Math.min(Math.floor((v - d.lo) / binw), nbins - 1);
                    counts[i] = (counts[i] || 0) + 1;
                    if (counts[i] > max) max = counts[i];
                });
                return max;
            },
            histYTicks: function() {
                var steps = [1, 2, 5, 10, 20, 25, 50, 100];
                var step = steps[steps.length - 1];
                for (var i = 0; i < steps.length; i++) {
                    if (this.histMaxCount / steps[i] <= 5) { step = steps[i]; break; }
                }
                var ticks = [];
                for (var t = step; t <= this.histMaxCount + 1e-9; t += step) ticks.push(t);
                return ticks;
            },

            // ---- SPF distribution histogram ----
            spfValues: function() {
                return this.homes.map(function(h) { return h.cop; })
                    .filter(function(v) { return v !== null && v > 0; })
                    .sort(function(a, b) { return a - b; });
            },
            spfStats: function() {
                var v = this.spfValues;
                var q = this.quantile;
                if (!v.length) return { n: 0, median: 0, mean: 0, q1: 0, q3: 0, min: 0, max: 0 };
                return {
                    n: v.length,
                    median: q(v, 0.5),
                    mean: v.reduce(function(a, b) { return a + b; }, 0) / v.length,
                    q1: q(v, 0.25),
                    q3: q(v, 0.75),
                    min: v[0],
                    max: v[v.length - 1]
                };
            },
            // Systems ranked by SPF, best first, for the winner card and leaderboard views
            spfRankedHomes: function() {
                return this.homes
                    .filter(function(h) { return h.cop !== null && h.cop > 0; })
                    .slice()
                    .sort(function(a, b) { return b.cop - a.cop; });
            },
            spfSelectionBand: function() {
                var ranked = this.spfRankedHomes;
                var stats = this.spfStats;
                var n = ranked.length;
                var count = 0;
                if (!n) return null;

                if (this.spfLeaderMode === "top") {
                    count = Math.min(5, n);
                    return { lo: ranked[count - 1].cop, hi: ranked[0].cop, count: count, label: "Top 5 systems" };
                }
                if (this.spfLeaderMode === "bottom") {
                    count = Math.max(1, Math.round(n * 0.25));
                    return { lo: ranked[n - 1].cop, hi: ranked[n - count].cop, count: count, label: "Bottom 25% of systems" };
                }
                if (this.spfLeaderMode === "decile") {
                    count = Math.max(1, Math.round(n * 0.25));
                    return { lo: ranked[count - 1].cop, hi: ranked[0].cop, count: count, label: "Top 25% of systems" };
                }
                return { lo: stats.q1, hi: stats.q3, count: Math.max(1, Math.round(n * 0.5)), label: "Middle 50% of systems" };
            },
            spfLeaders: function() {
                var ranked = this.spfRankedHomes;
                var n = ranked.length;
                var bandCount = 0;
                var bandStart = 0;
                if (!n) return [];
                var start = 0;
                if (this.spfLeaderMode === "bottom") {
                    bandCount = Math.max(1, Math.round(n * 0.25));
                    bandStart = Math.max(0, n - bandCount);
                    start = Math.round(bandStart + bandCount * 0.5) - 3;
                    start = Math.max(bandStart, Math.min(start, Math.max(0, n - 5)));
                } else if (this.spfLeaderMode === "median") start = Math.max(0, Math.round(n / 2) - 3);
                // Middle of the top-25% band: window centred on the band's median rank
                else if (this.spfLeaderMode === "decile") {
                    bandCount = Math.max(1, Math.round(n * 0.25));
                    start = Math.max(0, Math.round(bandCount * 0.5) - 3);
                }
                return ranked.slice(start, start + 5).map(function(h, i) {
                    return { h: h, rank: start + i + 1 };
                });
            },
            spfLeaderNote: function() {
                var band = this.spfSelectionBand;
                if (!band) return "";
                if (this.spfLeaderMode === "decile") {
                    return "Top 25% = " + this.spfTopQuartileStats.n + " systems, SPF " + this.spfTopQuartileStats.min.toFixed(2) + "–" + this.spfTopQuartileStats.max.toFixed(2) + ", mean " + this.spfTopQuartileStats.mean.toFixed(2) + ", median " + this.spfTopQuartileStats.median.toFixed(2);
                }
                if (this.spfLeaderMode === "median") {
                    return "Middle 50% = roughly " + band.count + " systems, SPF " + this.spfStats.q1.toFixed(2) + "–" + this.spfStats.q3.toFixed(2) + "; the list shows five homes around the median.";
                }
                if (this.spfLeaderMode === "top") {
                    return "Top 5 = SPF " + band.lo.toFixed(2) + "–" + band.hi.toFixed(2) + ".";
                }
                return "Bottom 25% = " + this.spfBottomQuartileStats.n + " systems, SPF " + this.spfBottomQuartileStats.min.toFixed(2) + "–" + this.spfBottomQuartileStats.max.toFixed(2) + ", mean " + this.spfBottomQuartileStats.mean.toFixed(2) + ", median " + this.spfBottomQuartileStats.median.toFixed(2) + ". An SPF of " + this.spfBottomQuartileStats.median.toFixed(2) + " is still a good result!"
            },
            // Count, range, mean and median of the top 25% of systems by SPF
            spfTopQuartileStats: function() {
                var ranked = this.spfRankedHomes;
                var n = ranked.length;
                if (!n) return { n: 0, min: 0, max: 0, mean: 0, median: 0 };
                var k = Math.max(1, Math.round(n * 0.25));
                var v = ranked.slice(0, k).map(function(h) { return h.cop; }).reverse();
                return {
                    n: k,
                    min: v[0],
                    max: v[v.length - 1],
                    mean: v.reduce(function(a, b) { return a + b; }, 0) / k,
                    median: this.quantile(v, 0.5)
                };
            },
            // Count, range, mean and median of the bottom 25% of systems by SPF
            spfBottomQuartileStats: function() {
                var ranked = this.spfRankedHomes;
                var n = ranked.length;
                if (!n) return { n: 0, min: 0, max: 0, mean: 0, median: 0 };
                var k = Math.max(1, Math.round(n * 0.25));
                var v = ranked.slice(n - k).map(function(h) { return h.cop; }).reverse();
                return {
                    n: k,
                    min: v[0],
                    max: v[v.length - 1],
                    mean: v.reduce(function(a, b) { return a + b; }, 0) / k,
                    median: this.quantile(v, 0.5)
                };
            },
            // SPF axis snapped outwards to 0.5 steps
            spfDomain: function() {
                var v = this.spfValues;
                if (!v.length) return { lo: 2, hi: 6 };
                var lo = Math.floor(v[0] * 2) / 2;
                var hi = Math.ceil(v[v.length - 1] * 2) / 2;
                if (hi - lo < 1) hi = lo + 1;
                return { lo: lo, hi: hi };
            },
            spfXTicks: function() {
                var ticks = [];
                var d = this.spfDomain;
                for (var t = d.lo; t <= d.hi + 1e-9; t += 0.5) ticks.push(t);
                return ticks;
            },
            spfBinCounts: function() {
                var d = this.spfDomain;
                var binw = 0.1;
                var nbins = Math.round((d.hi - d.lo) / binw);
                var counts = [];
                for (var i = 0; i < nbins; i++) counts.push(0);
                this.spfValues.forEach(function(v) {
                    // the epsilon keeps values like 3.5 out of the 3.4–3.5 bin
                    var i = Math.min(Math.floor((v - d.lo) / binw + 1e-9), nbins - 1);
                    if (i < 0) i = 0;
                    counts[i]++;
                });
                return counts;
            },
            spfMaxCount: function() {
                return Math.max.apply(null, this.spfBinCounts.concat([1]));
            },
            spfBins: function() {
                var d = this.spfDomain;
                var binw = 0.1;
                var self = this;
                return this.spfBinCounts.map(function(c, i) {
                    var x0 = d.lo + i * binw;
                    var x1 = x0 + binw;
                    var y = self.spfY(c);
                    return {
                        x: self.spfX(x0),
                        w: self.spfX(x1) - self.spfX(x0),
                        y: y,
                        h: 300 - y,
                        label: "SPF " + x0.toFixed(1) + "–" + x1.toFixed(1) + " — " + c + " system" + (c === 1 ? "" : "s"),
                        count: c
                    };
                });
            },
            spfYTicks: function() {
                var steps = [1, 2, 5, 10, 20, 25, 50, 100];
                var step = steps[steps.length - 1];
                for (var i = 0; i < steps.length; i++) {
                    if (this.spfMaxCount / steps[i] <= 5) { step = steps[i]; break; }
                }
                var ticks = [];
                for (var t = step; t <= this.spfMaxCount + 1e-9; t += step) ticks.push(t);
                return ticks;
            },

            // Dot strip: bin running cost and stack ties upwards from the baseline.
            // Row spacing and dot size adapt so the tallest stack fits the plot.
            stripDots: function() {
                var top = this.fabricTopFour;
                var costOf = this.costOf.bind(this);
                var costX = this.costX.bind(this);
                var fmt = this.fmt;
                var subtitle = this.homeSubtitle;
                var d = this.costDomain;
                var bin_size = Math.max(5, Math.round((d.hi - d.lo) / 48 / 5) * 5);
                var costRate = this.costRate.bind(this);
                var sorted = this.matches.slice().sort(function(a, b) { return costOf(a) - costOf(b); });

                var counts = {};
                var max_stack = 1;
                sorted.forEach(function(h) {
                    var bin = Math.round(costOf(h) / bin_size);
                    counts[bin] = (counts[bin] || 0) + 1;
                    if (counts[bin] > max_stack) max_stack = counts[bin];
                });
                var dy = max_stack > 1 ? Math.min(13, 149 / (max_stack - 1)) : 13;
                var r = Math.min(5.5, Math.max(2.75, dy * 0.48));

                var stacks = {};
                return sorted.map(function(h) {
                    var cost = costOf(h);
                    var bin = Math.round(cost / bin_size);
                    stacks[bin] = (stacks[bin] || 0) + 1;
                    return {
                        id: h.id,
                        x: costX(cost),
                        y: 183 - (stacks[bin] - 1) * dy,
                        r: r,
                        top: top.indexOf(h) !== -1,
                        label: h.location + " — " + subtitle(h) + " · ≈£" + fmt(cost) + "/yr (" + costRate(h).toFixed(1) + "p/kWh) · SPF " + h.cop.toFixed(1) + " · " + fmt(h.heat) + " kWh heat"
                    };
                });
            }
        },
        methods: {
            clearFinder: function() { this.finder = Object.assign({}, FINDER_DEFAULTS); },
            openSystem: function(id) { window.location = path + "system/view?id=" + id; },
            // "Air Source" / "Ground Source" shown as "Air-source" / "Ground-source"
            hpTypeLabel: function(h) {
                return h.hp ? h.hp.replace(" Source", "-source") : "";
            },
            // Same source-type colours as the system list
            hpTypeColor: function(h) {
                if (h.hp === "Air Source") return "#4f8baa";
                if (h.hp === "Ground Source") return "#938e03";
                return "";
            },
            // "Vaillant Arotherm Plus · 7 kW · 110 m²" from whichever fields are present
            homeSubtitle: function(h) {
                var make_model = (h.manufacturer + " " + h.model).trim();
                // Avoid "Vaillant Vaillant Arotherm" when the model already includes the make
                if (h.manufacturer && h.model.toLowerCase().indexOf(h.manufacturer.toLowerCase()) === 0) {
                    make_model = h.model;
                }
                var parts = [];
                if (make_model) parts.push(make_model);
                if (h.capacity) parts.push(h.capacity + " kW");
                if (h.floor) parts.push(Math.round(h.floor) + " m²");
                return parts.join(" · ");
            },
            floorBand: function(floor) {
                if (floor < 80) return "Under 80 m²";
                if (floor < 120) return "80–120 m²";
                if (floor < 160) return "120–160 m²";
                if (floor < 220) return "160–220 m²";
                return "Over 220 m²";
            },
            // The unit rate used to cost a home on the selected tariff: its own
            // achieved rate, the tariff median if missing, or the flat cap rate
            costRate: function(h) {
                if (this.costTariff === "cap") return this.PRICE_CAP;
                var v = h[this.costTariff];
                return (v !== null && v > 0) ? v : this.tariffMedianRates[this.costTariff];
            },
            // Estimated annual running cost in £ on the selected tariff
            costOf: function(h) { return h.elec * this.costRate(h) / 100; },
            // Effective unit rate of a system on the selected tariff
            rateOf: function(h) { return h[this.tariff]; },
            // Linear-interpolated quantile of a sorted array
            quantile: function(sorted, p) {
                var idx = (sorted.length - 1) * p;
                var lo = Math.floor(idx);
                var hi = Math.ceil(idx);
                return sorted[lo] + (sorted[hi] - sorted[lo]) * (idx - lo);
            },
            // Map p/kWh onto the histogram's 50–660 plot width
            histX: function(v) {
                var d = this.histDomain;
                var c = Math.min(Math.max(v, d.lo), d.hi);
                return 50 + (c - d.lo) / (d.hi - d.lo) * 610;
            },
            // Map a bin count onto the histogram's 30–300 plot height
            histY: function(count) {
                return 300 - (count / this.histMaxCount) * 270;
            },
            // Map SPF onto the distribution histogram's 50–660 plot width
            spfX: function(v) {
                var d = this.spfDomain;
                var c = Math.min(Math.max(v, d.lo), d.hi);
                return 50 + (c - d.lo) / (d.hi - d.lo) * 610;
            },
            // Map a bin count onto the distribution histogram's 30–300 plot height
            spfY: function(count) {
                return 300 - (count / this.spfMaxCount) * 270;
            },
            // Map cost onto the strip's 60–650 plot width
            costX: function(cost) {
                var d = this.costDomain;
                var c = Math.min(Math.max(cost, d.lo), d.hi);
                return 60 + (c - d.lo) / (d.hi - d.lo) * 590;
            },
            median: function(values) {
                if (!values.length) return 0;
                var sorted = values.slice().sort(function(a, b) { return a - b; });
                var mid = Math.floor(sorted.length / 2);
                return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            },
            fmt: function(n) { return Math.round(n).toLocaleString(); },
            // Discourse-style compact relative time: 12m, 5h, 3d, 2mo
            forumAgo: function(iso) {
                var s = (Date.now() - new Date(iso).getTime()) / 1000;
                if (s < 60) return "now";
                if (s < 3600) return Math.round(s / 60) + "m";
                if (s < 86400) return Math.round(s / 3600) + "h";
                if (s < 2592000) return Math.round(s / 86400) + "d";
                if (s < 31536000) return Math.round(s / 2592000) + "mo";
                return Math.round(s / 31536000) + "y";
            },
            // Discourse-style view counts: 850, 1.2k
            fmtViews: function(n) {
                return n >= 1000 ? (n / 1000).toFixed(1).replace(/\.0$/, "") + "k" : "" + n;
            }
        }
    });
</script>

<script>
// ---- 04 · Find an installer: static map preview ----
// OpenLayers (~200 kB) and the system/installer data are only fetched once the
// section approaches the viewport. The preview map has no controls and no
// interactions; the overlay link takes users to the full map page.
(function() {

    var mapEl = document.getElementById("installer-map");
    if (!mapEl) return;

    var started = false;

    function initMapPreview() {
        if (started) return;
        started = true;

        // OpenLayers is vendored (see theme/vendor/MANIFEST.md); hashes match the manifest
        var css = document.createElement("link");
        css.rel = "stylesheet";
        css.href = path + "theme/vendor/ol-7.4.0/ol.css";
        css.integrity = "sha384-9bhh0d43Gp9ct3OBqOdccAg6nQ48H3ibQN30wXbD0oC/uFYftJt4aOK45aGS1TJL";
        document.head.appendChild(css);

        var script = document.createElement("script");
        script.src = path + "theme/vendor/ol-7.4.0/ol.js";
        script.integrity = "sha384-UnFf8KXzbcP68LSCc4PSf4WbixBySdywUoVXLwlMw14SsRiQYHMAImua4dnHgwzo";
        script.onload = loadData;
        document.head.appendChild(script);
    }

    function loadData() {
        Promise.all([
            fetch(path + "system/list/public.json").then(function(r) { return r.json(); }),
            fetch(path + "installer/list.json?system_count=1").then(function(r) { return r.json(); })
        ]).then(function(results) {
            drawMap(results[0], results[1]);
            drawInstallerStrip(results[1]);
        }).catch(function(error) {
            console.error("installer map preview:", error);
        });
    }

    function drawMap(systems, installers) {
        // Installer name → brand colour, as used on the full map
        var installerColors = {};
        installers.forEach(function(installer) {
            if (installer.name && installer.color) {
                installerColors[installer.name.trim()] = installer.color;
            }
        });

        var markerSource = new ol.source.Vector();
        systems.forEach(function(system) {
            if (!system.latitude || !system.longitude) return;
            var color = "#cccccc";
            if (system.installer_name && installerColors[system.installer_name.trim()]) {
                color = installerColors[system.installer_name.trim()];
            }
            var marker = new ol.Feature({
                geometry: new ol.geom.Point(ol.proj.fromLonLat([system.longitude, system.latitude]))
            });
            marker.setStyle(new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 5,
                    fill: new ol.style.Fill({ color: hexToRgba(color, 0.85) }),
                    stroke: new ol.style.Stroke({ color: "rgba(255,255,255,0.9)", width: 1 })
                })
            }));
            markerSource.addFeature(marker);
        });

        var esriTiles = "https://services.arcgisonline.com/ArcGIS/rest/services/";

        // Multiply blend, matching the full map: the hillshade service renders
        // sea as white, so plain opacity would wash the water out.
        var hillshadeLayer = new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: esriTiles + "Elevation/World_Hillshade/MapServer/tile/{z}/{y}/{x}",
                maxZoom: 16
            })
        });
        hillshadeLayer.on("prerender", function(event) {
            event.context.globalCompositeOperation = "multiply";
        });
        hillshadeLayer.on("postrender", function(event) {
            event.context.globalCompositeOperation = "source-over";
        });

        new ol.Map({
            target: "installer-map",
            controls: [new ol.control.Attribution({ collapsible: true })],
            interactions: [],
            layers: [
                new ol.layer.Tile({
                    source: new ol.source.XYZ({
                        url: esriTiles + "Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}",
                        attributions: 'Tiles &copy; <a href="https://www.esri.com">Esri</a> &mdash; Esri, USGS, NOAA, Garmin, HERE, &copy; OpenStreetMap contributors',
                        maxZoom: 16
                    })
                }),
                hillshadeLayer,
                new ol.layer.Tile({
                    source: new ol.source.XYZ({
                        url: esriTiles + "Canvas/World_Light_Gray_Reference/MapServer/tile/{z}/{y}/{x}",
                        maxZoom: 16
                    })
                }),
                new ol.layer.Vector({ source: markerSource })
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat([-3.8, 54.3]),
                zoom: 5.7
            })
        });
    }

    function drawInstallerStrip(installers) {
        var strip = document.getElementById("installer-strip");
        if (!strip) return;

        var top = installers.filter(function(installer) {
            return installer.name && installer.mid_systems > 0;
        }).sort(function(a, b) {
            return b.mid_systems - a.mid_systems;
        }).slice(0, 10);

        top.forEach(function(installer) {
            var chip = document.createElement("a");
            chip.className = "hpm-installer-chip";
            chip.href = path + "system/list?filter=" + encodeURIComponent(installer.name) + "&period=last365&minDays=290&mode=costs&tariff=agile";
            chip.title = "Show " + installer.name + " systems in the system list";

            var dot = document.createElement("span");
            dot.className = "dot";
            dot.style.background = installer.color || "#cccccc";
            chip.appendChild(dot);

            chip.appendChild(document.createTextNode(installer.name));

            var count = document.createElement("span");
            count.className = "count";
            count.textContent = installer.mid_systems;
            chip.appendChild(count);

            strip.appendChild(chip);
        });

        var label = document.getElementById("installer-strip-label");
        if (label && top.length) label.style.display = "";
    }

    function hexToRgba(hex, alpha) {
        hex = hex.replace(/^#/, "");
        if (hex.length === 3) {
            hex = hex.split("").map(function(x) { return x + x; }).join("");
        }
        if (hex.length !== 6) return "rgba(204,204,204," + alpha + ")";
        var num = parseInt(hex, 16);
        return "rgba(" + ((num >> 16) & 255) + "," + ((num >> 8) & 255) + "," + (num & 255) + "," + alpha + ")";
    }

    if ("IntersectionObserver" in window) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    observer.disconnect();
                    initMapPreview();
                }
            });
        }, { rootMargin: "600px 0px" });
        observer.observe(mapEl);
    } else {
        initMapPreview();
    }

})();
</script>