var app = new Vue({
  el: '#app',

  data: {
    nodes: [],
    filterKey: '',
    currentSort:'',
    currentSortDir:'asc'
  },

  created() {
    this.fetchData()
  },

  methods: {
    async fetchData() {
      // https://docs.google.com/spreadsheets/d/e/2PACX-1vQ-eqigAmjwwSIc6snCYTWRYZW6wsVK98fsJ8kn4aiG_pDw8qgpc4y_ZkiHC_OtWpchDCk1nBwxza8W/pub?gid=447603213&single=true&output=tsv
      const url = `data.tsv`
      let text = await (await fetch(url,{cache: "no-store"})).text()
      this.nodes = this.readData(text)
    },
    
    readData(text) {
      let nodes = [];
      let rows = text.split("\n");
      rows.shift(); // skip header
      for (let row of rows) {
        if (row.includes("\t")) {
          nodes.push(new Node(row));
        }
      }
      return nodes;
    },
    
    sort(s) {
      //if s == current sort, reverse
      if(s === this.currentSort) {
        this.currentSortDir = this.currentSortDir==='asc'?'desc':'asc';
      }
      this.currentSort = s;
    }
  },
  
  computed:{
    sortedNodes:function() {
      return this.nodes.sort((a,b) => {
        if (this.currentSort == '') {return 0;}
        if(this.currentSortDir === 'desc') {
          return b[this.currentSort].localeCompare(a[this.currentSort], undefined, {numeric: true, sensitivity: 'base'});
        }
        else {
          return a[this.currentSort].localeCompare(b[this.currentSort], undefined, {numeric: true, sensitivity: 'base'});
        }
      }).filter((row) => {
        if (this.filterKey != '') {
          return Object.keys(row).some((key) => {
            return String(row[key]).toLowerCase().indexOf(this.filterKey.toLowerCase()) > -1 })
        }
        return true;
      });
    }
  }
});
    
function Node(row) {
  let cols = row.split("\t");
  this.location = cols[0];
  this.hp_model = cols[1];
  this.hp_type = cols[2];
  this.hp_output = cols[3];
  this.emitters = cols[4];
  this.annual_kwh = cols[5];
  this.notes = cols[6];
  this.property = cols[7];
  this.floor_area = cols[8];
  this.heat_loss = cols[9];
  this.url = cols[10];
  this.age = cols[12];
  this.insulation = cols[13];

  // 14 flow_temp
  // 15 buffer
  // 16	freeze
  // 17	zone
  // 18	controls
  // 19	refrigerant
  // 20	dhw
  // 21	legionella

  this.year_elec = cols[22];
  this.year_heat = cols[23];
  this.since = cols[24];
  if (this.year_heat > 0) {
    this.year_cop = (cols[23] / cols[22]).toFixed(1);
  }
  else {
    this.year_cop = "-";
  }
  
  if (this.age == 'Pre-1900') {
    // Fix for sorting ages
    this.age = ' Pre-1900';
  }
  
  this.sinceClass = function () {
    return this.since > 0 ? 'partial nowrap' : 'nowrap';
  }
  
  this.sinceDate = function() {
    if (this.since == 0) {
      return "";
    }
    var date = new Date(this.since*1000);
    return "Since " + date.toDateString();
  }
}

