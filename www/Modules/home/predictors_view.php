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
    --c-dt: var(--hpm-ink);
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
.hpm-pred #chart svg { position: absolute; inset: 0; width: 100%; height: 100%; }
.hpm-pred #chart .empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: var(--muted); }
.hpm-pred #tip { position: absolute; pointer-events: none; background: var(--hpm-card); border: 1px solid var(--hpm-line); border-radius: .75rem; padding: 12px 14px; font-size: 14px; line-height: 1.5; color: var(--hpm-ink-soft); box-shadow: 0 12px 32px var(--shadow-strong); opacity: 0; transition: opacity .1s; z-index: 5; min-width: 250px; font-variant-numeric: tabular-nums; }
.hpm-pred #tip .h { font-weight: 700; font-size: 15px; color: var(--hpm-ink); }
.hpm-pred #tip .m { color: var(--muted); font-size: 13px; }
.hpm-pred #tip .row { display: flex; justify-content: space-between; gap: 16px; margin: 0; }
.hpm-pred #tip i { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
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

<script>
var path = "<?php echo $path; ?>";

// Systems are embedded server-side rather than fetched over ajax, for a
// faster initial load (same as the flowtemp and economics pages).
// The same data is available as JSON at home/predictors_systems.
var predictors_systems = <?php echo json_encode($predictors_systems); ?>;

// [id, location, make & model, measured combined SPF, MCS 031 SPF (0 = none), manufacturer SCOP (0 = none), weighted flowT − outsideT (K)]
const D=predictors_systems.map(s=>[s.id,(s.location||'').split(',')[0].trim(),((s.hp_manufacturer||'')+' '+(s.hp_model||'')).trim(),s.cop,s.mcs031_spf||0,s.scop_spf||0,s.dt]).sort((a,b)=>b[3]-a[3]);

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
const f2=v=>isFinite(v)?v.toFixed(2):'–', sg=v=>isFinite(v)?(v>=0?'+':'−')+Math.abs(v).toFixed(2):'–', pc=(a,b)=>{const p=Math.round((a/b-1)*100);return (p>=0?'+':'−')+Math.abs(p)+'%'};

const KP=[
 {key:'mcs',col:'--c-mcs',l:'MCS 031 SPF',v:()=>`R² = ${f2(ST.mcs.r**2)}`,s:()=>`RMSE ${f2(ST.mcs.rmse)} · ${ST.mcs.w10} of ${ST.mcs.n} within ±10%<br>${ST.mcs.beat} of ${ST.mcs.n} beat it · mean ${sg(ST.mcs.bias)}`},
 {key:'scop',col:'--c-scop',l:'Manufacturer SCOP',v:()=>`R² = ${f2(ST.scop.r**2)}`,s:()=>`RMSE ${f2(ST.scop.rmse)} · ${ST.scop.w10} of ${ST.scop.n} within ±10%<br>${ST.scop.beat} of ${ST.scop.n} beat it · mean ${sg(ST.scop.bias)}`},
 {key:'dt',col:'--c-dt',l:'Flow − outside ΔT (fitted)',v:()=>`R² = ${f2(ST.dt.r**2)}`,s:()=>`RMSE ${f2(ST.dt.rmse)} · ${ST.dt.w10} of ${ST.dt.n} within ±10%<br>SPF = ${f2(icpt)} − ${Math.abs(slope).toFixed(4)} × ΔT`}
];
document.getElementById('kpis').innerHTML=KP.map(k=>`<div class="kpi" data-k="${k.key}" style="--k:var(${k.col})" role="button" tabindex="0" title="Show only this comparison"><p class="l">${k.l}</p><p class="v">${k.v()}</p><p class="s">${k.s()}</p></div>`).join('');

const PRED={mcs:{k:4,col:'--c-mcs',name:'MCS 031',sh:'s'},scop:{k:5,col:'--c-scop',name:'Manufacturer SCOP',sh:'t'},dt:{k:7,col:'--c-dt',name:'ΔT fit',sh:'d'}};
const ORDER=['all','mcs','scop','dt'];
let pred='all',mode='gap',W=1000,H=500;
const P={l:64,r:20,t:14,b:52};
const NS='http://www.w3.org/2000/svg';
const el=(t,a)=>{const e=document.createElementNS(NS,t);for(const k in a)e.setAttribute(k,a[k]);return e};
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
function text(svg,x,y,s,a){const t=el('text',Object.assign({x,y,'font-size':14,fill:css('--muted')},a||{}));t.textContent=s;svg.append(t);return t}
function frame(){const r=chart.getBoundingClientRect();W=Math.max(400,r.width);H=Math.max(260,r.height);return el('svg',{viewBox:`0 0 ${W} ${H}`,role:'img'})}
function yAxis(svg,y0,y1,label){const ys=v=>P.t+(H-P.t-P.b)*(1-(v-y0)/(y1-y0));for(let v=Math.ceil(y0*2)/2;v<=y1+1e-9;v+=.5){svg.append(el('line',{x1:P.l,x2:W-P.r,y1:ys(v),y2:ys(v),stroke:css('--grid'),'stroke-width':1}));text(svg,P.l-10,ys(v)+5,v.toFixed(1),{'text-anchor':'end'})}const cy=P.t+(H-P.t-P.b)/2;text(svg,18,cy,label,{transform:`rotate(-90 18 ${cy})`,'text-anchor':'middle','font-size':15});return ys}
function mark(g,sh,x,y,sz,c){
  if(sh==='s')g.append(el('rect',{x:x-sz,y:y-sz,width:sz*2,height:sz*2,fill:c}));
  else if(sh==='t')g.append(el('rect',{x:x-sz*1.6,y:y-sz*.45,width:sz*3.2,height:sz*.9,fill:c}));
  else if(sh==='d')g.append(el('path',{d:`M${x} ${y-sz*1.4}L${x+sz*1.2} ${y}L${x} ${y+sz*1.4}L${x-sz*1.2} ${y}Z`,fill:c}));
  else g.append(el('circle',{cx:x,cy:y,r:sz*1.2,fill:c}));
}

