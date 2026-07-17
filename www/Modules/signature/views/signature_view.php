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
<link rel="stylesheet" type="text/css" href="<?php echo $path; ?>Modules/signature/views/signature.css?v=1">

<div id="signature">
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
                    <div class="input-group mb-3" style="position:relative">
                        <span class="input-group-text">Select system</span>
                        <input type="text" class="form-control" v-model="query"
                            placeholder="Type location, make/model or kW…"
                            @focus="openList($event)" @input="onInput" @keydown="onKey" @blur="onBlur">
                        <button class="btn btn-primary" @click="next_system(-1)">&lt;</button>
                        <button class="btn btn-primary" @click="next_system(1)">&gt;</button>

                        <!-- Filtered match list; mousedown (not click) so it fires before blur -->
                        <ul v-if="showList && filtered.length" class="sig-dropdown">
                            <li v-for="(s,i) in filtered" :key="s.id"
                                :class="{active: i===highlight}"
                                @mousedown.prevent="select(s)"
                                @mouseenter="highlight=i">{{ label(s) }}</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="mb-3" style="line-height:36px">
                        <span class="err" id="err"></span>
                        <span class="text-muted" id="count"></span>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-muted" style="font-size:14px">
            Each point is a steady-state operating episode detected in this system's real data.
            Any variable can go on either axis or drive colour; bubble size is duration; click a point to open its dashboard view.
            Add constraints to hold a property in a narrow band and read the residual correlations.
        </p>

        <h6 class="text-muted mt-3">Constraints</h6>
        <div class="filters">
            <div id="frows"></div>
            <button class="btn btn-outline-secondary btn-sm" id="addf">+ Add constraint</button>
            <button class="btn btn-outline-secondary btn-sm" id="clearf" style="margin-left:6px">Clear all</button>
        </div>

        <div class="statcards" id="cards"></div>

        <div class="controls">
            <div><label>X axis</label><select id="xs"></select></div>
            <div><label>Y axis</label><select id="ys"></select></div>
            <div><label>Colour</label><select id="cs"></select></div>
        </div>
        <div class="cbar" id="cbar"></div>
        <div class="chartbox"><canvas id="sc"></canvas></div>
        <p class="hint">Orange line = median COP per 2&deg;C outside-temp band (appears on the outside-temp vs COP view).</p>

        <div class="corr">
            <div class="head" id="corrhead"></div>
            <div class="chips" id="corrchips"></div>
        </div>

        <div style="text-align:center; padding:20px 0">
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
<script src="<?php echo $path; ?>Modules/signature/views/signature.js?v=1"></script>
