var app = new Vue({
  el: '#app',

  data: {
    nodes: [],
    filterKey: '',
    currentSort:'year_cop',
    currentSortDir:'desc'
  },

  created() {
    this.fetchData()
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
        return '?';
      }
    },

    compareNodes(n1, n2) {
      if (this.currentSort == '') {return 0;}
      
      let dir = this.currentSortDir === 'desc' ? -1 : 1;
      let val1 = this.getValue(n1, this.currentSort);
      let val2 = this.getValue(n2, this.currentSort);
      
      if (typeof val1 === 'undefined') {
        return 0;
      }
      
      if (this.currentSort == 'age') {
        val1 = val1.replace(/^Pre-/, '');
        val2 = val2.replace(/^Pre-/, '');
      }
      
      return dir * val1.toString().localeCompare(val2, undefined, {numeric: true, sensitivity: 'base'});
    },

    filterNodes(row) {
      if (this.filterKey != '') {
        return Object.keys(row).some((key) => {
          return String(row[key]).toLowerCase().indexOf(this.filterKey.toLowerCase()) > -1 })
      }
      return true;
    },
    
    hasStats(row) {
      return typeof row.stats !== 'undefined';
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
function unit(value, unit) {
  return (value != '') ? value + ' ' + unit : '-';
}

var row;
function toggle(cell) {
  row = cell.parentElement;
  if (row.nextElementSibling.style.display == 'none') {
    row.nextElementSibling.style.display = 'table-row';
    cell.innerHTML = '&minusb;';  // -
  }
  else {
    row.nextElementSibling.style.display = 'none';
    cell.innerHTML = '&plusb;';  // +
  }
}

