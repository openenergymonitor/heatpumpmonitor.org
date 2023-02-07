var app = new Vue({
  el: '#app',

  data: {
    nodes: [],
    filterKey: '',
    hiliteKey: 0,
    currentSort:'year_cop',
    currentSortDir:'desc'
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
      this.nodes = await (await fetch('data.json',{cache: "no-store"})).json()
    },
    
    sort(s, dir) {
      //if s == current sort, reverse
      if(s === this.currentSort) {
        this.currentSortDir = this.currentSortDir==='asc'?'desc':'asc';
      }
      else {
        this.currentSortDir = dir;
      }
      this.currentSort = s;
    },

    getValue(node, path) {
      var current=node;
      try {
        path.split('.').forEach(function(p){ current = current[p]; }); 
        return current;
      }
      catch (e) {
        return 0;
      }
    },

    compareNodes(n1, n2) {
      if (this.currentSort == '') {return 0;}
      
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
        return dir * val1.localeCompare(val2, undefined, {numeric: true, sensitivity: 'base'});
      }
    },

    filterNodes(row) {
      if (this.filterKey != '') {
        return Object.keys(row).some((key) => {
          return String(row[key]).toLowerCase().indexOf(this.filterKey.toLowerCase()) > -1 })
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
    } 
  },
  
  computed:{
    sortedNodes:function() {
      return this.nodes.sort(this.compareNodes).filter(this.filterNodes);
    }
  }
});

// grey if start date is less that 1 year ago
function sinceClass(node) {
  return node.since > 0 ? 'partial ' : '';
}
 
// grey if start date is less that 1 month ago
function monthClass(node) {
  return (node.since + 30 * 24 * 3600) * 1000 > Date.now() ? 'partial ' : '';
}
   
function sinceDate(node) {
  if (node.since == 0) {
    return "";
  }
  var date = new Date(node.since*1000);
  return "Since " + date.toDateString();
}

// helper function to append unit to a value, but only if it's not blank  
function unit_dp(value, unit, dp = false) {
  if (value != '' && value!==false) {
    if (dp!==false) value = value.toFixed(dp);
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