function showTip(d,ev){
  const f=d[7];
  const row=(c,l,v,p)=>`<div class="row"><span><i style="background:${css(c)}"></i>${l}</span><span>${v}${p?` <span class="m">${p}</span>`:''}</span></div>`;
  tip.innerHTML=`<div class="h">${d[1]}</div><div class="m">${d[2]} · id ${d[0]}</div><div style="margin-top:6px">`+
   row('--c-meas','Measured SPF',f2(d[3]))+
   (d[4]?row('--c-mcs','MCS 031',f2(d[4]),pc(d[3],d[4])):`<div class="row m"><span>No MCS 031 estimate</span></div>`)+
   (d[5]?row('--c-scop','SCOP',f2(d[5]),pc(d[3],d[5])):`<div class="row m"><span>No SCOP estimate</span></div>`)+
   row('--c-dt',`ΔT ${d[6].toFixed(1)} K → fit`,f2(f),pc(d[3],f))+`</div>`;
  const pr=panel.getBoundingClientRect();let x=ev.clientX-pr.left+16,y=ev.clientY-pr.top+16;
  tip.style.opacity=1;const tw=tip.offsetWidth,th=tip.offsetHeight;
  if(x+tw>pr.width-8)x=ev.clientX-pr.left-tw-16; if(y+th>pr.height-8)y=ev.clientY-pr.top-th-16;
  tip.style.left=x+'px';tip.style.top=y+'px';
}
const hideTip=()=>tip.style.opacity=0;
const open=d=>{window.location=path+'system/view?id='+d[0]};

// Gap view: one vertical thread per system, sorted by measured SPF
function gaps(){
  const fp=PRED[pred],S=fp?D.filter(d=>d[fp.k]>0):D;
  legend.innerHTML=fp?sw(css('--c-meas'),'c','Measured SPF')+sw(css(fp.col),fp.sh,fp.name)+sw(css('--thread'),'line','Beat it')+sw(css(fp.col),'line','Fell short')
    :sw(css('--c-meas'),'c','Measured SPF')+sw(css('--c-mcs'),'s','MCS 031')+sw(css('--c-scop'),'t','Manufacturer SCOP')+sw(css('--c-dt'),'d','ΔT-predicted SPF')+`<span style="color:var(--faint)">Amber thread = fell short of MCS 031</span>`;
  const svg=frame(),ys=yAxis(svg,1.5,6.5,'SPF'),n=S.length,iw=W-P.l-P.r-12,xs=i=>P.l+6+iw*i/Math.max(1,n-1),sz=Math.max(1,Math.min(fp?3:2.2,iw/n*(fp?.6:.55)));
  text(svg,P.l+(W-P.l-P.r)/2,H-14,`${n} systems, sorted by measured SPF (highest left)`,{'text-anchor':'middle','font-size':15});
  const g=el('g',{});svg.append(g);
  S.forEach((d,i)=>{const x=xs(i),m=d[3];
    if(fp){const v=d[fp.k];
      g.append(el('line',{x1:x,x2:x,y1:ys(Math.min(m,v)),y2:ys(Math.max(m,v)),stroke:m<v?css(fp.col):css('--thread'),'stroke-width':Math.max(1,sz*.8)}));
      mark(g,fp.sh,x,ys(v),sz,css(fp.col));}
    else{
      if(d[4]){g.append(el('line',{x1:x,x2:x,y1:ys(Math.min(m,d[4])),y2:ys(Math.max(m,d[4])),stroke:m<d[4]?css('--c-mcs'):css('--thread'),'stroke-width':sz*.7}));mark(g,'s',x,ys(d[4]),sz,css('--c-mcs'));}
      if(d[5])mark(g,'t',x,ys(d[5]),sz,css('--c-scop'));
      mark(g,'d',x,ys(d[7]),sz,css('--c-dt'));}
    mark(g,'c',x,ys(m),sz,css('--c-meas'));});
  const bw=Math.max(3,iw/n),hl=el('rect',{x:0,y:P.t,width:bw,height:H-P.t-P.b,fill:css('--accent'),opacity:0});svg.insertBefore(hl,g);
  let cur=null;
  svg.addEventListener('mousemove',e=>{const r=svg.getBoundingClientRect(),x=(e.clientX-r.left)*W/r.width;if(x<P.l||x>W-P.r){hl.setAttribute('opacity',0);hideTip();cur=null;return}const i=Math.max(0,Math.min(n-1,Math.round((x-P.l-6)/iw*Math.max(1,n-1))));cur=S[i];hl.setAttribute('x',xs(i)-bw/2);hl.setAttribute('opacity',.15);showTip(cur,e)});
  svg.addEventListener('mouseleave',()=>{hl.setAttribute('opacity',0);hideTip();cur=null});
  svg.addEventListener('click',()=>cur&&open(cur));svg.style.cursor='pointer';
  chart.replaceChildren(svg);
}

