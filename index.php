<?php
$config = require __DIR__ . '/config.php';
$interfaces = $config['interfaces'] ?? [];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MikroTik Traffic Monitor</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{--bg:#07111f;--panel:#0f1d31;--panel2:#13243b;--line:#20344f;--text:#e7eef8;--muted:#8ea2bd;--green:#32d583;--red:#ff6b6b;--blue:#5ea7ff;--cyan:#2dd4bf}
*{box-sizing:border-box}body{margin:0;background:linear-gradient(135deg,#06101d,#0b1728);color:var(--text);font-family:Inter,Arial,sans-serif}
.container{max-width:1400px;margin:auto;padding:18px}.top{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-bottom:18px}.title h1{font-size:24px;margin:0 0 5px}.title p{margin:0;color:var(--muted);font-size:13px}.actions{display:flex;gap:8px;flex-wrap:wrap}.btn{border:1px solid var(--line);background:var(--panel2);color:var(--text);padding:10px 13px;border-radius:9px;cursor:pointer}.btn:hover{filter:brightness(1.15)}.btn.primary{background:#1769aa;border-color:#2784ca}.status{display:inline-flex;align-items:center;gap:7px;font-size:12px;color:var(--muted)}.dot{width:9px;height:9px;border-radius:50%;background:#777}.dot.ok{background:var(--green);box-shadow:0 0 9px #32d583}.dot.bad{background:var(--red)}
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px}.card,.panel{background:rgba(15,29,49,.94);border:1px solid var(--line);border-radius:13px;box-shadow:0 8px 30px rgba(0,0,0,.16)}.card{padding:16px}.card .small{color:var(--muted);font-size:12px}.value{font-size:23px;font-weight:700;margin-top:7px}.sub{font-size:11px;color:var(--muted);margin-top:5px}.grid{display:grid;grid-template-columns:2fr 1fr;gap:14px}.panel{padding:16px}.panel h2{font-size:15px;margin:0 0 12px}.chart-wrap{height:330px}.interfaces{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.iface{background:#0b1829;border:1px solid var(--line);border-radius:11px;padding:13px}.iface-head{display:flex;justify-content:space-between;gap:8px}.iface-name{font-weight:700}.role{font-size:10px;text-transform:uppercase;color:var(--muted);margin-top:3px}.metrics{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}.metric{background:#101f33;padding:9px;border-radius:8px}.metric b{display:block;font-size:15px;margin-top:3px}.metric span{font-size:10px;color:var(--muted)}.running{color:var(--green)}.down{color:var(--red)}.router p{margin:8px 0;color:var(--muted);font-size:12px}.router b{color:var(--text)}.table{width:100%;border-collapse:collapse;font-size:12px}.table th,.table td{text-align:left;padding:9px;border-bottom:1px solid var(--line)}.muted{color:var(--muted)}.modal{position:fixed;inset:0;background:rgba(0,0,0,.65);display:none;align-items:center;justify-content:center;padding:16px;z-index:20}.modal.show{display:flex}.dialog{width:min(850px,100%);max-height:90vh;overflow:auto;background:#0d1b2d;border:1px solid var(--line);border-radius:15px;padding:18px}.dialog-head{display:flex;justify-content:space-between;align-items:center}.dialog h2{margin:0}.hint{color:var(--muted);font-size:12px}.list{margin-top:12px}.row{display:grid;grid-template-columns:1.5fr 1fr 120px 80px;gap:8px;align-items:center;padding:9px 0;border-bottom:1px solid var(--line)}.row input,.row select{width:100%;background:#081523;color:var(--text);border:1px solid var(--line);padding:8px;border-radius:7px}.check{width:18px;height:18px}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:15px}.toast{position:fixed;right:18px;bottom:18px;background:#10253d;border:1px solid var(--line);padding:11px 14px;border-radius:9px;display:none;z-index:30;font-size:13px}.toast.show{display:block}
@media(max-width:1000px){.cards{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}.chart-wrap{height:280px}}
@media(max-width:650px){.container{padding:11px}.top{align-items:flex-start;flex-direction:column}.cards{grid-template-columns:1fr 1fr;gap:8px}.card{padding:12px}.value{font-size:18px}.interfaces{grid-template-columns:1fr}.row{grid-template-columns:1fr 1fr 95px 40px}.row .type{display:none}.chart-wrap{height:230px}}
</style>
</head>
<body>
<div class="container">
  <div class="top">
    <div class="title"><h1>📡 System Monitor Traffic</h1><p id="routerLine">by HERY BTR</p></div>
    <div class="actions"><span class="status"><i id="dot" class="dot"></i><span id="status">Connecting...</span></span><button class="btn primary" onclick="openManager()">⚙ Interface</button><button class="btn" onclick="loadData()">↻ Refresh</button></div>
  </div>

  <div class="cards">
    <div class="card"><div class="small">SOURCE DOWNLOAD</div><div id="sourceRx" class="value">0 Mbps</div><div class="sub">Total interface role source</div></div>
    <div class="card"><div class="small">SOURCE UPLOAD</div><div id="sourceTx" class="value">0 Mbps</div><div class="sub">Total interface role source</div></div>
    <div class="card"><div class="small">OUTPUT DOWNLOAD</div><div id="outputRx" class="value">0 Mbps</div><div class="sub">Total interface role output</div></div>
    <div class="card"><div class="small">OUTPUT UPLOAD</div><div id="outputTx" class="value">0 Mbps</div><div class="sub">Total interface role output</div></div>
  </div>

  <div class="grid">
    <div class="panel"><h2>Traffic Realtime</h2><div class="chart-wrap"><canvas id="chart"></canvas></div></div>
    <div class="panel router"><h2>Router</h2><p>Identity: <b id="identity">-</b></p><p>IP: <b id="host">-</b></p><p>RouterOS: <b id="version">-</b></p><p>Board: <b id="board">-</b></p><p>Architecture: <b id="architecture">-</b></p><p>Uptime: <b id="uptime">-</b></p><p>CPU: <b id="cpu">-</b></p><p>Memory: <b id="memory">-</b></p><p>Update: <b id="updated">-</b></p></div>
  </div>

  <div class="panel" style="margin-top:14px"><h2>Interface Monitoring</h2><div id="interfaces" class="interfaces"><div class="muted">Memuat...</div></div></div>
</div>

<div id="modal" class="modal">
 <div class="dialog">
  <div class="dialog-head"><div><h2>⚙ Kelola Interface</h2><div class="hint">Centang interface yang ingin dimonitor. Nama diambil langsung dari MikroTik.</div></div><button class="btn" onclick="closeManager()">✕</button></div>
  <div class="list" id="interfaceList">Memuat interface MikroTik...</div>
  <div class="modal-actions"><button class="btn" onclick="closeManager()">Batal</button><button class="btn primary" onclick="saveInterfaces()">💾 Simpan & Monitor</button></div>
 </div>
</div>
<div id="toast" class="toast"></div>

<script>
const initialConfig = <?=json_encode($interfaces, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
let chart;
const labels=[], sourceRx=[], sourceTx=[], outputRx=[], outputTx=[];

function fmt(b){b=Number(b)||0; if(b>=1e9)return (b/1e9).toFixed(2)+' Gbps'; if(b>=1e6)return (b/1e6).toFixed(2)+' Mbps'; if(b>=1e3)return (b/1e3).toFixed(2)+' Kbps'; return b.toFixed(0)+' bps'}
function fmtMem(n){if(!n)return '-'; return (n/1024/1024).toFixed(0)+' MB'}
function toast(t){const e=document.getElementById('toast');e.textContent=t;e.classList.add('show');setTimeout(()=>e.classList.remove('show'),2600)}
function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}

function initChart(){
 const ctx=document.getElementById('chart').getContext('2d');
 chart=new Chart(ctx,{type:'line',data:{labels,datasets:[
  {label:'Source RX',data:sourceRx,tension:.3,borderWidth:2},
  {label:'Source TX',data:sourceTx,tension:.3,borderWidth:2},
  {label:'Output RX',data:outputRx,tension:.3,borderWidth:2},
  {label:'Output TX',data:outputTx,tension:.3,borderWidth:2}
 ]},options:{responsive:true,maintainAspectRatio:false,animation:false,scales:{x:{ticks:{color:'#8ea2bd'}},y:{ticks:{color:'#8ea2bd',callback:v=>fmt(v)}}},plugins:{legend:{labels:{color:'#e7eef8'}}}}});
}
function renderInterfaces(items){
 const el=document.getElementById('interfaces');
 if(!items.length){el.innerHTML='<div class="muted">Belum ada interface yang dimonitor. Klik ⚙ Interface.</div>';return}
 el.innerHTML=items.map(i=>`<div class="iface">
  <div class="iface-head"><div><div class="iface-name">${esc(i.label)}</div><div class="role">${esc(i.name)} · ${esc(i.role)}</div></div><div class="${i.running?'running':'down'}">${i.running?'● RUNNING':'● OFFLINE'}</div></div>
  <div class="metrics"><div class="metric"><span>RX / Download</span><b>${fmt(i.rx)}</b><span>${Number(i.rx_packets||0).toLocaleString()} pps</span></div><div class="metric"><span>TX / Upload</span><b>${fmt(i.tx)}</b><span>${Number(i.tx_packets||0).toLocaleString()} pps</span></div></div>
 </div>`).join('');
}
async function loadData(){
 try{
  const res=await fetch('api.php?action=traffic',{cache:'no-store'}); const d=await res.json();
  if(!d.ok) throw new Error(d.error||'API error');
  document.getElementById('dot').className='dot ok';document.getElementById('status').textContent='Connected';
  document.getElementById('identity').textContent=d.router.identity||'-';document.getElementById('host').textContent=d.router.host||'-';document.getElementById('version').textContent=d.router.version||'-';document.getElementById('board').textContent=d.router.board||'-';document.getElementById('architecture').textContent=d.router.architecture||'-';document.getElementById('uptime').textContent=d.router.uptime||'-';
  document.getElementById('cpu').textContent=d.router.cpu===null?'-':d.router.cpu+'%';
  document.getElementById('memory').textContent=d.router.memory_total?fmtMem(d.router.memory_total-d.router.memory_free)+' / '+fmtMem(d.router.memory_total):'-';
  document.getElementById('updated').textContent=new Date().toLocaleTimeString();
  const s=d.summary||{}; document.getElementById('sourceRx').textContent=fmt(s.source_rx);document.getElementById('sourceTx').textContent=fmt(s.source_tx);document.getElementById('outputRx').textContent=fmt(s.output_rx);document.getElementById('outputTx').textContent=fmt(s.output_tx);
  renderInterfaces(d.interfaces||[]);
  labels.push(new Date().toLocaleTimeString());sourceRx.push(s.source_rx||0);sourceTx.push(s.source_tx||0);outputRx.push(s.output_rx||0);outputTx.push(s.output_tx||0);
  if(labels.length>30){labels.shift();sourceRx.shift();sourceTx.shift();outputRx.shift();outputTx.shift()} chart.update();
 }catch(e){document.getElementById('dot').className='dot bad';document.getElementById('status').textContent='Error';toast(e.message)}
}
async function openManager(){
 document.getElementById('modal').classList.add('show');
 try{
  const res=await fetch('api.php?action=interfaces',{cache:'no-store'});const d=await res.json();
  if(!d.ok)throw new Error(d.error||'Gagal mengambil interface');
  const selected=d.monitored||{}; 
  document.getElementById('interfaceList').innerHTML=`<div class="row" style="font-weight:700"><div>Interface</div><div>Nama Dashboard</div><div>Role</div><div>Monitor</div></div>`+
   d.interfaces.map(x=>{const m=selected[x.name]||{};return `<div class="row" data-name="${esc(x.name)}"><div><b>${esc(x.name)}</b><div class="hint">${x.running?'RUNNING':'OFFLINE'} · ${esc(x.type)}</div></div><div><input class="label" value="${esc(m.label||x.name)}"></div><div><select class="role"><option value="source" ${m.role==='source'?'selected':''}>Source</option><option value="output" ${m.role==='output'?'selected':''}>Output</option><option value="other" ${(!m.role||m.role==='other')?'selected':''}>Other</option></select></div><div style="text-align:center"><input class="check" type="checkbox" ${selected[x.name]?'checked':''}></div></div>`}).join('');
 }catch(e){document.getElementById('interfaceList').innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
}
function closeManager(){document.getElementById('modal').classList.remove('show')}
async function saveInterfaces(){
 const out={};document.querySelectorAll('#interfaceList .row[data-name]').forEach(r=>{const c=r.querySelector('.check');if(c.checked){out[r.dataset.name]={label:r.querySelector('.label').value.trim()||r.dataset.name,role:r.querySelector('.role').value}}});
 try{
  const res=await fetch('api.php?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({interfaces:out})});const d=await res.json();
  if(!d.ok)throw new Error(d.error||'Gagal menyimpan');toast('Konfigurasi berhasil disimpan');closeManager();setTimeout(loadData,250);
 }catch(e){toast(e.message)}
}
initChart();loadData();setInterval(loadData,2000);
</script>
</body>
</html>
