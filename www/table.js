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
      console.log("fetchData: " + this.nodes.length);
    },
    sort(s) {
      //if s == current sort, reverse
      if(s === this.currentSort) {
        this.currentSortDir = this.currentSortDir==='asc'?'desc':'asc';
      }
      this.currentSort = s;
    },
    
    compareNodes(n1, n2) {
      if (this.currentSort == '') {return 0;}
      
      let dir = this.currentSortDir === 'desc' ? -1 : 1;
      let val1 = n1[this.currentSort].toString();
      let val2 = n2[this.currentSort].toString();
      
      return dir * val1.toString().localeCompare(val2, undefined, {numeric: true, sensitivity: 'base'});
    },

    filterNodes(n)
    {
      if (this.filterKey != '') {
        return Object.keys(row).some((key) => {
          return String(row[key]).toLowerCase().indexOf(this.filterKey.toLowerCase()) > -1 })
      }
      return true;
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