// Scatter view: measured SPF (y) against predicted (x). ΔT alone is plotted against ΔT in K with its fit line.
function scatter(){
  const isDT=pred==='dt',keys=pred==='all'?['mcs','scop','dt']:[pred],multi=keys.length>1;
  const ser=keys.map(k=>({k,p:PRED[k],S:D.filter(d=>d[PRED[k].k]>0)}));
  legend.innerHTML=ser.map(s=>sw(css(s.p.col),'c',multi?`${s.p.name} (${s.S.length})`:`${s.S.length} systems`)).join('')+(isDT?sw(css('--axis'),'line','Fit: SPF = '+f2(icpt)+' − '+Math.abs(slope).toFixed(4)+' × ΔT'):sw(css('--axis'),'line','Measured = predicted'))+sw(css('--axis'),'dash','±10%')+(isDT?'':`<span style="color:var(--faint)">Shaded = beat the prediction</span>`);
  const svg=frame(),y0=1.8,y1=6.2,ys=yAxis(svg,y0,y1,'Measured SPF');
  const x0=isDT?8:1.8,x1=isDT?43:6.2,xs=v=>P.l+(W-P.l-P.r)*(v-x0)/(x1-x0);
  const step=isDT?5:.5;for(let v=Math.ceil(x0/step)*step;v<=x1+1e-9;v+=step)text(svg,xs(v),H-P.b+20,isDT?v:v.toFixed(1),{'text-anchor':'middle'});
  text(svg,P.l+(W-P.l-P.r)/2,H-8,isDT?'Heat-weighted flow − outside temperature (K) · lower is better':multi?'Predicted SPF':PRED[pred].name+' (predicted SPF)',{'text-anchor':'middle','font-size':15});
  const g=el('g',{});svg.append(g);
  if(isDT){[1,1.1,.9].forEach(k=>g.append(el('line',{x1:xs(x0),y1:ys(fit(x0)*k),x2:xs(x1),y2:ys(fit(x1)*k),stroke:css('--axis'),'stroke-width':k===1?2:1.2,'stroke-dasharray':k===1?'':'5 5'})))}
  else{g.append(el('polygon',{points:`${xs(y0)},${ys(y0)} ${xs(y1)},${ys(y1)} ${xs(y0)},${ys(y1)}`,fill:css('--band')}));
    g.append(el('line',{x1:xs(y0),y1:ys(y0),x2:xs(y1),y2:ys(y1),stroke:css('--axis'),'stroke-width':2}));
    [1.1,.9].forEach(k=>g.append(el('line',{x1:xs(y0),y1:ys(y0*k),x2:xs(y1),y2:ys(y1*k),stroke:css('--axis'),'stroke-width':1.2,'stroke-dasharray':'5 5'})))}
  const r=multi?Math.max(3,Math.min(5,W/240)):Math.max(4,Math.min(7,W/170)),op=multi?.6:.72;
  ser.forEach(s=>s.S.forEach(d=>{const xv=isDT?d[6]:d[s.p.k];if(xv<x0||xv>x1)return;
    const c=el('circle',{cx:xs(xv),cy:ys(d[3]),r,fill:css(s.p.col),'fill-opacity':op,stroke:css('--ring'),'stroke-width':1,style:'cursor:pointer'});
    c.addEventListener('mouseenter',e=>{c.setAttribute('r',r*1.6);c.setAttribute('fill-opacity',1);showTip(d,e)});
    c.addEventListener('mousemove',e=>showTip(d,e));
    c.addEventListener('mouseleave',()=>{c.setAttribute('r',r);c.setAttribute('fill-opacity',op);hideTip()});
    c.addEventListener('click',()=>open(d));g.append(c)}));
  if(isDT){D.filter(d=>d[3]/d[7]<.75).forEach(d=>text(svg,xs(d[6])+r+6,ys(d[3])+5,d[1],{'font-size':13}))}
  chart.replaceChildren(svg);
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
