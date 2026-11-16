<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

global $path;
?>
<style>
/* ===================================================================
   SPF predictors: how well MCS 031, manufacturer SCOP and weighted
   flow − outside ΔT predict measured SPF. Ported from the standalone
   page (standalone_pages/heatpump-spf-predictors.html); tokens follow
   home_view.php .hpm-home.
   =================================================================== */
.hpm-pred {
    --hpm-blue: #44b3e2;
    --hpm-blue-deep: #2187ba;
    --hpm-ink: #14344a;
    --hpm-ink-soft: #3d5a6e;
    --hpm-muted: #64808f;
    --hpm-sky: #eef6fb;
    --hpm-sky-deep: #ddeef8;
    --hpm-card: #ffffff;
    --hpm-line: #d9e8f1;
    --hpm-teal: #2a9d8f;
    --hpm-amber: #c97716;
    --shadow: rgba(20, 52, 74, .05);
    --shadow-strong: rgba(20, 52, 74, .14);
    /* names used by the chart code */
    --accent: var(--hpm-blue);
    --muted: var(--hpm-muted);
    --faint: #8aa3b1;
    --grid: #e7f0f6;
    --axis: #9fbccd;
    --c-meas: var(--hpm-blue-deep);
    --c-mcs: var(--hpm-amber);
    --c-scop: var(--hpm-teal);
    --c-dt: #7e57c2;
    --thread: rgba(20, 52, 74, .18);
    --ring: #ffffff;
    --band: rgba(68, 179, 226, .08);

    background: linear-gradient(180deg, var(--hpm-sky-deep) 0, var(--hpm-sky) 240px);
    background-color: var(--hpm-sky);
    color: var(--hpm-ink-soft);
    line-height: 1.5;
    min-height: 100%;
}
.hpm-pred-wrap { max-width: 1600px; margin: 0 auto; padding: 24px 28px 20px; display: flex; flex-direction: column; gap: 16px; }

.hpm-pred header { display: flex; flex-direction: column; gap: 8px; }
.hpm-pred h1 { margin: 0; color: var(--hpm-ink); font-weight: 800; letter-spacing: -.02em; line-height: 1.15; font-size: clamp(24px, 2.2vw, 38px); }
@media (min-width: 1000px) { .hpm-pred h1 { white-space: nowrap; } }
.hpm-pred h1 .acc { color: var(--hpm-blue-deep); }
.hpm-pred header p { margin: 0; min-height: 1.5em; font-size: clamp(15px, 1.2vw, 18px); }

.hpm-pred .tabs { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 4px; background: var(--hpm-sky); border: 1px solid var(--hpm-line); border-radius: .75rem; }
.hpm-pred .tabs button { border: 0; background: transparent; border-radius: .5rem; padding: 5px 12px; font: inherit; font-size: 14px; font-weight: 600; color: var(--hpm-ink-soft); cursor: pointer; white-space: nowrap; }
.hpm-pred .tabs button:hover { color: var(--hpm-blue-deep); }
.hpm-pred .tabs button.on { background: var(--hpm-blue-deep); color: #fff; }
.hpm-pred .tabs button:focus-visible { outline: 3px solid rgba(68, 179, 226, .55); outline-offset: 2px; }
.hpm-pred .tabs kbd { font-size: 11px; opacity: .6; margin-right: 6px; font-family: inherit; padding: 0; background: none; color: inherit; box-shadow: none; }

/* ---- headline cards ---- */
.hpm-pred .kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.hpm-pred .kpi { background: var(--hpm-card); border: 1px solid var(--hpm-line); border-radius: .75rem; padding: 8px 14px 9px; box-shadow: 0 4px 16px var(--shadow); transition: opacity .2s; cursor: pointer; }
.hpm-pred .kpi .l { font-size: 12px; line-height: 1.3; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--k); margin: 0; }
.hpm-pred .kpi .v { font-size: clamp(20px, 1.6vw, 26px); font-weight: 800; letter-spacing: -.02em; line-height: 1.15; color: var(--hpm-ink); margin: 1px 0 2px; font-variant-numeric: tabular-nums; }
.hpm-pred .kpi .s { font-size: 13px; line-height: 1.4; color: var(--muted); margin: 0; font-variant-numeric: tabular-nums; }
.hpm-pred .kpi:hover { border-color: var(--k); }
.hpm-pred .kpi.sel { border-color: var(--k); box-shadow: 0 0 0 2px var(--k), 0 4px 16px var(--shadow); }
.hpm-pred .kpi:focus-visible { outline: 3px solid rgba(68, 179, 226, .55); outline-offset: 2px; }
.hpm-pred .kpi.dim { opacity: .4; }
.hpm-pred .kpi.dim:hover { opacity: .75; }

