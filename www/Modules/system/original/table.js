// Available months
// Aug 2023, Jul 2023, Jun 2023 etc for 12 months
var months = [];
var d = new Date();
for (var i = 0; i < 12; i++) {
    months.push(d.toLocaleString('default', { month: 'short' }) + ' ' + d.getFullYear());
    d.setMonth(d.getMonth() - 1);
}

var app = new Vue({
    el: '#app',

    data: {
        nodes: [],
        filterKey: '',
        hiliteKey: 0,
        currentSort: 'cop',
        currentSortDir: 'desc',
        show_kwh_m2: false,

        // stats time selection
        stats_time_start: "last365",
        stats_time_end: "only",
        stats_time_range: false,
        available_months_start: months,
        available_months_end: months,
        show_when_running: false,
    },

    created() {
        this.fetchData()
    },

    mounted() {
        const params = new URLSearchParams(window.location.search);
        this.filterKey = params.get("filter") ?? '';
        this.hiliteKey = window.location.hash.replace(/^#/, '');
    },

    methods: {
        async fetchData() {
            this.nodes = await (await fetch(path + 'system/list/public.json', { cache: "no-store" })).json()
            this.load_system_stats();

            // calculate fabric efficiency
            this.nodes.forEach(function (node) {
                node.kwh_m2_heat = 0;
                node.kwh_m2_elec = 0;
                if (node.floor_area > 0) {
                    node.kwh_m2_heat = node.heat_demand / node.floor_area;
                }
                if (node.year_cop > 0) {
                    node.kwh_m2_elec = (node.heat_demand / node.floor_area) / node.year_cop;
                }
            });

        },

        sort(s, dir) {
            //if s == current sort, reverse
            if (s === this.currentSort) {
                this.currentSortDir = this.currentSortDir === 'asc' ? 'desc' : 'asc';
            }
            else {
                this.currentSortDir = dir;
            }
            this.currentSort = s;
        },

        getValue(node, path) {
            var current = node;
            try {
                path.split('.').forEach(function (p) { current = current[p]; });
                return current;
            }
            catch (e) {
                return 0;
            }
        },

        compareNodes(n1, n2) {
            if (this.currentSort == '') { return 0; }

            let dir = this.currentSortDir === 'desc' ? -1 : 1;
            let val1 = this.getValue(n1, this.currentSort);
            let val2 = this.getValue(n2, this.currentSort);

            if (typeof val1 === 'undefined') {
                return -dir;
            }
            if (typeof val2 === 'undefined') {
                return dir;
            }

            if (this.currentSort == 'age') {
                val1 = val1.replace(/^Pre-/, '');
                val2 = val2.replace(/^Pre-/, '');
            }

            if (isFinite(val1) && isFinite(val2)) {
                return dir * (val1 - val2);
            }
            else {
                return dir * val1.localeCompare(val2, undefined, { numeric: true, sensitivity: 'base' });
            }
        },

        filterNodes(row) {
            if (this.filterKey != '') {
                return Object.keys(row).some((key) => {
                    return String(row[key]).toLowerCase().indexOf(this.filterKey.toLowerCase()) > -1
                })
            }
            return true;
        },

        isNew(row) {
            const submitted = Date.parse(row.submitted);
            return submitted > Date.now() - 14 * 24 * 3600 * 1000;
        },

        hasStats(row) {
            return typeof row.stats !== 'undefined';
        },

        // highlight row if id matches ?h in url
        hiliteClass(row) {
            return row.id == this.hiliteKey ? 'hilite ' : '';
        },

        stats_time_start_change: function () {
            // change available_months_end to only show months after start
            if (this.stats_time_start == 'last30' || this.stats_time_start == 'last365') {
                this.stats_time_end = 'only';
            } else {
                let start_index = this.available_months_start.indexOf(this.stats_time_start);
                this.available_months_end = this.available_months_start.slice(0, start_index);

                if (this.stats_time_end != 'only') {
                    this.stats_time_end = this.available_months_end[0];
                }
            }
            this.load_system_stats();
        },
        stats_time_end_change: function () {
            this.load_system_stats();
        },
        load_system_stats: function () {

            // Start
            let start = this.stats_time_start;
            if (start != 'last30' && start != 'last365') {
                // Convert e.g Mar 2023 to 2023-03-01
                let months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                let month = start.split(' ')[0];
                let year = start.split(' ')[1];
                start = year + '-' + (months.indexOf(month) + 1) + '-01';
            }

            // End
            let end = this.stats_time_end;
            if (end != 'only') {
                // Convert e.g Mar 2023 to 2023-03-01
                let months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                let month = end.split(' ')[0];
                let year = end.split(' ')[1];
                end = year + '-' + (months.indexOf(month) + 1) + '-01';
            } else {
                end = start;
            }

            var url = path + 'system/stats';
            var params = {
                start: start,
                end: end
            };

            if (start == 'last30' || start == 'last365') {
                url = path + 'system/stats/' + start;
                params = {};
            }
            // Load system/stats data
            axios.get(url, {
                params: params
            })
            .then(response => {
                var stats = response.data;
                for (var i = 0; i < app.nodes.length; i++) {
                    let id = app.nodes[i].id;
                    if (stats[id]) {
                        // copy stats data to system
                        for (var key in stats[id]) {
                            app.nodes[i][key] = stats[id][key];
                        }
                    } else {
                        app.nodes[i]['elec_kwh'] = 0;
                        app.nodes[i]['heat_kwh'] = 0;
                        app.nodes[i]['cop'] = 0;

                        app.nodes[i]['when_running_elec_kwh'] = 0;
                        app.nodes[i]['when_running_heat_kwh'] = 0;
                        app.nodes[i]['when_running_cop'] = 0;

                        app.nodes[i]['when_running_flowT'] = 0;
                        app.nodes[i]['when_running_flow_minus_return'] = 0;
                        app.nodes[i]['when_running_outsideT'] = 0;
                        app.nodes[i]['standby_kwh'] = 0;

                    }
                }
                // force re-render
                app.nodes = app.nodes.slice();
            })
            .catch(error => {
                alert("Error loading data: " + error);
            });
        }
    },

    computed: {
        sortedNodes: function () {
            return this.nodes.sort(this.compareNodes).filter(this.filterNodes);
        }
    }
});

// grey if start date is less that 1 year ago
function sinceClass(node) {
    // return node.since > 0 ? 'partial ' : '';
    // node.since is unix time in seconds
    return (node.since + 360 * 24 * 3600) * 1000 > Date.now() ? 'partial ' : '';
}

// grey if start date is less that 1 month ago
function monthClass(node) {
    return (node.since + 30 * 24 * 3600) * 1000 > Date.now() ? 'partial ' : '';
}

function sinceDate(node) {
    if (node.since == 0) {
        return "";
    }
    var date = new Date(node.since * 1000);
    return "Since " + date.toDateString();
}

// helper function to append unit to a value, but only if it's not blank  
function unit_dp(value, unit, dp = false) {
    if (value != '' && value !== false) {
        if (dp !== false) value = value.toFixed(dp);
        value += ' ' + unit
    } else {
        value = '-';
    }
    return value;
}

function toggle(cell) {
    var row = cell.parentElement;
    if (row.nextElementSibling.style.display == 'none') {
        row.nextElementSibling.style.display = 'table-row';
        cell.innerHTML = '&minusb;';  // -
    }
    else {
        row.nextElementSibling.style.display = 'none';
        cell.innerHTML = '&plusb;';  // +
    }
}

