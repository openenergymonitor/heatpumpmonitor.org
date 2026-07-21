<?php
defined('EMONCMS_EXEC') or die('Restricted access');
global $session, $path;

// Admin unlocks the admin system list; otherwise the public/user lists apply.
$admin = 0;
if (isset($session['admin']) && $session['admin']) {
    $admin = 1;
}

// System to open on load: ?id=... if given, otherwise default to 748. If that
// system isn't in the accessible list, emitter.js falls back to the first one.
$systemid = 748;
if (isset($_GET['id'])) {
    $systemid = (int) $_GET['id'];
}
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>

<div>
    <div style="background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid" style="max-width:1300px">
            <h3>Emitter capacity estimator</h3>
        </div>
    </div>

    <div class="container-fluid" style="margin-top:20px; max-width:1300px">

        <div class="row g-3">

            <!-- ================= Left: system control panel ================= -->
            <!-- Vue owns the left panel: search plus prev/next stepping through
                 the matching systems (single system at a time). -->
            <div class="col-lg-3">
                <div id="app" class="card position-sticky" style="top:10px">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">System</h6>

                        <label class="form-label text-muted small mb-1">Search</label>
                        <input type="text" class="form-control form-control-sm" v-model="query" placeholder="">

                        <label class="form-label text-muted small mb-1 mt-2">
                            Select system <span class="text-muted">({{ filtered.length }} matching)</span>
                        </label>
                        <select class="form-select form-select-sm" v-model="candidate" @change="open(candidate)">
                            <option v-for="s in filtered" :key="s.id" :value="s.id">{{ label(s) }}</option>
                        </select>
                        <div class="d-flex gap-1 mt-1">
                            <button class="btn btn-outline-secondary btn-sm flex-fill" @click="step(-1)" title="Previous match">&lt; Prev</button>
                            <button class="btn btn-outline-secondary btn-sm flex-fill" @click="step(1)" title="Next match">Next &gt;</button>
                        </div>

                        <hr class="my-3">

                        <div v-if="current" class="small">
                            <div class="fw-bold">{{ label(current) }}</div>
                        </div>
                        <div v-else class="text-muted small">No system selected.</div>
                    </div>
                </div>
            </div>

            <!-- ================= Right: parameters, histogram, stats ================= -->
            <div class="col-lg-9">

                <div class="mb-1">
                    <span class="text-danger small me-2" id="err"></span>
                    <span class="text-muted small" id="count"></span>
                </div>

                <p class="">
                    Estimates the household's installed emitter (radiator) capacity from steady-state operating episodes.
                    For each episode the rated output at &Delta;T50 is inferred from the standard radiator equation
                    <i>heat&nbsp;=&nbsp;rated&nbsp;&times;&nbsp;(&Delta;T&nbsp;/&nbsp;50)<sup>1.3</sup></i>,
                    where &Delta;T is mean water temperature (flow&nbsp;+&nbsp;return)&nbsp;/&nbsp;2 minus room temperature.
                    Each estimate is weighted by the heat energy delivered during the episode, building a histogram of
                    rated capacity vs kWh delivered. Requires episodes with heat, return temp and room temp data.
                </p>

                <h6 class="text-muted mt-3">Parameters</h6>
                <div class="bg-light border rounded p-3">
                    <div class="row g-2">
                        <div class="col-6 col-md">
                            <label class="form-label text-muted small mb-1">Rated &Delta;T (K)</label>
                            <input type="number" class="form-control form-control-sm" id="rdt" step="1" value="50">
                        </div>
                        <div class="col-6 col-md">
                            <label class="form-label text-muted small mb-1">Exponent n</label>
                            <input type="number" class="form-control form-control-sm" id="exp" step="0.05" value="1.3">
                        </div>
                        <div class="col-6 col-md">
                            <label class="form-label text-muted small mb-1">Min &Delta;T (K)</label>
                            <input type="number" class="form-control form-control-sm" id="mindt" step="1" value="5">
                        </div>
                        <div class="col-6 col-md">
                            <label class="form-label text-muted small mb-1">Bin width (kW)</label>
                            <input type="number" class="form-control form-control-sm" id="binw" step="0.25" min="0.05" value="0.5">
                        </div>
                        <div class="col-6 col-md">
                            <label class="form-label text-muted small mb-1">Smoothing &sigma; (kW, 0 = auto)</label>
                            <input type="number" class="form-control form-control-sm" id="bw" step="0.25" min="0" value="0">
                        </div>
                    </div>
                </div>

                <!-- Summary cards (filled by cards()) -->
                <div class="row g-2 my-3" id="cards"></div>

                <!-- Histogram -->
                <div class="border rounded bg-white p-2" style="position:relative; height:420px">
                    <canvas id="hist"></canvas>
                </div>

                <!-- Sample of a clicked bin's episodes (filled by selectBin()) -->
                <div class="border rounded p-3 mt-3" id="binpanel" style="display:none">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="text-muted small" id="binhead"></div>
                        <button type="button" class="btn-close btn-sm ms-2" id="binclose" title="close"></button>
                    </div>
                    <div class="table-responsive" id="bintable"></div>
                </div>

                <!-- Peak analysis (filled by peaksPanel()) -->
                <div class="border rounded p-3 mt-3">
                    <div class="text-muted small mb-2" id="peakhead"></div>
                    <div class="d-flex flex-wrap gap-2" id="peakchips"></div>
                </div>

                <div class="text-center py-4">
                    <a href="#" class="btn btn-outline-secondary" id="sigbtn">Open in signature explorer</a>
                    <a href="#" class="btn btn-primary" id="backbtn">Back to System</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Server-side values consumed by emitter.js -->
<script>
    var path = "<?php echo $path; ?>";
    var admin = <?php echo $admin; ?>;
    var systemid = <?php echo $systemid; ?>;
</script>
<script src="<?php echo $path; ?>Modules/emitter/views/emitter.js?v=3"></script>