/* ---- chart card ---- */
.hpm-pred .panel { background: var(--hpm-card); border: 1px solid var(--hpm-line); border-radius: 1rem; padding: 16px 20px 10px; box-shadow: 0 4px 16px var(--shadow); height: clamp(440px, 64vh, 720px); display: flex; flex-direction: column; position: relative; }
.hpm-pred .ph { display: flex; justify-content: space-between; align-items: center; gap: 8px 16px; flex-wrap: wrap; margin-bottom: 10px; }
.hpm-pred .legend { display: flex; flex-wrap: wrap; gap: 6px 22px; font-size: 15px; color: var(--hpm-ink-soft); align-items: center; min-height: 24px; }
.hpm-pred .legend span { display: flex; align-items: center; gap: 8px; }
.hpm-pred #chart { flex: 1; min-height: 0; position: relative; }
.hpm-pred #chart canvas { position: absolute; left: 0; top: 0; }
.hpm-pred #tip { position: absolute; pointer-events: none; background: var(--hpm-card); border: 1px solid var(--hpm-line); border-radius: .75rem; padding: 12px 14px; font-size: 14px; line-height: 1.5; color: var(--hpm-ink-soft); box-shadow: 0 12px 32px var(--shadow-strong); opacity: 0; transition: opacity .1s; z-index: 5; min-width: 250px; font-variant-numeric: tabular-nums; }
.hpm-pred #tip .h { font-weight: 700; font-size: 15px; color: var(--hpm-ink); }
.hpm-pred #tip .m { color: var(--muted); font-size: 13px; }
/* label | value | % vs measured, so the numbers line up row to row.
   (Not .row: Bootstrap's grid .row adds negative margins and full-width children.) */
.hpm-pred #tip .tg { display: grid; grid-template-columns: 1fr auto auto; column-gap: 12px; align-items: baseline; margin-top: 6px; }
.hpm-pred #tip .tl { display: flex; align-items: center; white-space: nowrap; }
.hpm-pred #tip .tv { text-align: right; color: var(--hpm-ink); font-weight: 600; }
.hpm-pred #tip .tp { text-align: right; color: var(--muted); font-size: 13px; min-width: 3.2em; }
.hpm-pred #tip .tn { grid-column: 1 / -1; color: var(--muted); font-size: 13px; }
.hpm-pred #tip .tc { display: grid; grid-template-columns: 1fr auto auto; column-gap: 14px; align-items: baseline; margin: 8px 0 4px; padding-top: 8px; border-top: 1px solid var(--hpm-line); font-size: 13px; }
.hpm-pred #tip .tc .tv { font-weight: 600; }
.hpm-pred #tip .th { text-align: right; color: var(--muted); font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
.hpm-pred #tip .tx { color: var(--muted); font-size: 13px; line-height: 1.4; max-width: 300px; }
.hpm-pred #tip .dot { display: inline-block; flex: none; width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; }
.hpm-pred .note { font-size: 13px; color: var(--muted); }

@media (max-width: 800px) { .hpm-pred-wrap { padding: 16px; } }
@media (max-width: 640px) { .hpm-pred .kpis { grid-template-columns: minmax(0, 1fr); } }
@media (prefers-reduced-motion: reduce) { .hpm-pred * { transition: none !important; } }
</style>

