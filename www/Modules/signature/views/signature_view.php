<?php
defined('EMONCMS_EXEC') or die('Restricted access');
global $session, $path;

// Admin unlocks the admin system list; otherwise the public/user lists apply.
$admin = 0;
if (isset($session['admin']) && $session['admin']) {
    $admin = 1;
}

// System to open on load (?id=...); falls back to the first system in the list.
$systemid = 0;
if (isset($_GET['id'])) {
    $systemid = (int) $_GET['id'];
}
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>

<div>
    <div style="background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid" style="max-width:1100px">
            <h3>Heat pump signature explorer</h3>
        </div>
    </div>

    <div class="container-fluid" style="margin-top:20px; max-width:1100px">

        <!-- System selector: type-ahead over location / make / model / kW (Vue) -->
        <div id="app">
            <div class="row">
                <div class="col-lg-8">
                    <div class="input-group mb-3 position-relative">
                        <span class="input-group-text">Select system</span>
                        <input type="text" class="form-control" v-model="query"
                            placeholder="Type location, make/model or kW…"
                            @focus="openList($event)" @input="onInput" @keydown="onKey" @blur="onBlur">
                        <button class="btn btn-primary" @click="next_system(-1)">&lt;</button>
                        <button class="btn btn-primary" @click="next_system(1)">&gt;</button>

                        <!-- Filtered match list; mousedown (not click) so it fires before blur.
                             Highlighted item carries Bootstrap's .active list-group style. -->
                        <ul v-if="showList && filtered.length"
                            class="list-group position-absolute w-100 shadow-sm"
                            style="top:100%; z-index:1000; max-height:320px; overflow:auto">
                            <li v-for="(s,i) in filtered" :key="s.id"
                                class="list-group-item list-group-item-action py-2 text-truncate"
                                style="cursor:pointer"
                                :class="{active: i===highlight}"
                                @mousedown.prevent="select(s)"
                                @mouseenter="highlight=i">{{ label(s) }}</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4 d-flex align-items-center mb-3">
                    <span class="text-danger small me-2" id="err"></span>
                    <span class="text-muted small" id="count"></span>
                </div>
            </div>
        </div>

        <p class="">
            Each point is a steady-state operating episode that should be comparable to the heat pump's data sheet. 
            Click a point to see the episode in the dashboard. Add constraints to hold a property in a narrow band and read the residual correlations.
        </p>

        <h6 class="text-muted mt-3">Constraints</h6>
        <div class="bg-light border rounded p-3">
            <div id="frows"></div>
            <button class="btn btn-outline-secondary btn-sm" id="addf">+ Add constraint</button>
            <button class="btn btn-outline-secondary btn-sm ms-2" id="clearf">Clear all</button>
        </div>

        <!-- Summary cards (filled by cards()) -->
        <div class="row g-2 my-3" id="cards"></div>

        <!-- Axis / colour selectors -->
        <div class="row g-2 my-2">
            <div class="col-md-4">
                <label class="form-label text-muted small mb-1">X axis</label>
                <select class="form-select" id="xs"></select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small mb-1">Y axis</label>
                <select class="form-select" id="ys"></select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small mb-1">Colour</label>
                <select class="form-select" id="cs"></select>
            </div>
        </div>

        <!-- Colour legend bar (filled by build()) -->
        <div class="d-flex align-items-center gap-2 my-2 small text-muted" id="cbar"></div>

        <!-- Chart -->
        <div class="border rounded bg-white p-2" style="position:relative; height:440px">
            <canvas id="sc"></canvas>
        </div>
        <p class="small text-muted mt-1">Orange line = median COP per 2&deg;C outside-temp band (appears on the outside-temp vs COP view).</p>

        <!-- Correlations (filled by correlations()) -->
        <div class="border rounded p-3 mt-3">
            <div class="text-muted small mb-2" id="corrhead"></div>
            <div class="d-flex flex-wrap gap-2" id="corrchips"></div>
        </div>

        <div class="text-center py-4">
            <a :href="path + 'system/view?id=' + systemid" class="btn btn-primary" id="backbtn">Back to System</a>
        </div>
    </div>
</div>

<!-- Server-side values consumed by signature.js -->
<script>
    var path = "<?php echo $path; ?>";
    var admin = <?php echo $admin; ?>;
    var systemid = <?php echo $systemid; ?>;
</script>
<script src="<?php echo $path; ?>Modules/signature/views/signature.js?v=2"></script>
