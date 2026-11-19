const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const file = process.env.DASHBOARD_JS || path.join(__dirname, '../www/Modules/dashboard/myheatpump_bargraph.js');
const src = fs.readFileSync(file, 'utf8');
const load = src.slice(src.indexOf('function bargraph_load('), src.indexOf('\nfunction bargraph_draw('));
const draw = src.slice(src.indexOf('function bargraph_draw('), src.indexOf('\n    $("#window-carnot-cop").html("");')) + '\n}';
const elements = {};
function $(selector) {
  return elements[selector] ||= {show(){return this}, hide(){return this}, html(v){this.value=v;return this}, attr(k,v){this[k]=v;return this}, text(v){this.value=v;return this}, 0:{checked:false}};
}
let csv = '';
$.ajax = o => { assert.equal(o.url, '/system/stats/daily'); o.success(csv); };
const ctx = { $, set_url_view_params(){}, DAY:86400000, path:'/', config:{id:900001,app:{enable_process_daily:{value:1}}}, apikey:'', daily_data:{}, bargraph_series:[], data:{}, feeds:{}, bargraph_mode:'combined', flot_color:()=> '#000', show_daily_dhw:false, show_daily_cooling:false, show_daily_immersion:false, show_daily_boiler:false };
vm.createContext(ctx); vm.runInContext(load + '\n' + draw,ctx);
const header='id,timestamp,combined_elec_kwh,combined_heat_kwh,water_heat_kwh,cooling_heat_kwh,immersion_kwh,boiler_kwh,cooling_elec_kwh\n';
function test(rows, expected) { csv=header+rows; ctx.bargraph_load(0,172800000); ctx.bargraph_draw(); assert.equal(elements['#window-kwh']?.value, expected); }
test('1,0,5,15,0,0,0,0,0\n2,86400,7,21,0,0,0,0,0\n','Recorded daily values — Electric: 12.0 kWh · Heat: 36.0 kWh');
test('1,0,5,15,0,0,0,0,0\n2,86400,,21,0,0,0,0,0\n','Recorded daily values — Electric: — · Heat: 36.0 kWh');
test('1,0,0,0,0,0,0,0,0\n','Recorded daily values — Electric: 0.0 kWh · Heat: 0.0 kWh');
test('1,0,5,15,0,0,0,0,0\n','Recorded daily values — Electric: 5.0 kWh · Heat: 15.0 kWh');
test('1,0,bad,15,0,0,0,0,0\n','Recorded daily values — Electric: — · Heat: 15.0 kWh');
test('','Recorded daily values — Electric: — · Heat: —');
test('1,0,5,15,0,0,1,0,0\n','Recorded daily values — HP Electric: 5.0 kWh · HP Heat: 15.0 kWh');
ctx.bargraph_mode = 'cooling';
test('1,0,5,15,0,8,0,0,2\n','Recorded daily values — Electric: 2.0 kWh · Cooling: 8.0 kWh');
ctx.bargraph_mode = 'running';
test('1,0,5,15,0,8,0,0,2\n','Recorded daily values — Electric: — · Heat: —');
console.log('PASS: omitted day remains explicitly labelled recorded daily values.');
console.log('PASS: totals, blank, non-finite, empty, zero, immersion, cooling and stale-mode cases.');
console.log('LIMIT: AJAX and DOM are mocks; no complete draw, browser, power view or mobile.');