<div class="hpm-pred" id="hpm-pred">
  <div class="hpm-pred-wrap">
    <header>
      <h1>How well do design-stage estimates predict <span class="acc">real heat pump performance</span>?</h1>
      <p id="sub"></p>
    </header>
    <div class="kpis" id="kpis"></div>
    <div class="panel">
      <div class="ph">
        <div class="tabs" id="pred" aria-label="Predictor">
          <button data-p="all"><kbd>1</kbd>All predictors</button>
          <button data-p="mcs"><kbd>2</kbd>MCS 031</button>
          <button data-p="scop"><kbd>3</kbd>Manufacturer SCOP</button>
          <button data-p="dt"><kbd>4</kbd>ΔT fit</button>
        </div>
        <div class="tabs" id="mode" aria-label="Chart type">
          <button data-m="gap" title="Measured vs predicted per system (M)">Gaps</button>
          <button data-m="scatter" title="Measured against predicted (M)">Scatter</button>
        </div>
      </div>
      <div class="legend" id="legend"></div>
      <div id="chart"></div>
      <div id="tip"></div>
    </div>
    <div class="note" id="foot"></div>
  </div>
</div>

<script src="<?php echo $path; ?>theme/vendor/chart.js-4.4.1/chart.umd.js" integrity="sha384-dug+JxfBvklEQdJ4AYuBBAIScUz0bVN73xpy273gcAwHjb3qI0fXmuYNaNfdyYJG"></script>
<script>
var path = "<?php echo $path; ?>";

// Systems are embedded server-side rather than fetched over ajax, for a
// faster initial load (same as the flowtemp and economics pages).
// The same data is available as JSON at home/predictors_systems.
var predictors_systems = <?php echo json_encode($predictors_systems); ?>;

// [id, location, make & model, measured combined SPF, MCS 031 SPF (0 = none), manufacturer SCOP (0 = none), weighted flowT − outsideT (K)]
const D=predictors_systems.map(s=>[s.id,(s.location||'').split(',')[0].trim(),((s.hp_manufacturer||'')+' '+(s.hp_model||'')).trim(),s.cop,s.mcs031_spf||0,s.scop_spf||0,s.dt,null,s]).sort((a,b)=>b[3]-a[3]);

