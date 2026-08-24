<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

global $path;
?>
<style>
/* ===================================================================
   Flow temperature vs performance explorer
   Moved out of home_view.php onto its own page while it is worked on.
   The block marked "shared scaffolding" duplicates home_view.php
   styles and can simply be deleted if merged back; everything else is
   specific to this page.
   =================================================================== */

/* ---- shared scaffolding (copied from home_view.php — drop on merge) ---- */
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
.hpm-btn:focus-visible {
    outline: 3px solid rgba(68, 179, 226, 0.55);
    outline-offset: 2px;
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
.hpm-scatter { width: 100%; height: auto; display: block; }
.hpm-scatter circle { cursor: pointer; }
.hpm-scatter .grid { stroke: #e7f0f6; stroke-width: 1; }
.hpm-scatter .axis-label {
    font-size: 13px;
    fill: var(--hpm-muted);
    font-family: inherit;
}
.hpm-scatter .axis-title {
    font-size: 13px;
    fill: var(--hpm-ink-soft);
    font-weight: 600;
    font-family: inherit;
}
.hpm-scatter .trend {
    stroke: var(--hpm-ink);
    stroke-width: 2;
    stroke-dasharray: 7 6;
    fill: none;
    opacity: 0.75;
}
.hpm-scatter .dot-a { fill: var(--hpm-teal); stroke: #fff; stroke-width: 1.5; }
.hpm-scatter .dot-b { fill: var(--hpm-amber); stroke: #fff; stroke-width: 1.5; }
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

.hpm-finder-empty {
    background: var(--hpm-card);
    border: 1px dashed var(--hpm-line);
    border-radius: 1rem;
    padding: 3rem 2rem;
    text-align: center;
    color: var(--hpm-muted);
}

/* ---- design flow temperature explorer ---- */
.hpm-dft { width: 100%; height: auto; display: block; }
.hpm-dft .grid { stroke: #e7f0f6; stroke-width: 1; }
.hpm-dft .axis-label { font-size: 13px; fill: var(--hpm-muted); font-family: inherit; }
.hpm-dft .row-bg { fill: transparent; cursor: pointer; }
.hpm-dft .row-bg.selected { fill: var(--hpm-sky); }
.hpm-dft .row-bg:hover { fill: var(--hpm-sky); opacity: 0.6; }
.hpm-dft .row-bg.selected:hover { opacity: 1; }
.hpm-dft .row-label { font-size: 16px; font-weight: 700; fill: var(--hpm-ink); font-family: inherit; cursor: pointer; }
.hpm-dft .row-count { font-size: 11.5px; fill: var(--hpm-muted); font-family: inherit; cursor: pointer; }
.hpm-dft .row-mean { font-size: 15px; font-weight: 700; fill: var(--hpm-ink); font-family: inherit; font-variant-numeric: tabular-nums; }
.hpm-dft .row-mean .lab { font-size: 11.5px; font-weight: 600; fill: var(--hpm-muted); }
.hpm-dft .dot-sel { fill: var(--hpm-teal); stroke: #fff; stroke-width: 1.5; cursor: pointer; transition: stroke 0.12s ease, stroke-width 0.12s ease, filter 0.12s ease; }
.hpm-dft .dot-dim { fill: #aac4d4; stroke: #fff; stroke-width: 1.5; cursor: pointer; transition: stroke 0.12s ease, stroke-width 0.12s ease, filter 0.12s ease, fill 0.12s ease; }
.hpm-dft .dot-sel:hover,
.hpm-dft .dot-dim:hover {
    stroke: rgba(42, 157, 143, 0.4);
    stroke-width: 5;
    filter: drop-shadow(0 0 4px rgba(42, 157, 143, 0.65));
}
.hpm-dft .dot-dim:hover { fill: var(--hpm-teal); }
.hpm-dft .dot-sel.nm { fill: var(--hpm-amber); }
.hpm-dft .dot-dim.nm { fill: #d9b98c; }
.hpm-dft .dot-sel.nm:hover,
.hpm-dft .dot-dim.nm:hover {
    stroke: rgba(201, 119, 22, 0.4);
    filter: drop-shadow(0 0 4px rgba(201, 119, 22, 0.65));
}
.hpm-dft .dot-dim.nm:hover { fill: var(--hpm-amber); }
.hpm-dft .mean-tick { stroke: var(--hpm-ink); stroke-width: 2; opacity: 0.75; }

.hpm-thermo { width: 100%; height: auto; display: block; }
.hpm-thermo .track { stroke: var(--hpm-line); stroke-width: 2; }
.hpm-thermo .tick-label { font-size: 12px; fill: var(--hpm-muted); font-family: inherit; }
.hpm-thermo .marker-label { font-size: 13px; font-weight: 600; fill: var(--hpm-ink-soft); font-family: inherit; }
.hpm-thermo .design-marker { stroke: var(--hpm-ink); stroke-width: 2.5; }
.hpm-thermo .actual-marker { fill: var(--hpm-teal); stroke: #fff; stroke-width: 2; }
.hpm-thermo .gap-arrow { stroke: var(--hpm-amber); stroke-width: 2; fill: none; }
.hpm-thermo .gap-head { fill: var(--hpm-amber); }

/* ---- correlation walkthrough: stepper, predictor & annotations ---- */
.hpm-steps { display: flex; gap: 0.75rem; margin-bottom: 2rem; }
.hpm-step {
    flex: 1 1 0;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    text-align: left;
    background: var(--hpm-card);
    border: 1px solid var(--hpm-line);
    border-radius: 1rem;
    padding: 0.9rem 1.15rem;
    cursor: pointer;
    font-family: inherit;
    color: inherit;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.hpm-step:hover { border-color: var(--hpm-blue); }
.hpm-step.active {
    border-color: var(--hpm-blue-deep);
    box-shadow: 0 4px 16px rgba(20, 52, 74, 0.1);
}
.hpm-step:focus-visible { outline: 3px solid rgba(68, 179, 226, 0.55); outline-offset: 2px; }
.hpm-step .num {
    flex: none;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hpm-sky-deep);
    color: var(--hpm-ink);
    font-weight: 700;
}
.hpm-step.active .num { background: var(--hpm-blue-deep); color: #fff; }
.hpm-step .txt { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.hpm-step .t { font-weight: 700; font-size: 0.9375rem; color: var(--hpm-ink); line-height: 1.25; }
.hpm-step .s { font-size: 0.8125rem; color: var(--hpm-muted); }
.hpm-step .r2 {
    margin-left: auto;
    flex: none;
    font-size: 0.8125rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    color: var(--hpm-ink-soft);
    background: var(--hpm-sky);
    border: 1px solid var(--hpm-line);
    border-radius: 2rem;
    padding: 0.25rem 0.65rem;
}
.hpm-step.active .r2 { background: var(--hpm-blue-deep); border-color: var(--hpm-blue-deep); color: #fff; }
.hpm-stage-lead { font-size: 1.0625rem; max-width: 80ch; margin-bottom: 1.5rem; }
.hpm-step-nav { display: flex; gap: 1rem; margin-top: 2rem; }
.hpm-step-nav .hpm-btn-primary { margin-left: auto; }
.hpm-r2-line {
    margin-top: 1rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--hpm-line);
    font-size: 0.9375rem;
    color: var(--hpm-ink-soft);
}
.hpm-predict-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--hpm-ink);
    margin: 0.9rem 0 0.4rem;
}
.hpm-predict-input { display: flex; align-items: center; gap: 0.85rem; margin-bottom: 0.9rem; }
.hpm-predict-input input[type="range"] { flex: 1 1 auto; min-width: 0; accent-color: var(--hpm-blue-deep); }
.hpm-predict-input input[type="number"] {
    flex: none;
    width: 5.25rem;
    padding: 0.35rem 0.5rem;
    border: 1px solid var(--hpm-line);
    border-radius: 0.5rem;
    font: inherit;
    font-weight: 700;
    color: var(--hpm-ink);
    font-variant-numeric: tabular-nums;
    text-align: right;
}
.hpm-predict-input .unit { font-weight: 600; color: var(--hpm-muted); }
.hpm-predict-range {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--hpm-ink);
    font-variant-numeric: tabular-nums;
    margin-bottom: 0.9rem;
}
.hpm-predict-range .lab { font-size: 0.8125rem; font-weight: 600; color: var(--hpm-muted); margin-left: 0.35rem; }
.hpm-predict-design {
    display: flex;
    align-items: baseline;
    gap: 0.55rem;
    margin: 0 0 0.9rem;
    padding: 0.6rem 0.8rem;
    background: var(--hpm-sky);
    border: 1px solid var(--hpm-line);
    border-radius: 0.6rem;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: var(--hpm-ink-soft);
}
.hpm-predict-design .bi { color: var(--hpm-blue-deep); flex: none; }
.hpm-predict-design strong { color: var(--hpm-ink); }
.hpm-scatter .pi-band { stroke: var(--hpm-blue-deep); stroke-width: 3.5; stroke-linecap: round; opacity: 0.9; }
.hpm-scatter .pi-cap { stroke: var(--hpm-blue-deep); stroke-width: 2.5; stroke-linecap: round; opacity: 0.9; }
.hpm-scatter .pi-mid { fill: var(--hpm-blue-deep); stroke: #fff; stroke-width: 2; }

/* ---- step 3: data-explorer style scatter (matches the docs chart) ---- */
.hpm-scatter .fit-line { stroke: #2b7fce; stroke-width: 2.5; fill: none; }
.hpm-scatter .pi-fill { fill: rgba(43, 127, 206, 0.1); }
.hpm-scatter .pi-edge { stroke: #9cc0e4; stroke-width: 1.5; stroke-dasharray: 6 5; fill: none; }
/* Viridis dots take their colour from currentColor so the hover halo and
   glow (matching the stage 1 chart) follow each dot's own colour */
.hpm-scatter .dot-v {
    fill: currentColor;
    transition: stroke 0.12s ease, stroke-width 0.12s ease, filter 0.12s ease;
}
.hpm-scatter .dot-v:hover {
    stroke: currentColor;
    stroke-opacity: 0.35;
    stroke-width: 5;
    filter: drop-shadow(0 0 4px currentColor);
}
.hpm-explorer-bar { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 0.85rem; }
.hpm-pill {
    display: inline-flex;
    align-items: stretch;
    border: 1px solid var(--hpm-line);
    border-radius: 0.55rem;
    overflow: hidden;
    font-size: 0.875rem;
    background: var(--hpm-card);
    white-space: nowrap;
}
.hpm-pill .k {
    padding: 0.3rem 0.7rem;
    background: var(--hpm-sky);
    color: var(--hpm-ink);
    font-weight: 600;
    border-right: 1px solid var(--hpm-line);
}
.hpm-pill .v { padding: 0.3rem 0.7rem; color: var(--hpm-ink-soft); }
.hpm-explorer-stats {
    font-size: 0.9375rem;
    color: var(--hpm-ink-soft);
    margin-bottom: 1rem;
    font-variant-numeric: tabular-nums;
}
.hpm-viridis-bar {
    display: inline-block;
    width: 7rem;
    height: 0.6rem;
    border-radius: 0.3rem;
    background: linear-gradient(90deg, #440154, #482878, #3e4989, #31688e, #26828e, #1f9e89, #35b779, #6ece58, #b5de2b, #fde725);
}

.hpm-dft-mean-stat {
    display: flex;
    align-items: baseline;
    gap: 0.75rem;
    margin: 0.5rem 0 0.25rem;
}
.hpm-dft-mean-stat .val {
    font-size: 2.6rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--hpm-teal);
    line-height: 1.1;
}
.hpm-dft-mean-stat .lab { font-size: 0.9375rem; color: var(--hpm-muted); }
.hpm-finder-footnote {
    margin-top: 1.25rem;
    font-size: 0.875rem;
    color: var(--hpm-muted);
    max-width: 75ch;
}

/* ---- chart tooltip (same look as the economics info tipbox) ---- */
.hpm-chart-tip {
    position: fixed;
    z-index: 60;
    width: max-content;
    max-width: 19rem;
    padding: 0.6rem 0.8rem;
    background: var(--hpm-ink);
    color: #fff;
    font-size: 0.8125rem;
    font-weight: 500;
    line-height: 1.45;
    border-radius: 0.6rem;
    box-shadow: 0 10px 28px rgba(20, 52, 74, 0.3);
    pointer-events: none;
    transform: translate(-50%, calc(-100% - 0.6rem));
    animation: hpm-tip-in 0.12s ease;
}
.hpm-chart-tip.below { transform: translate(-50%, 0.6rem); }
.hpm-chart-tip .t { font-weight: 700; }
.hpm-chart-tip .s { font-size: 0.75rem; color: rgba(255, 255, 255, 0.7); }
.hpm-chart-tip .stats {
    display: flex;
    flex-wrap: wrap;
    gap: 0.1rem 0.9rem;
    margin-top: 0.45rem;
    padding-top: 0.45rem;
    border-top: 1px solid rgba(255, 255, 255, 0.18);
}
.hpm-chart-tip .stats > span { white-space: nowrap; }
.hpm-chart-tip .stats .k { color: rgba(255, 255, 255, 0.6); margin-right: 0.35rem; }
.hpm-chart-tip .stats .v { font-weight: 700; font-variant-numeric: tabular-nums; }
@keyframes hpm-tip-in { from { opacity: 0; } to { opacity: 1; } }

@media (max-width: 767.98px) {
    .hpm-section { padding: 3rem 0; }
    .hpm-steps { flex-direction: column; }
    .hpm-step-nav { flex-wrap: wrap; }
}

@media (prefers-reduced-motion: reduce) {
    .hpm-home * { transition: none !important; animation: none !important; }
}

/* Hide the app until Vue has compiled the template, so raw {{ }} bindings
   never flash on screen. Vue removes v-cloak on mount. */
[v-cloak] { display: none !important; }
</style>

<script src="<?php echo $path; ?>theme/vendor/vue-2.7.16/vue.min.js" integrity="sha384-YVYXhPGIH/Gmcr0W5Rin4PcpcsG1a4pcdUUod1CnbDEJut7XiUaJtSlNKeRLJBPk"></script>
<div class="hpm-home" id="flowtemp" v-cloak>

    <!-- Shared chart tooltip: anchored to the hovered dot, styled to match
         the economics info tipbox (dark ink card, white text) -->
    <div class="hpm-chart-tip" v-if="tip" :class="{below: tip.below}"
         :style="{left: tip.x + 'px', top: tip.y + 'px'}" aria-hidden="true">
        <div class="t">{{ tip.title }}</div>
        <div class="s" v-if="tip.sub">{{ tip.sub }}</div>
        <div class="stats" v-if="tip.stats.length">
            <span v-for="st in tip.stats"><span class="k">{{ st.k }}</span><span class="v">{{ st.v }}</span></span>
        </div>
    </div>

    <!-- ============ What performance can I expect? ============ -->
    <!-- Three-step walkthrough: design flow temperature (step 1, weak
         correlation) → measured coldest-day flow temperature (step 2, better,
         with a live SPF predictor) → weighted flowT minus outsideT (step 3,
         the strongest correlation, styled after the docs data explorer).
         Uses the same home/eligible_systems dataset as the home page.
         Background: community.openenergymonitor.org/t/29547,
         docs.openenergymonitor.org/heatpumpmonitor/low_temperature.html and
         github.com/openenergymonitor/heatpumpmonitor.org/tree/main/analysis/performance_prediction -->

    <section class="hpm-section hpm-section-sky">
        <div class="container">
            <div class="hpm-eyebrow"><span class="hpm-eyebrow-num">04</span> Predicting performance</div>
            <h2 class="hpm-display mb-3"><span class="hpm-accent">Flow temperature</span> vs performance</h2>
            <p class="hpm-lead mb-4">
                Add description here..
            </p>

            <template v-if="!finderLoading && !finderError">

                <div class="hpm-steps" role="group" aria-label="Three steps from design temperature to the measured temperature lift">
                    <button v-for="s in corrSteps" :key="s.stage" type="button"
                            :class="['hpm-step', {active: corrStage === s.stage}]"
                            :aria-current="corrStage === s.stage ? 'step' : false"
                            @click="corrStage = s.stage">
                        <span class="num">{{ s.stage }}</span>
                        <span class="txt">
                            <span class="t">{{ s.title }}</span>
                            <span class="s">{{ s.sub }}</span>
                        </span>
                        <span class="r2">R&sup2; {{ corrFits[s.key].r2.toFixed(2) }}</span>
                    </button>
                </div>

                <!-- ---- Step 1 · the design sheet ---- -->
                <template v-if="corrStage === 1">
                    <p class="hpm-stage-lead">
                        <strong>Step 1 &mdash; the design sheet.</strong> Group systems by their design flow
                        temperature and the means do fall gently as the design gets hotter &mdash; but the
                        spread within every group dwarfs the difference between the groups. On its own, the
                        design temperature explains very little of the performance a system will actually
                        achieve.
                    </p>
                    <div class="row g-4">
                        <div class="col-lg-7 d-flex">
                            <div class="hpm-chart-card flex-grow-1">
                                <h3>Design temperature vs Performance</h3>
                                <div class="hpm-chart-sub">Each dot is one system's measured SPF over the last 365 days &middot; click a row to select it &middot; click a dot to open that system</div>
                                <svg class="hpm-dft" viewBox="0 0 680 304" role="img"
                                     aria-label="Five dot strips of measured SPF, one per design flow temperature from 35 to 55 degrees. Group means fall gently from about 4.1 at 35 degrees design to about 3.6 at 55 degrees, while the spread within every group is far wider than the difference between them.">
                                    <g>
                                        <line v-for="t in dftTicks" class="grid" :x1="dftX(t)" y1="8" :x2="dftX(t)" y2="268"></line>
                                        <text v-for="t in dftTicks" class="axis-label" :x="dftX(t)" y="290" text-anchor="middle">{{ t.toFixed(1) }}</text>
                                        <text class="axis-label" x="338" y="303" text-anchor="middle">Measured SPF, space heating &amp; hot water combined</text>
                                    </g>
                                    <g v-for="row in dftRows">
                                        <rect :class="['row-bg', {selected: row.t===designTemp}]"
                                              x="2" :y="row.top" width="676" height="50" rx="10"
                                              @click="designTemp=row.t"></rect>
                                        <text class="row-label" x="12" :y="row.center - 1" @click="designTemp=row.t">{{ row.t }}&deg;C</text>
                                        <text class="row-count" x="12" :y="row.center + 15" @click="designTemp=row.t">{{ row.n }} system{{ row.n===1 ? '' : 's' }}</text>
                                        <line v-if="row.n" class="mean-tick" :x1="row.meanX" :y1="row.center - 17" :x2="row.meanX" :y2="row.center + 17"></line>
                                        <circle v-for="d in row.dots" :class="[row.t===designTemp ? 'dot-sel' : 'dot-dim', {nm: d.nm}]"
                                                :cx="d.x" :cy="d.y" :r="d.r" @click.stop="openSystem(d.id)"
                                                @mouseenter="showTip($event, d.tip)" @mouseleave="hideTip"></circle>
                                        <text v-if="row.n" class="row-mean" x="674" :y="row.center + 5" text-anchor="end"><tspan class="lab">mean </tspan>{{ row.mean.toFixed(2) }}</text>
                                    </g>
                                </svg>
                                <div class="hpm-legend">
                                    <span><span class="hpm-swatch" style="background:#2a9d8f;"></span> Selected group</span>
                                    <span><span class="hpm-swatch" style="background:#c97716;"></span> No coldest-day flow temp data</span>
                                    <span><span class="hpm-swatch" style="background:#aac4d4;"></span> Other design temperatures</span>
                                    <span><span class="hpm-swatch" style="background:#14344a; width:0.2rem; border-radius:2px;"></span> Group mean</span>
                                </div>
                                <div class="hpm-r2-line">
                                    <strong>R&sup2; {{ corrFits.design.r2.toFixed(2) }}</strong> &mdash; the design flow
                                    temperature explains only {{ Math.round(corrFits.design.r2 * 100) }}% of the spread in
                                    measured performance across the {{ corrFits.design.n }} air source systems that specify one.
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5 d-flex">
                            <div class="hpm-chart-card flex-grow-1" v-if="dftSel.n">
                                <h3>Designed for {{ designTemp }}&deg;C</h3>
                                <div class="hpm-chart-sub">{{ dftSel.n }} system{{ dftSel.n===1 ? '' : 's' }} &middot; performance range {{ dftSel.lo.toFixed(1) }}&ndash;{{ dftSel.hi.toFixed(1) }}<template v-if="dftSel.n < 8"> &middot; early days, treat with caution</template></div>
                                <div class="hpm-dft-mean-stat">
                                    <span class="val">{{ dftSel.mean.toFixed(2) }}</span>
                                    <span class="lab">mean SPF &mdash; heat delivered per unit of electricity</span>
                                </div>
                                <template v-if="dftSel.actual !== null">
                                    <svg class="hpm-thermo" viewBox="0 0 420 96" role="img"
                                         :aria-label="'Temperature scale showing the design flow temperature of ' + designTemp + ' degrees against the measured coldest-day average of about ' + dftSel.actual.toFixed(1) + ' degrees.'">
                                        <line class="track" x1="20" y1="52" x2="400" y2="52"></line>
                                        <g v-for="t in [30, 35, 40, 45, 50, 55]">
                                            <line class="grid" :x1="thermoX(t)" y1="48" :x2="thermoX(t)" y2="56" stroke="#d9e8f1" stroke-width="1.5"></line>
                                            <text class="tick-label" :x="thermoX(t)" y="74" text-anchor="middle">{{ t }}&deg;</text>
                                        </g>
                                        <line v-if="dftGap > 0.5" class="gap-arrow" :x1="thermoX(designTemp)" y1="52" :x2="thermoX(dftSel.actual) + 8" y2="52"></line>
                                        <polygon v-if="dftGap > 0.5" class="gap-head" :points="(thermoX(dftSel.actual)+8) + ',48 ' + (thermoX(dftSel.actual)+8) + ',56 ' + (thermoX(dftSel.actual)+1) + ',52'"></polygon>
                                        <line class="design-marker" :x1="thermoX(designTemp)" y1="40" :x2="thermoX(designTemp)" y2="64"></line>
                                        <text class="marker-label" :x="thermoX(designTemp)" y="30"
                                              :text-anchor="thermoX(designTemp) > 340 ? 'end' : 'middle'">designed {{ designTemp }}&deg;C</text>
                                        <circle class="actual-marker" :cx="thermoX(dftSel.actual)" cy="52" r="6.5"></circle>
                                        <text class="marker-label" :x="thermoX(dftSel.actual)" y="93"
                                              :text-anchor="thermoX(dftSel.actual) < 80 ? 'start' : 'middle'" style="fill:#1e7a6f;">ran at &approx;{{ dftSel.actual.toFixed(1) }}&deg;C</text>
                                    </svg>
                                    <p class="mb-0" style="font-size:0.9375rem;">
                                        On their coldest days, these systems delivered most of their heat at a weighted
                                        average of <strong>&approx;{{ dftSel.actual.toFixed(1) }}&deg;C</strong>
                                        <template v-if="dftGap > 0.5"> &mdash; <strong>{{ dftGap.toFixed(1) }}&deg;C below design</strong></template>
                                        <template v-else> &mdash; right on design</template>
                                        (measured on {{ dftSel.nActual }} of {{ dftSel.n }} systems).
                                    </p>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="hpm-note-card mt-4">
                        <div class="hpm-note-eyebrow">Why cooler than designed?</div>
                        <p class="mb-2">
                            Heat-loss calculations have historically over-estimated heat loss due to factors such as air change rate assumptions.
                            An apparent 7.5 kW heat loss using the old air-change rate assumptions might be closer to 4.0 kW in reality and consequently radiators sized for 50C at 7.5 kW can actually run at 40C to meet the real heat loss.
                            Gains from people and appliances and the way thermal mass helps homes ride through brief dips in outside temperature are also not taken into account in the design calculations and can further reduce flow temperature requirements by several degrees.
                        </p>
                    </div>
                </template>

                <!-- ---- Step 2 · measured coldest-day running temperatures ---- -->
                <template v-else-if="corrStage === 2">
                    <p class="hpm-stage-lead">
                        <strong>Step 2: Measured flow temperature on the coldest day.</strong> Swap the design assumption
                        for what each system actually did: the weighted mean flow temperature on its coldest
                        day of the last year. It&rsquo;s the closest measured equivalent to the design
                        condition, and the correlation strengthens considerably. Enter a coldest-day flow
                        temperature on the right to see the performance the fleet suggests.
                    </p>
                    <div class="row g-4">
                        <div class="col-lg-7 d-flex">
                            <div class="hpm-chart-card flex-grow-1">
                                <h3>{{ perfMetricDef.title }}</h3>
                                <div class="hpm-chart-sub">{{ perfMetricDef.sub }} &middot; each dot is one system</div>
                                <svg class="hpm-scatter" viewBox="0 0 680 386" role="img"
                                     :aria-label="'Scatter plot of measured SPF against ' + perfMetricDef.axis + ' for ' + perfFit.n + ' systems. SPF falls steadily as the temperature rises, with a dashed best-fit line showing roughly ' + Math.abs(perfFit.slope).toFixed(2) + ' SPF lost per degree.'">
                                    <g>
                                        <line v-for="t in perfYTicks" class="grid" x1="70" :y1="perfY(t)" x2="662" :y2="perfY(t)"></line>
                                        <text v-for="t in perfYTicks" class="axis-label" x="58" :y="perfY(t) + 4" text-anchor="end">{{ t }}</text>
                                        <text class="axis-title" x="24" y="173" transform="rotate(-90 24 173)" text-anchor="middle">SPF</text>
                                    </g>
                                    <g>
                                        <text v-for="t in perfXTicks" class="axis-label" :x="perfX(t)" y="352" text-anchor="middle">{{ t }}</text>
                                        <text class="axis-title" x="366" y="380" text-anchor="middle">{{ perfMetricDef.axis }}</text>
                                    </g>
                                    <line class="trend" :x1="perfTrend.x1" :y1="perfTrend.y1" :x2="perfTrend.x2" :y2="perfTrend.y2"></line>
                                    <circle v-for="d in perfDots" class="dot-a" :cx="d.x" :cy="d.y" r="4.5" @click="openSystem(d.id)"
                                            @mouseenter="showTip($event, d.tip)" @mouseleave="hideTip"></circle>
                                    <g v-if="prediction">
                                        <line class="pi-band" :x1="perfX(predictFlowT)" :y1="perfY(prediction.lo)" :x2="perfX(predictFlowT)" :y2="perfY(prediction.hi)"></line>
                                        <line class="pi-cap" :x1="perfX(predictFlowT) - 7" :y1="perfY(prediction.lo)" :x2="perfX(predictFlowT) + 7" :y2="perfY(prediction.lo)"></line>
                                        <line class="pi-cap" :x1="perfX(predictFlowT) - 7" :y1="perfY(prediction.hi)" :x2="perfX(predictFlowT) + 7" :y2="perfY(prediction.hi)"></line>
                                        <circle class="pi-mid" :cx="perfX(predictFlowT)" :cy="perfY(prediction.mid)" r="6.5"></circle>
                                    </g>
                                    <text class="axis-title" x="655" y="32" text-anchor="end">&minus;{{ Math.abs(perfFit.slope).toFixed(2) }} SPF per {{ perfMetricDef.unit === 'K' ? 'K' : '&deg;C' }}</text>
                                    <text class="axis-label" x="655" y="50" text-anchor="end">R&sup2; {{ perfFit.r2.toFixed(2) }} &middot; {{ perfFit.n }} systems</text>
                                </svg>
                                <div class="hpm-legend" v-if="prediction">
                                    <span><span class="hpm-swatch" style="background:#2187ba;"></span> 90% prediction interval</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5 d-flex flex-column gap-4">
                            <div class="hpm-chart-card flex-grow-1">
                                <h3>What SPF should I expect?</h3>
                                <div class="hpm-chart-sub">Live best fit across {{ corrFits.coldest.n }} air source systems</div>
                                <label class="hpm-predict-label" for="predict-flow">Weighted mean flow temperature on the coldest day</label>
                                <div class="hpm-predict-input">
                                    <input type="range" id="predict-flow" min="30" max="55" step="0.5" v-model.number="predictFlowT">
                                    <input type="number" min="30" max="55" step="0.5" v-model.number="predictFlowT"
                                           aria-label="Coldest-day flow temperature in degrees Celsius">
                                    <span class="unit">&deg;C</span>
                                </div>
                                <p class="hpm-predict-design" v-if="designEquiv !== null && designEquiv - predictFlowT >= 1">
                                    <i class="bi bi-thermometer-half" aria-hidden="true"></i>
                                    <span>
                                        Optimised systems often run below their design flow temperatures (step&nbsp;1): a coldest-day flow
                                        <em>measured</em> at &approx;{{ predictFlowT }}&deg;C could correspond to a design figure of
                                        <strong><template>around {{ Math.round(designEquiv) }}&deg;C</template></strong> - with large error bars!
                                    </span>
                                </p>
                                <template v-if="prediction">
                                    <div class="hpm-dft-mean-stat">
                                        <span class="val">{{ prediction.mid.toFixed(2) }}</span>
                                        <span class="lab">predicted SPF &mdash; heat delivered per unit of electricity</span>
                                    </div>
                                    <div class="hpm-predict-range">
                                        {{ prediction.lo.toFixed(2) }} &ndash; {{ prediction.hi.toFixed(2) }}
                                        <span class="lab">90% prediction interval</span>
                                    </div>
                                    <p class="mb-0" style="font-size:0.9375rem;">
                                        Nine in ten systems running at &approx;{{ predictFlowT }}&deg;C on their coldest
                                        day would be expected to land in this range.
                                    </p>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ---- Step 3 · weighted flowT minus outsideT ---- -->
                <template v-else-if="corrStage === 3">
                    <p class="hpm-stage-lead">
                        <strong>Step 3 &mdash; the temperature the heat pump works across.</strong> Flow
                        temperature is only half the story: a heat pump&rsquo;s job is to lift heat from the
                        outside air up to the flow temperature, and it is that lift &mdash; flow minus outside
                        temperature, averaged over the whole year and weighted by the heat delivered at each
                        moment &mdash; that thermodynamics says should set the efficiency. Sure enough, it is
                        the strongest correlation of the three.
                    </p>
                    <div class="hpm-chart-card">
                        <h3>SPF vs weighted flowT &minus; outsideT</h3>
                        <div class="hpm-chart-sub">Averaged over the last 365 days, weighted by heat output &middot; each dot is one system, coloured by % of the theoretical Carnot maximum &middot; click a dot to open that system</div>
                        <div class="hpm-explorer-bar" aria-hidden="true">
                            <span class="hpm-pill"><span class="k">X-axis</span><span class="v">Weighted flowT - outsideT</span></span>
                            <span class="hpm-pill"><span class="k">Y-axis</span><span class="v">COP</span></span>
                            <span class="hpm-pill"><span class="k">Colour map</span><span class="v">Weighted % Carnot</span></span>
                            <span class="hpm-pill"><span class="k">Best fit</span><span class="v">Ordinary Least Squares (OLS)</span></span>
                        </div>
                        <div class="hpm-explorer-stats">
                            Standard - R: {{ liftFit.r.toFixed(3) }}, R&sup2;: {{ liftFit.r2.toFixed(3) }},
                            n={{ liftFit.n }}, (y={{ liftFit.slope.toFixed(4) }}x +
                            {{ liftFit.icpt.toFixed(4) }})<template v-if="liftPI">, 90% PI: &plusmn;{{ liftPI.toFixed(2) }}</template>
                        </div>
                        <svg class="hpm-scatter" viewBox="0 0 960 470" role="img"
                             :aria-label="'Scatter plot of measured SPF against the weighted average flow minus outside temperature for ' + liftFit.n + ' systems, with a best-fit line falling by about ' + Math.abs(liftFit.slope).toFixed(2) + ' SPF per kelvin inside a shaded 90% prediction band.'">
                            <g>
                                <line v-for="t in liftYTicks" class="grid" x1="70" :y1="liftY(t)" x2="930" :y2="liftY(t)"></line>
                                <text v-for="t in liftYTicks" class="axis-label" x="58" :y="liftY(t) + 4" text-anchor="end">{{ t % 1 ? t.toFixed(1) : t }}</text>
                                <text class="axis-title" x="22" y="210" transform="rotate(-90 22 210)" text-anchor="middle">Seasonal Performance Factor (SPF)</text>
                            </g>
                            <g>
                                <line v-for="t in liftXTicks" class="grid" :x1="liftX(t)" y1="20" :x2="liftX(t)" y2="400"></line>
                                <text v-for="t in liftXTicks" class="axis-label" :x="liftX(t)" y="424" text-anchor="middle">{{ t }}</text>
                                <text class="axis-title" x="500" y="454" text-anchor="middle">Weighted by heat output flowT - outsideT</text>
                            </g>
                            <g v-if="liftBand">
                                <polygon class="pi-fill" :points="liftBand.poly"></polygon>
                                <polyline class="pi-edge" :points="liftBand.top"></polyline>
                                <polyline class="pi-edge" :points="liftBand.bot"></polyline>
                            </g>
                            <line class="fit-line" :x1="liftTrend.x1" :y1="liftTrend.y1" :x2="liftTrend.x2" :y2="liftTrend.y2"></line>
                            <circle v-for="d in liftDots" class="dot-v" :cx="d.x" :cy="d.y" r="4" :style="{ color: d.color }" @click="openSystem(d.id)"
                                    @mouseenter="showTip($event, d.tip)" @mouseleave="hideTip"></circle>
                        </svg>
                        <div class="hpm-legend">
                            <span><span class="hpm-viridis-bar"></span> Weighted by heat % Carnot &mdash; how close each system runs to the theoretical maximum (yellow = closer)</span>
                            <span><span class="hpm-swatch" style="background:rgba(43,127,206,0.25); border:1px dashed #9cc0e4; border-radius:3px;"></span> 90% prediction interval</span>
                        </div>
                        <div class="hpm-r2-line">
                            <strong>R&sup2; {{ liftFit.r2.toFixed(2) }}</strong> &mdash; the strongest of the three:
                            the weighted temperature lift explains {{ Math.round(liftFit.r2 * 100) }}% of the spread
                            in measured performance across {{ liftFit.n }} air source systems.
                        </div>
                    </div>
                </template>

                <div class="hpm-step-nav">
                    <button type="button" class="hpm-btn hpm-btn-secondary" v-if="corrStage > 1" @click="corrStage--">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button type="button" class="hpm-btn hpm-btn-primary" v-if="corrStage < 3" @click="corrStage++">
                        {{ corrStage === 1 ? "Next: what systems actually ran at" : "Next: the metric that predicts best" }} <i class="bi bi-arrow-right"></i>
                    </button>
                </div>

                <p class="hpm-finder-footnote" v-if="corrStage === 2">
                    This chart shows air source systems only, and excludes systems that provide active
                    cooling &mdash; cooling energy skews the heat-weighted averages.
                    Weighted averages weight each temperature reading by the heat delivered at it, so they
                    reflect the conditions most heat was produced under.
                    Live best fit: SPF = {{ perfFit.slope.toFixed(3) }} &times; x +
                    {{ perfFit.icpt.toFixed(2) }} across {{ perfFit.n }} systems &mdash; the exact figures
                    drift a little as systems join, but the slope has stayed near 0.1 per degree for years.
                </p>

                <p class="hpm-finder-footnote" v-if="corrStage === 3">
                    Air source systems only, excluding systems that provide active cooling.
                    Weighted averages weight each temperature reading by the heat delivered at it, so they
                    reflect the conditions most heat was produced under. Subtracting the outside temperature
                    gives the lift the heat pump has to work across &mdash; the quantity the Carnot limit says
                    drives efficiency, which is why it correlates best. This reproduces the
                    <a href="https://docs.openenergymonitor.org/heatpumpmonitor/low_temperature.html#weighted-by-heat-output-average-flow-minus-outside-temperature" target="_blank" rel="noopener">analysis in our documentation</a>
                    with live data.
                </p>

            </template>

            <div v-else class="hpm-finder-empty">
                <p class="mb-0">{{ finderError ? "Couldn’t load live system data right now — please try again shortly." : "Loading live system data…" }}</p>
            </div>
        </div>
    </section>

</div>

<script>

    var path = "<?php echo $path; ?>";

    // Eligible systems are embedded server-side rather than fetched over
    // ajax, for a faster initial load
    var eligible_systems = <?php echo json_encode($eligible_systems); ?>;

    var app = new Vue({
        el: '#flowtemp',
        data: {
            path: path,
            homes: [],
            finderLoading: true,
            finderError: false,

            // Shared chart tooltip: set by showTip on dot hover, null when hidden
            tip: null,

            // Correlation walkthrough: design temp → coldest-day temp → model
            corrStage: 1,
            corrSteps: [
                { stage: 1, key: "design", title: "Design flow temperature", sub: "The number on the design sheet" },
                { stage: 2, key: "coldest", title: "Coldest-day flow temperature", sub: "Measured at the design condition" },
                { stage: 3, key: "lift", title: "Flow minus outside temperature", sub: "The lift, weighted by heat output" }
            ],
            // Coldest-day flow temperature driving the step 2 SPF predictor
            predictFlowT: 45,

            // Design flow temperature explorer (step 1)
            designTemp: 50,
            designTemps: [35, 40, 45, 50, 55],

            // Scatter metric definition for step 2
            perfMetrics: [
                {
                    key: "coldest", name: "Coldest day", unit: "°C",
                    title: "SPF vs coldest-day flow temperature",
                    sub: "Weighted mean flow temperature on each system's coldest day — the closest measure to the design condition",
                    axis: "Weighted mean flow temperature on the coldest day (°C)"
                }
            ]
        },
        mounted: function() {
            this.homes = eligible_systems.map(function(s) {
                return {
                    id: s.id,
                    location: s.location || "",
                    manufacturer: s.hp_manufacturer || "",
                    model: s.hp_model || "",
                    capacity: s.hp_output,
                    floor: s.floor_area,
                    hp: s.hp_type,
                    design: s.flow_temp,
                    outsideDesign: s.design_temp,
                    measured: s.measured_flow_temp,
                    outsideColdest: s.measured_outside_temp_coldest_day,
                    flowT: s.weighted_flowT,
                    lift: s.weighted_flowT_minus_outsideT,
                    carnot: s.weighted_prc_carnot,
                    cooling: s.cooling_heat_kwh,
                    cop: s.combined_cop
                };
            });
            this.finderLoading = false;
        },
        computed: {
            // ---- Correlation walkthrough: SPF vs running temperature ----
            perfMetric: function() {
                return "coldest";
            },
            perfMetricDef: function() {
                var key = this.perfMetric;
                return this.perfMetrics.filter(function(m) { return m.key === key; })[0];
            },
            // Air source only, excluding systems that provide active cooling —
            // cooling energy skews the heat-weighted flow temperature averages
            perfHomes: function() {
                return this.homes.filter(function(h) {
                    return h.hp === "Air Source" && (h.cooling === null || h.cooling < 1);
                });
            },
            // One fit per measured step over the same population, so the
            // stepper's R² values are directly comparable
            corrFits: function() {
                var fit = this.linfit;
                return {
                    design: fit(this.perfHomes
                        .filter(function(h) { return h.design !== null && h.design > 20; })
                        .map(function(h) { return { x: h.design, y: h.cop }; })),
                    coldest: fit(this.metricPoints("coldest")),
                    lift: fit(this.metricPoints("lift"))
                };
            },
            perfPoints: function() {
                return this.metricPoints(this.perfMetric);
            },
            perfFit: function() {
                return this.corrFits[this.perfMetric];
            },
            // Step 2 predictor: SPF at the chosen coldest-day flow temperature,
            // with a 90% prediction interval for a single new system
            prediction: function() {
                var f = this.corrFits.coldest;
                var x = this.predictFlowT;
                if (f.n < 10 || typeof x !== "number" || !isFinite(x)) return null;
                var mid = f.slope * x + f.icpt;
                // two-sided 90% Student-t quantile, series approximation in z
                var z = 1.6449;
                var t = z + (z * z * z + z) / (4 * (f.n - 2));
                var half = t * f.se * Math.sqrt(1 + 1 / f.n + Math.pow(x - f.mx, 2) / f.sxx);
                return { mid: mid, lo: mid - half, hi: mid + half };
            },
            // Rough design-sheet equivalent of the step 2 slider, extrapolated
            // from step 1's group means: fit design temperature against each
            // group's mean coldest-day ("ran at") flow temperature. A
            // per-system regression won't do here — at R² ≈ 0.2 it collapses
            // toward the fleet mean and understates the design gap badly.
            designEquivFit: function() {
                var stats = this.dftStats;
                var pts = [];
                this.designTemps.forEach(function(t) {
                    var s = stats[t];
                    if (s.actual !== null && s.nActual >= 3) pts.push({ x: s.actual, y: t });
                });
                if (pts.length < 3) return null;
                var f = this.linfit(pts);
                return f.slope > 0.5 ? f : null; // needs a clearly rising trend
            },
            designEquiv: function() {
                var f = this.designEquivFit;
                var x = this.predictFlowT;
                if (!f || typeof x !== "number" || !isFinite(x)) return null;
                return f.slope * x + f.icpt;
            },
            perfXDomain: function() {
                var pts = this.perfPoints;
                if (!pts.length) return { lo: 25, hi: 55 };
                var xs = pts.map(function(p) { return p.x; });
                var lo = Math.floor(Math.min.apply(null, xs) / 5) * 5;
                var hi = Math.ceil(Math.max.apply(null, xs) / 5) * 5;
                if (hi - lo < 10) hi = lo + 10;
                return { lo: lo, hi: hi };
            },
            perfXTicks: function() {
                var ticks = [];
                for (var t = this.perfXDomain.lo; t <= this.perfXDomain.hi + 1e-9; t += 5) ticks.push(t);
                return ticks;
            },
            perfYDomain: function() {
                var pts = this.perfPoints;
                if (!pts.length) return { lo: 2, hi: 6 };
                var ys = pts.map(function(p) { return p.y; });
                var lo = Math.floor(Math.min.apply(null, ys) * 2) / 2;
                var hi = Math.ceil(Math.max.apply(null, ys) * 2) / 2;
                if (hi - lo < 1) hi = lo + 1;
                return { lo: lo, hi: hi };
            },
            perfYTicks: function() {
                var ticks = [];
                for (var t = Math.ceil(this.perfYDomain.lo); t <= this.perfYDomain.hi + 1e-9; t += 1) ticks.push(t);
                return ticks;
            },
            perfDots: function() {
                var self = this;
                var subtitle = this.homeSubtitle;
                var def = this.perfMetricDef;
                return this.perfPoints.map(function(p) {
                    var stats = [
                        { k: "SPF", v: p.y.toFixed(1) }
                    ];
                    // "designed …, ran at …" pairs as in the stage 1 tooltip,
                    // for the flow temperature and the outside temperature
                    var flow = "ran at " + p.x.toFixed(1) + " °C";
                    if (p.h.design !== null) flow = "designed " + p.h.design + " °C, " + flow;
                    stats.push({ k: "Flow", v: flow });
                    var outside = [];
                    if (p.h.outsideDesign !== null) outside.push("designed " + p.h.outsideDesign + " °C");
                    if (p.h.outsideColdest !== null) outside.push("coldest day " + p.h.outsideColdest.toFixed(1) + " °C");
                    if (outside.length) stats.push({ k: "Outside", v: outside.join(", ") });
                    return {
                        id: p.h.id,
                        x: self.perfX(p.x),
                        y: self.perfY(p.y),
                        tip: {
                            title: p.h.location || "System " + p.h.id,
                            sub: subtitle(p.h),
                            stats: stats
                        }
                    };
                });
            },
            // Best-fit line drawn across the span of the data
            perfTrend: function() {
                var pts = this.perfPoints;
                var fit = this.perfFit;
                if (pts.length < 3) return { x1: 0, y1: 0, x2: 0, y2: 0 };
                var xs = pts.map(function(p) { return p.x; });
                var lo = Math.min.apply(null, xs);
                var hi = Math.max.apply(null, xs);
                var d = this.perfYDomain;
                var yAt = function(x) { return Math.min(Math.max(fit.slope * x + fit.icpt, d.lo), d.hi); };
                return { x1: this.perfX(lo), y1: this.perfY(yAt(lo)), x2: this.perfX(hi), y2: this.perfY(yAt(hi)) };
            },

            // ---- Step 3: SPF vs weighted flowT − outsideT, drawn in the same
            // style as the docs data explorer chart it reproduces ----
            liftPoints: function() {
                return this.metricPoints("lift");
            },
            liftFit: function() {
                return this.corrFits.lift;
            },
            // 90% PI half-width at the mean, matching the ± figure the data
            // explorer quotes (its band is x-dependent, see liftMargin)
            liftPI: function() {
                var f = this.corrFits.lift;
                return f.n < 10 ? null : this.liftMargin(f.mx);
            },
            // Hug the data: 1 K of padding each side rather than snapping the
            // axis out to multiples of 5
            liftXDomain: function() {
                var pts = this.liftPoints;
                if (!pts.length) return { lo: 20, hi: 40 };
                var xs = pts.map(function(p) { return p.x; });
                var lo = Math.floor(Math.min.apply(null, xs) - 1);
                var hi = Math.ceil(Math.max.apply(null, xs) + 1);
                if (hi - lo < 10) hi = lo + 10;
                return { lo: lo, hi: hi };
            },
            liftXTicks: function() {
                var d = this.liftXDomain;
                var ticks = [];
                for (var t = Math.ceil(d.lo / 5) * 5; t <= d.hi + 1e-9; t += 5) ticks.push(t);
                return ticks;
            },
            // Y axis stretched to hold the prediction band as well as the dots
            liftYDomain: function() {
                var pts = this.liftPoints;
                if (!pts.length) return { lo: 2, hi: 6 };
                var ys = pts.map(function(p) { return p.y; });
                var self = this;
                var f = this.corrFits.lift;
                if (this.liftPI) {
                    // The band is widest at the axis edges, where it now ends
                    [this.liftXDomain.lo, this.liftXDomain.hi].forEach(function(x) {
                        var m = self.liftMargin(x);
                        ys = ys.concat([f.slope * x + f.icpt + m, f.slope * x + f.icpt - m]);
                    });
                }
                var lo = Math.floor(Math.min.apply(null, ys) * 2) / 2;
                var hi = Math.ceil(Math.max.apply(null, ys) * 2) / 2;
                if (hi - lo < 1) hi = lo + 1;
                return { lo: lo, hi: hi };
            },
            liftYTicks: function() {
                var ticks = [];
                for (var t = this.liftYDomain.lo; t <= this.liftYDomain.hi + 1e-9; t += 0.5) ticks.push(t);
                return ticks;
            },
            // Dots coloured viridis by the weighted % Carnot stat, as the data
            // explorer colours them; systems without the stat fall back to an
            // estimate from the weighted averages
            liftDots: function() {
                var self = this;
                var subtitle = this.homeSubtitle;
                var pts = this.liftPoints;
                var prcs = pts.map(function(p) {
                    if (p.h.carnot !== null && p.h.carnot > 0) return p.h.carnot;
                    var flowT = p.h.flowT;
                    return (flowT !== null && flowT > 5) ? 100 * p.y * p.x / (flowT + 273.15) : null;
                });
                var known = prcs.filter(function(v) { return v !== null; });
                var lo = known.length ? Math.min.apply(null, known) : 0;
                var hi = known.length ? Math.max.apply(null, known) : 1;
                return pts.map(function(p, i) {
                    var f = (prcs[i] === null || hi - lo < 1e-9) ? 0.5 : (prcs[i] - lo) / (hi - lo);
                    var stats = [
                        { k: "SPF", v: p.y.toFixed(1) },
                        { k: "Lift", v: p.x.toFixed(1) + " K" }
                    ];
                    if (prcs[i] !== null) stats.push({ k: "Carnot", v: prcs[i].toFixed(1) + "%" });
                    return {
                        id: p.h.id,
                        x: self.liftX(p.x),
                        y: self.liftY(p.y),
                        color: self.viridis(f),
                        tip: {
                            title: p.h.location || "System " + p.h.id,
                            sub: subtitle(p.h),
                            stats: stats
                        }
                    };
                });
            },
            // Fit line drawn edge to edge across the axis domain
            liftTrend: function() {
                var pts = this.liftPoints;
                var f = this.corrFits.lift;
                if (pts.length < 3) return { x1: 0, y1: 0, x2: 0, y2: 0 };
                var lo = this.liftXDomain.lo;
                var hi = this.liftXDomain.hi;
                return {
                    x1: this.liftX(lo), y1: this.liftY(f.slope * lo + f.icpt),
                    x2: this.liftX(hi), y2: this.liftY(f.slope * hi + f.icpt)
                };
            },
            // 90% prediction band, x-dependent as in the data explorer:
            // t · se · sqrt(1 + 1/n + (x − x̄)²/sxx) sampled at 51 points, so
            // it flares gently at the ends of the data range
            liftBand: function() {
                var pts = this.liftPoints;
                var f = this.corrFits.lift;
                if (!this.liftPI || pts.length < 3) return null;
                var lo = this.liftXDomain.lo;
                var hi = this.liftXDomain.hi;
                var top = [], bot = [];
                for (var i = 0; i <= 50; i++) {
                    var x = lo + (hi - lo) * i / 50;
                    var y = f.slope * x + f.icpt;
                    var m = this.liftMargin(x);
                    top.push(this.liftX(x) + "," + this.liftY(y + m));
                    bot.push(this.liftX(x) + "," + this.liftY(y - m));
                }
                return {
                    top: top.join(" "),
                    bot: bot.join(" "),
                    poly: top.concat(bot.slice().reverse()).join(" ")
                };
            },

            // ---- Design flow temperature explorer ----
            // Systems grouped by design flow temp, within 2°C of each label
            dftGroups: function() {
                var homes = this.homes;
                var groups = {};
                this.designTemps.forEach(function(t) {
                    groups[t] = homes.filter(function(h) {
                        return h.design !== null && Math.abs(h.design - t) <= 2;
                    });
                });
                return groups;
            },
            dftStats: function() {
                var stats = {};
                var groups = this.dftGroups;
                this.designTemps.forEach(function(t) {
                    var g = groups[t];
                    var cops = g.map(function(h) { return h.cop; });
                    var measured = g.map(function(h) { return h.measured; }).filter(function(m) {
                        return m !== null && m > 20; // ignore implausible entries
                    });
                    stats[t] = {
                        n: g.length,
                        mean: g.length ? cops.reduce(function(a, b) { return a + b; }, 0) / g.length : 0,
                        lo: g.length ? Math.min.apply(null, cops) : 0,
                        hi: g.length ? Math.max.apply(null, cops) : 0,
                        actual: measured.length ? measured.reduce(function(a, b) { return a + b; }, 0) / measured.length : null,
                        nActual: measured.length
                    };
                });
                return stats;
            },
            dftSel: function() { return this.dftStats[this.designTemp]; },
            dftGap: function() {
                var s = this.dftSel;
                return s.actual !== null ? this.designTemp - s.actual : 0;
            },
            // Shared SPF axis across all groups, snapped outwards to 0.5 steps
            dftDomain: function() {
                var all = [];
                var groups = this.dftGroups;
                this.designTemps.forEach(function(t) { all = all.concat(groups[t]); });
                if (!all.length) return { lo: 2, hi: 6 };
                var cops = all.map(function(h) { return h.cop; });
                var lo = Math.floor(Math.min.apply(null, cops) * 2) / 2;
                var hi = Math.ceil(Math.max.apply(null, cops) * 2) / 2;
                if (hi - lo < 1) hi = lo + 1;
                return { lo: lo, hi: hi };
            },
            dftTicks: function() {
                var ticks = [];
                for (var t = Math.ceil(this.dftDomain.lo); t <= this.dftDomain.hi + 1e-9; t += 1) {
                    ticks.push(t);
                }
                return ticks;
            },
            // One symmetric beeswarm row per design temperature
            dftRows: function() {
                var self = this;
                var subtitle = this.homeSubtitle;
                return this.designTemps.map(function(t, i) {
                    var top = 8 + i * 52;
                    var center = top + 25;
                    var g = self.dftGroups[t].slice().sort(function(a, b) { return a.cop - b.cop; });
                    var stats = self.dftStats[t];

                    var counts = {};
                    var max_stack = 1;
                    g.forEach(function(h) {
                        var bin = Math.round(h.cop * 10);
                        counts[bin] = (counts[bin] || 0) + 1;
                        if (counts[bin] > max_stack) max_stack = counts[bin];
                    });
                    var max_level = Math.ceil((max_stack - 1) / 2);
                    var dy = max_level ? Math.min(6.5, 18 / max_level) : 6.5;
                    var r = Math.min(4, Math.max(2.2, dy * 0.55));

                    var stacks = {};
                    var dots = g.map(function(h) {
                        var bin = Math.round(h.cop * 10);
                        var k = (stacks[bin] || 0);
                        stacks[bin] = k + 1;
                        var side = k % 2 ? 1 : -1;
                        var level = Math.ceil(k / 2);
                        var hasMeasured = h.measured !== null && h.measured > 20;
                        return {
                            id: h.id,
                            x: self.dftX(h.cop),
                            y: center + side * level * dy,
                            r: r,
                            nm: !hasMeasured,
                            tip: {
                                title: h.location || "System " + h.id,
                                sub: subtitle(h),
                                stats: [
                                    { k: "SPF", v: h.cop.toFixed(1) },
                                    { k: "Designed", v: h.design + " °C" },
                                    hasMeasured ? { k: "Ran at", v: h.measured.toFixed(1) + " °C" }
                                                : { k: "Coldest-day flow", v: "no data" }
                                ]
                            }
                        };
                    });
                    return { t: t, top: top, center: center, n: g.length, mean: stats.mean, meanX: self.dftX(stats.mean), dots: dots };
                });
            }
        },
        watch: {
            // The hovered dot can vanish without a mouseleave when the stage
            // or selected row changes, leaving the tooltip stranded
            corrStage: function() { this.tip = null; },
            designTemp: function() { this.tip = null; }
        },
        methods: {
            openSystem: function(id) { window.location = path + "system/view?id=" + id; },
            // Anchor the shared tooltip to the hovered dot, flipping below it
            // near the top of the viewport and clamping at the side edges
            showTip: function(evt, tip) {
                var r = evt.target.getBoundingClientRect();
                var below = r.top < 150;
                this.tip = {
                    title: tip.title,
                    sub: tip.sub,
                    stats: tip.stats,
                    below: below,
                    x: Math.min(Math.max(r.left + r.width / 2, 155), window.innerWidth - 155),
                    y: below ? r.bottom : r.top
                };
            },
            hideTip: function() { this.tip = null; },
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
            // SPF against one running-temperature metric, over the systems
            // that report it with a plausible value
            metricPoints: function(key) {
                return this.perfHomes.map(function(h) {
                    var v = key === "lift" ? h.lift : key === "flowT" ? h.flowT : h.measured;
                    return { h: h, x: v, y: h.cop };
                }).filter(function(p) { return p.x !== null && p.x > 5 && p.y > 0; });
            },
            // Least-squares fit with the pieces needed for prediction intervals
            linfit: function(pts) {
                var n = pts.length;
                if (n < 3) return { slope: 0, icpt: 0, r: 0, r2: 0, n: n, mx: 0, sxx: 1, se: 0 };
                var mx = 0, my = 0;
                pts.forEach(function(p) { mx += p.x; my += p.y; });
                mx /= n; my /= n;
                var sxx = 0, syy = 0, sxy = 0;
                pts.forEach(function(p) {
                    sxx += (p.x - mx) * (p.x - mx);
                    syy += (p.y - my) * (p.y - my);
                    sxy += (p.x - mx) * (p.y - my);
                });
                var slope = sxy / sxx;
                var r = sxy / Math.sqrt(sxx * syy);
                return {
                    slope: slope,
                    icpt: my - slope * mx,
                    r: r,
                    r2: r * r,
                    n: n,
                    mx: mx,
                    sxx: sxx,
                    se: Math.sqrt(Math.max(syy - slope * sxy, 0) / (n - 2))
                };
            },
            // Map metric / SPF onto the scatter's plot area (x 70–662, y 16–330)
            perfX: function(v) {
                var d = this.perfXDomain;
                var c = Math.min(Math.max(v, d.lo), d.hi);
                return 70 + (c - d.lo) / (d.hi - d.lo) * 592;
            },
            perfY: function(cop) {
                var d = this.perfYDomain;
                var c = Math.min(Math.max(cop, d.lo), d.hi);
                return 330 - (c - d.lo) / (d.hi - d.lo) * 314;
            },
            // 90% prediction-interval half-width at a given x, using the same
            // formula as the data explorer (t quantile via a series
            // approximation that matches jStat's exact value to <0.01 here)
            liftMargin: function(x) {
                var f = this.corrFits.lift;
                var z = 1.6449;
                var t = z + (z * z * z + z) / (4 * (f.n - 2));
                return t * f.se * Math.sqrt(1 + 1 / f.n + Math.pow(x - f.mx, 2) / f.sxx);
            },
            // Map lift / SPF onto the step 3 chart's plot area (x 70–930, y 20–400)
            liftX: function(v) {
                var d = this.liftXDomain;
                var c = Math.min(Math.max(v, d.lo), d.hi);
                return 70 + (c - d.lo) / (d.hi - d.lo) * 860;
            },
            liftY: function(cop) {
                var d = this.liftYDomain;
                var c = Math.min(Math.max(cop, d.lo), d.hi);
                return 400 - (c - d.lo) / (d.hi - d.lo) * 380;
            },
            // Plotly's 10-stop Viridis colorscale, as the data explorer uses
            viridis: function(t) {
                var stops = [
                    [68, 1, 84], [72, 40, 120], [62, 73, 137], [49, 104, 142], [38, 130, 142],
                    [31, 158, 137], [53, 183, 121], [110, 206, 88], [181, 222, 43], [253, 231, 37]
                ];
                var x = Math.min(Math.max(t, 0), 1) * (stops.length - 1);
                var i = Math.min(Math.floor(x), stops.length - 2);
                var f = x - i;
                var c = stops[i].map(function(v, k) { return Math.round(v + (stops[i + 1][k] - v) * f); });
                return "rgb(" + c.join(",") + ")";
            },
            // Map SPF onto the design-flow-temp chart's 90–586 plot width
            dftX: function(cop) {
                var d = this.dftDomain;
                var c = Math.min(Math.max(cop, d.lo), d.hi);
                return 90 + (c - d.lo) / (d.hi - d.lo) * 496;
            },
            // Map °C onto the thermometer's 20–400 track (28–58°C)
            thermoX: function(temp) {
                var c = Math.min(Math.max(temp, 28), 58);
                return 20 + (c - 28) / 30 * 380;
            }
        }
    });
</script>