(function(){
if(D.length<3){
  document.getElementById('sub').textContent='Not enough systems with a full year of data to compare yet.';
  document.querySelector('.hpm-pred .panel').style.display='none';
  return;
}
const mean=a=>a.reduce((s,v)=>s+v,0)/a.length;
function corr(x,y){const mx=mean(x),my=mean(y);let n=0,a=0,b=0;x.forEach((v,i)=>{n+=(v-mx)*(y[i]-my);a+=(v-mx)**2;b+=(y[i]-my)**2});return n/Math.sqrt(a*b)}
const rmse=(m,p)=>Math.sqrt(mean(m.map((v,i)=>(v-p[i])**2)));
const within=(m,p,t)=>m.filter((v,i)=>Math.abs(v/p[i]-1)<=t).length;
const beat=(m,p)=>m.filter((v,i)=>v>p[i]).length;

// least-squares fit of measured SPF on ΔT across all systems
const dT=D.map(d=>d[6]),M=D.map(d=>d[3]);
const slope=(()=>{const mx=mean(dT),my=mean(M);let n=0,q=0;dT.forEach((x,i)=>{n+=(x-mx)*(M[i]-my);q+=(x-mx)**2});return n/q})();
const icpt=mean(M)-slope*mean(dT);
const fit=t=>icpt+slope*t;
D.forEach(d=>d[7]=fit(d[6]));

function stats(k){const S=D.filter(d=>d[k]>0),m=S.map(d=>d[3]),p=S.map(d=>d[k]);return{n:S.length,r:corr(p,m),rmse:rmse(m,p),w10:within(m,p,.1),beat:beat(m,p),bias:mean(m.map((v,i)=>v-p[i]))}}
const ST={mcs:stats(4),scop:stats(5),dt:stats(7)};
const SC=D.filter(d=>d[5]>0);

const root=document.getElementById('hpm-pred');
const css=n=>getComputedStyle(root).getPropertyValue(n).trim();
const f2=v=>isFinite(v)?v.toFixed(2):'–', sg=v=>isFinite(v)?(v>=0?'+':'−')+Math.abs(v).toFixed(2):'–', pc=(a,b)=>{const p=-Math.round((a/b-1)*100);return (p>=0?'+':'−')+Math.abs(p)+'%'};

const KP=[
 {key:'mcs',col:'--c-mcs',l:'MCS 031 SPF',v:()=>`R² = ${f2(ST.mcs.r**2)}`,s:()=>`RMSE ${f2(ST.mcs.rmse)} · ${ST.mcs.w10} of ${ST.mcs.n} within ±10%<br>${ST.mcs.beat} of ${ST.mcs.n} beat it · mean ${sg(ST.mcs.bias)}`},
 {key:'scop',col:'--c-scop',l:'Manufacturer SCOP',v:()=>`R² = ${f2(ST.scop.r**2)}`,s:()=>`RMSE ${f2(ST.scop.rmse)} · ${ST.scop.w10} of ${ST.scop.n} within ±10%<br>${ST.scop.beat} of ${ST.scop.n} beat it · mean ${sg(ST.scop.bias)}`},
 {key:'dt',col:'--c-dt',l:'Flow − outside ΔT (fitted)',v:()=>`R² = ${f2(ST.dt.r**2)}`,s:()=>`RMSE ${f2(ST.dt.rmse)} · ${ST.dt.w10} of ${ST.dt.n} within ±10%<br>SPF = ${f2(icpt)} − ${Math.abs(slope).toFixed(4)} × ΔT`}
];
document.getElementById('kpis').innerHTML=KP.map(k=>`<div class="kpi" data-k="${k.key}" style="--k:var(${k.col})" role="button" tabindex="0" title="Show only this comparison"><p class="l">${k.l}</p><p class="v">${k.v()}</p><p class="s">${k.s()}</p></div>`).join('');

const PRED={mcs:{k:4,col:'--c-mcs',name:'MCS 031',sh:'c'},scop:{k:5,col:'--c-scop',name:'Manufacturer SCOP',sh:'c'},dt:{k:7,col:'--c-dt',name:'ΔT fit',sh:'c'}};
const ORDER=['all','mcs','scop','dt'];
let pred='all',mode='gap',cj=null,OV=null;
const chart=document.getElementById('chart'),tip=document.getElementById('tip'),panel=document.querySelector('.panel'),legend=document.getElementById('legend');
const SUB={
 gap:{
  all:()=>`${D.length} systems on HeatpumpMonitor.org, last 365 days. Each vertical thread is one system: measured SPF against three predictors.`,
  mcs:()=>`${ST.mcs.n} systems with an MCS 031 estimate. Each thread is the gap between measured SPF and the estimate: ${ST.mcs.beat} of ${ST.mcs.n} beat it, by ${sg(ST.mcs.bias)} SPF on average.`,
  scop:()=>`${ST.scop.n} systems with a manufacturer SCOP. Each thread is the gap between measured SPF and SCOP: ${ST.scop.beat} of ${ST.scop.n} beat it, ${ST.scop.w10} land within ±10%.`,
  dt:()=>`All ${ST.dt.n} systems. Each thread is the gap between measured SPF and the ΔT fit: ${ST.dt.w10} of ${ST.dt.n} land within ±10%.`},
 scatter:{
  all:()=>`Predicted against measured SPF for every system and predictor. The closer a colour hugs the diagonal, the better that predictor.`,
  mcs:()=>`MCS 031 is consistently conservative: ${ST.mcs.beat} of ${ST.mcs.n} systems beat it, by ${sg(ST.mcs.bias)} SPF on average.`,
  scop:()=>`Manufacturer SCOP is close on average (${sg(ST.scop.bias)}) but only loosely tracks which systems do well.`,
  dt:()=>`Heat-weighted flow minus outside temperature explains more of the spread. It's measured in operation, so not a like-for-like design estimate.`}
};
const FILT=`Standard filter: H4 metering boundary, air source, no cooling, ≥330 days. ${ST.mcs.n} have an MCS 031 estimate, ${ST.scop.n} a manufacturer SCOP.`;
const FOOT={
 gap:{all:`Measured = combined SPF (space heating + hot water). ${FILT}`,mcs:'Coloured thread = system fell short of its MCS 031 estimate.',scop:'Coloured thread = system fell short of its manufacturer SCOP.',dt:'Coloured thread = system fell short of the ΔT fit.'},
 scatter:{all:`Above the diagonal = beat the prediction. Dashed lines = ±10%. ${FILT}`,mcs:'Above the diagonal = system beat its MCS 031 estimate. Dashed lines = ±10%.',scop:'Above the diagonal = system beat its manufacturer SCOP. Dashed lines = ±10%.',dt:'Solid line = least-squares fit across all systems; dashed = ±10% of the fit.'}
};
function sw(c,s,t){const sh=s==='c'?`<circle cx="8" cy="7" r="5.5" fill="${c}"/>`:s==='s'?`<rect x="3" y="2" width="10" height="10" fill="${c}"/>`:s==='d'?`<path d="M8 1L13 7L8 13L3 7Z" fill="${c}"/>`:s==='t'?`<rect x="1" y="5.5" width="14" height="3" fill="${c}"/>`:`<line x1="1" y1="7" x2="15" y2="7" stroke="${c}" stroke-width="2" ${s==='dash'?'stroke-dasharray="3 3"':''}/>`;return `<span><svg width="16" height="14">${sh}</svg>${t}</span>`}
const alpha=(c,a)=>{if(c[0]==='#'){let h=c.slice(1);if(h.length===3)h=h.replace(/./g,'$&$&');const n=parseInt(h,16);return `rgba(${n>>16&255},${n>>8&255},${n&255},${a})`}const m=c.match(/[\d.]+/g);return m?`rgba(${m[0]},${m[1]},${m[2]},${a})`:c};

// Why measured and estimated differ: each estimate is a space heating and a
// hot water figure applied to the system's measured heat, so show those
// against measured, plus the design flow temperature the estimates read off.
function context(c){
  if(!c)return '';
  const v=x=>x?x.toFixed(2):'–',deg=x=>(x<0?'−':'')+Math.abs(x).toFixed(Number.isInteger(x)?0:1)+'\u00a0°C';
  const r=(col,l,s,w)=>`<span class="tl"><span class="dot" style="background:${css(col)}"></span>${l}</span><span class="tv">${v(s)}</span><span class="tv">${v(w)}</span>`;
  let h=`<div class="tc"><span></span><span class="th">Space</span><span class="th">Water</span>`+r('--c-meas','Measured',c.space_cop,c.water_cop);
  if(c.mcs_space)h+=r('--c-mcs','MCS 031',c.mcs_space,c.mcs_water);
  if(c.scop_space)h+=r('--c-scop','SCOP',c.scop_space,c.scop_water);
  h+='</div>';
  const n=[];
  if(c.water_share!=null)n.push(`Hot water ${Math.round(c.water_share*100)}% of heat`);
  if(c.flow_temp)n.push(`Design flow ${deg(c.flow_temp)}${c.design_temp!=null?` at ${deg(c.design_temp)} outside`:''}`);
  if(c.coldest_flow)n.push(`Coldest day: ran at ${deg(c.coldest_flow)}`);
  if(c.emitters)n.push(c.emitters[0].toUpperCase()+c.emitters.slice(1));
  if(c.immersion&&c.mcs_space)n.push('Immersion pasteurisation, counted in the MCS 031 hot water figure');
  return h+n.map(t=>`<div class="tx">${t}</div>`).join('');
}
function showTip(d,ev){
  const f=d[7];
  const row=(c,l,v,p)=>`<span class="tl"><span class="dot" style="background:${css(c)}"></span>${l}</span><span class="tv">${v}</span><span class="tp">${p||''}</span>`;
  const none=t=>`<span class="tn">${t}</span>`;
  tip.innerHTML=`<div class="h">${d[1]}</div><div class="m">${d[2]} · id ${d[0]}</div><div class="tg">`+
   row('--c-meas','Measured SPF',f2(d[3]))+
   (d[4]?row('--c-mcs','MCS 031',f2(d[4]),pc(d[3],d[4])):none('No MCS 031 estimate'))+
   (d[5]?row('--c-scop','SCOP',f2(d[5]),pc(d[3],d[5])):none('No SCOP estimate'))+
   row('--c-dt',`ΔT ${d[6].toFixed(1)} K → fit`,f2(f),pc(d[3],f))+`</div>`+context(d[8]);
  const pr=panel.getBoundingClientRect();let x=ev.clientX-pr.left+16,y=ev.clientY-pr.top+16;
  tip.style.opacity=1;const tw=tip.offsetWidth,th=tip.offsetHeight;
  if(x+tw>pr.width-8)x=ev.clientX-pr.left-tw-16; if(y+th>pr.height-8)y=Math.max(8,ev.clientY-pr.top-th-16);
  tip.style.left=x+'px';tip.style.top=y+'px';
}
const hideTip=()=>tip.style.opacity=0;
const open=d=>{window.location=path+'system/view?id='+d[0]};

// Chart.js plugin: highlights the hovered column (gaps) and draws the
// diagonal / fit line, ±10% lines, "beat it" band and outlier labels (scatter).
const overlay={id:'hpmOverlay',
  beforeDatasetsDraw(c){
    if(!OV)return;const {ctx,chartArea:a,scales:{x,y}}=c;
    ctx.save();ctx.beginPath();ctx.rect(a.left,a.top,a.width,a.height);ctx.clip();
    if(OV.column){const act=c.getActiveElements();if(act.length){const bw=Math.max(3,a.width/OV.column);ctx.fillStyle=css('--accent');ctx.globalAlpha=.15;ctx.fillRect(x.getPixelForValue(act[0].index)-bw/2,a.top,bw,a.height);ctx.globalAlpha=1}}
    if(OV.band){ctx.fillStyle=css('--band');ctx.beginPath();OV.band.forEach(([u,v],i)=>ctx[i?'lineTo':'moveTo'](x.getPixelForValue(u),y.getPixelForValue(v)));ctx.closePath();ctx.fill()}
    (OV.lines||[]).forEach(l=>{ctx.strokeStyle=css('--axis');ctx.lineWidth=l.w;ctx.setLineDash(l.dash?[5,5]:[]);ctx.beginPath();ctx.moveTo(a.left,y.getPixelForValue(l.f(x.min)));ctx.lineTo(a.right,y.getPixelForValue(l.f(x.max)));ctx.stroke()});
    ctx.restore();
  },
  afterDatasetsDraw(c){
    if(!OV||!OV.labels)return;const {ctx,scales:{x,y}}=c;
    ctx.save();ctx.fillStyle=css('--muted');ctx.font=`13px ${Chart.defaults.font.family}`;ctx.textBaseline='middle';
    OV.labels.forEach(l=>ctx.fillText(l.t,x.getPixelForValue(l.x)+OV.r+6,y.getPixelForValue(l.y)));
    ctx.restore();
  }
};
// ticks on round multiples of the step (Chart.js otherwise anchors them to min)
const roundTicks=step=>ax=>{const t=[];for(let v=Math.ceil(ax.min/step-1e-9)*step;v<=ax.max+1e-9;v+=step)t.push({value:+v.toFixed(6)});ax.ticks=t};
function axis(title,extra){return Object.assign({border:{display:false},grid:{color:css('--grid')},ticks:{color:css('--muted'),font:{size:14}},title:{display:true,text:title,color:css('--muted'),font:{size:15}}},extra)}
function draw(type,datasets,scales,interaction,pick,labels){
  if(cj)cj.destroy();
  Chart.defaults.font.family=getComputedStyle(chart).fontFamily;
  const cv=document.createElement('canvas');chart.replaceChildren(cv);
  cj=new Chart(cv,{type,data:{labels,datasets},plugins:[overlay],options:{
    animation:false,responsive:true,maintainAspectRatio:false,layout:{padding:{top:4,right:4}},
    interaction,scales,plugins:{legend:{display:false},tooltip:{enabled:false}},
    onHover:(e,els)=>{const d=els.length?pick(els[0]):null;cv.style.cursor=d?'pointer':'default';d&&e.native?showTip(d,e.native):hideTip()},
    onClick:(e,els)=>{if(els.length)open(pick(els[0]))}}});
  cv.addEventListener('mouseleave',hideTip);
}

// Gap view: one vertical thread per system, sorted by measured SPF
function gaps(){
  const fp=PRED[pred],S=fp?D.filter(d=>d[fp.k]>0):D,n=S.length;
  legend.innerHTML=fp?sw(css('--c-meas'),'c','Measured SPF')+sw(css(fp.col),fp.sh,fp.name)+sw(css('--thread'),'line','Beat it')+sw(css(fp.col),'line','Fell short')
    :sw(css('--c-meas'),'c','Measured SPF')+sw(css('--c-mcs'),'c','MCS 031')+sw(css('--c-scop'),'c','Manufacturer SCOP')+sw(css('--c-dt'),'c','ΔT-predicted SPF')+`<span style="color:var(--faint)">Amber thread = fell short of MCS 031</span>`;
  const iw=Math.max(400,chart.clientWidth)-90,sz=Math.max(1,Math.min(fp?3:2.2,iw/n*(fp?.6:.55)));
  // marker sizes match the original SVG marks: square, horizontal tick, diamond, circle
  const R={c:sz*1.2,s:sz*1.41,t:sz*1.6,d:sz*1.4},STY={c:'circle',s:'rect',t:'line',d:'rectRot'};
  const pts=(sh,col,data,order)=>{const c=css(col),r=R[sh],bw=sh==='t'?sz*.9:0;return{type:'line',data,order,showLine:false,pointStyle:STY[sh],pointRadius:r,pointHoverRadius:r,pointBackgroundColor:c,pointBorderColor:c,pointBorderWidth:bw,pointHoverBackgroundColor:c,pointHoverBorderColor:c,pointHoverBorderWidth:bw}};
  const tk=fp?fp.k:4,tc=css(fp?fp.col:'--c-mcs'),th=css('--thread');
  const ds=[{type:'bar',order:9,barThickness:Math.max(1,sz*(fp?.8:.7)),
    data:S.map(d=>d[tk]?[Math.min(d[3],d[tk]),Math.max(d[3],d[tk])]:null),
    backgroundColor:S.map(d=>d[3]<d[tk]?tc:th),hoverBackgroundColor:S.map(d=>d[3]<d[tk]?tc:th)}];
  if(fp)ds.push(pts(fp.sh,fp.col,S.map(d=>d[fp.k]),5));
  else ds.push(pts('c','--c-dt',S.map(d=>d[7]),3),pts('c','--c-scop',S.map(d=>d[5]||null),2),pts('c','--c-mcs',S.map(d=>d[4]||null),1));
  ds.push(pts('c','--c-meas',S.map(d=>d[3]),0));
  OV={column:n};
  draw('bar',ds,{
    x:axis(`${n} systems, sorted by measured SPF (highest left)`,{grid:{display:false},ticks:{display:false}}),
    y:axis('SPF',{min:1.5,max:6.5,ticks:{color:css('--muted'),font:{size:14},stepSize:.5,callback:v=>v.toFixed(1)}})},
    {mode:'index',axis:'x',intersect:false},el=>S[el.index],S.map(d=>d[0]));
}

// Scatter view: measured SPF (y) against predicted (x). ΔT alone is plotted against ΔT in K with its fit line.
function scatter(){
  const isDT=pred==='dt',keys=pred==='all'?['mcs','scop','dt']:[pred],multi=keys.length>1;
  const ser=keys.map(k=>({k,p:PRED[k],S:D.filter(d=>d[PRED[k].k]>0)}));
  legend.innerHTML=ser.map(s=>sw(css(s.p.col),'c',multi?`${s.p.name} (${s.S.length})`:`${s.S.length} systems`)).join('')+(isDT?sw(css('--axis'),'line','Fit: SPF = '+f2(icpt)+' − '+Math.abs(slope).toFixed(4)+' × ΔT'):sw(css('--axis'),'line','Measured = predicted'))+sw(css('--axis'),'dash','±10%')+(isDT?'':`<span style="color:var(--faint)">Shaded = beat the prediction</span>`);
  const y0=1.8,y1=6.2,x0=isDT?8:1.8,x1=isDT?43:6.2,W=chart.clientWidth;
  const r=multi?Math.max(3,Math.min(5,W/240)):Math.max(4,Math.min(7,W/170)),op=multi?.6:.72;
  const ds=ser.map((s,i)=>{const c=css(s.p.col);return{type:'scatter',order:ser.length-i,
    data:s.S.map(d=>({x:isDT?d[6]:d[s.p.k],y:d[3],d})).filter(p=>p.x>=x0&&p.x<=x1),
    pointRadius:r,pointHoverRadius:r*1.6,pointBackgroundColor:alpha(c,op),pointHoverBackgroundColor:c,
    pointBorderColor:css('--ring'),pointHoverBorderColor:css('--ring'),pointBorderWidth:1,pointHoverBorderWidth:1}});
  OV=isDT?{lines:[{f:fit,w:2},{f:v=>fit(v)*1.1,w:1.2,dash:1},{f:v=>fit(v)*.9,w:1.2,dash:1}],r,
           labels:D.filter(d=>d[3]/d[7]<.75).map(d=>({x:d[6],y:d[3],t:d[1]}))}
         :{band:[[y0,y0],[y1,y1],[y0,y1]],lines:[{f:v=>v,w:2},{f:v=>v*1.1,w:1.2,dash:1},{f:v=>v*.9,w:1.2,dash:1}]};
  const tick=isDT?{}:{callback:v=>v.toFixed(1)};
  draw('scatter',ds,{
    x:axis(isDT?'Heat-weighted flow − outside temperature (K) · lower is better':multi?'Predicted SPF':PRED[pred].name+' (predicted SPF)',
      {type:'linear',min:x0,max:x1,afterBuildTicks:roundTicks(isDT?5:.5),ticks:Object.assign({color:css('--muted'),font:{size:14}},tick)}),
    y:axis('Measured SPF',{min:y0,max:y1,afterBuildTicks:roundTicks(.5),ticks:{color:css('--muted'),font:{size:14},callback:v=>v.toFixed(1)}})},
    {mode:'nearest',intersect:true},el=>cj.data.datasets[el.datasetIndex].data[el.index].d);
}

function render(){
  hideTip();
  document.querySelectorAll('#pred button').forEach(b=>{const on=b.dataset.p===pred;b.classList.toggle('on',on);b.setAttribute('aria-pressed',on)});
  document.querySelectorAll('#mode button').forEach(b=>{const on=b.dataset.m===mode;b.classList.toggle('on',on);b.setAttribute('aria-pressed',on)});
  document.getElementById('sub').textContent=SUB[mode][pred]();
  document.getElementById('foot').textContent=FOOT[mode][pred];
  document.querySelectorAll('.kpi').forEach(k=>{const sel=k.dataset.k===pred;k.classList.toggle('dim',pred!=='all'&&!sel);k.classList.toggle('sel',sel);k.setAttribute('aria-pressed',sel)});
  (mode==='gap'?gaps:scatter)();
}
const setPred=p=>{pred=p;render()}, setMode=m=>{mode=m;render()};
document.querySelectorAll('#pred button').forEach(b=>b.addEventListener('click',()=>setPred(b.dataset.p)));
document.querySelectorAll('#mode button').forEach(b=>b.addEventListener('click',()=>setMode(b.dataset.m)));
document.querySelectorAll('.kpi').forEach(k=>{const go=()=>setPred(pred===k.dataset.k?'all':k.dataset.k);k.addEventListener('click',go);k.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();e.stopPropagation();go()}})});
document.addEventListener('keydown',e=>{if(e.metaKey||e.ctrlKey||e.altKey)return;
  if(e.target.closest&&e.target.closest('input,select,textarea,nav,.dropdown-menu'))return;
  if(e.target.closest&&e.target.closest('button,.kpi')&&(e.key===' '||e.key==='Enter'))return;
  const i=ORDER.indexOf(pred);
  if(e.key==='Escape')setPred('all');
  else if(e.key>='1'&&e.key<='4')setPred(ORDER[+e.key-1]);
  else if(e.key==='ArrowRight'){e.preventDefault();setPred(ORDER[(i+1)%4])}
  else if(e.key==='ArrowLeft'){e.preventDefault();setPred(ORDER[(i+3)%4])}
  else if(e.key==='m'||e.key==='M')setMode(mode==='gap'?'scatter':'gap');
});
let rt;window.addEventListener('resize',()=>{clearTimeout(rt);rt=setTimeout(render,80)});
render();
})();
</script>
