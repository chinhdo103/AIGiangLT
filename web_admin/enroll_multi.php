<?php
require_once 'includes/header.php';
require_once 'auth_guard.php';
$emp_id = $_GET['emp_id'] ?? $_SESSION['emp_id'] ?? 0;
?>
<style>
.enroll-step{display:flex;justify-content:space-around;padding:20px 0}.step{text-align:center;opacity:.4;transition:.3s}.step.active{opacity:1}.step.done .step-circle{background:var(--success-color,#22C55E)!important}.step-circle{width:56px;height:56px;border-radius:50%;background:var(--accent-color,#38BDF8);color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-weight:700;font-size:20px;box-shadow:0 0 20px rgba(56,189,248,.4)}.step.done .step-circle::after{content:"✓";font-size:24px}.step.done .step-circle{font-size:0}#capture-area{position:relative;max-width:640px;margin:0 auto 20px;text-align:center}#video{width:100%;border-radius:12px;background:#000}#preview{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:16px}.preview-thumb{width:120px;height:90px;border-radius:8px;object-fit:cover;border:2px solid var(--accent-color,#38BDF8)}.preview-thumb.pending{opacity:.3;border-style:dashed}.status-msg{margin-top:16px;padding:12px;border-radius:8px;text-align:center}.status-msg.ok{background:rgba(34,197,94,.15);color:#22C55E}.status-msg.err{background:rgba(239,68,68,.15);color:#EF4444}.scanner-ov{position:absolute;top:0;left:50%;transform:translateX(-50%);width:300px;height:300px;border:2px solid var(--accent-color);border-radius:50%;pointer-events:none;animation:pulse 2s infinite}@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
</style>

<div class="container-fluid mt-4"><div class="row"><div class="col-lg-8 mx-auto"><div class="card border-0 shadow-glow"><div class="card-body p-4">
<h4 class="mb-3">Đăng Ký Mống Mắt (3 Mẫu)</h4>
<p class="text-muted mb-4">Nhân viên #<?= htmlspecialchars($emp_id) ?> &middot; AI: <code id="ai-url">127.0.0.1:8000</code></p>

<div class="enroll-step mb-4">
    <div class="step active" data-step="1"><div class="step-circle">1</div><span>Mẫu 1</span></div>
    <div class="step" data-step="2"><div class="step-circle">2</div><span>Mẫu 2</span></div>
    <div class="step" data-step="3"><div class="step-circle">3</div><span>Hoàn Tất</span></div>
</div>

<div id="capture-area">
    <video id="video" autoplay playsinline></video>
    <canvas id="canvas" style="display:none"></canvas>
    <div class="scanner-ov" id="scanner" style="display:none"></div>
</div>

<div id="preview"></div>

<div class="d-flex gap-2 mt-3 justify-content-center">
    <button class="btn btn-primary" id="btn-cap" onclick="capture()"><span id="btn-txt">📷 Chụp Mẫu 1</span></button>
    <button class="btn btn-outline-secondary" onclick="location.href='employees.php'">Hủy</button>
</div>
<div id="status"></div>
</div></div></div></div></div>

<script>
const AI='http://127.0.0.1:8000',eid=<?=json_encode((int)$emp_id)?>;
let step=1,tpls=[],vs=null;

async function init(){try{vs=await navigator.mediaDevices.getUserMedia({video:{width:640,height:480}});document.getElementById('video').srcObject=vs;document.getElementById('scanner').style.display='block';}catch(e){msg('❌ Camera: '+e.message,'err')}}

function msg(t,c){document.getElementById('status').innerHTML=`<div class="status-msg ${c}">${t}</div>`}

function renderPrev(){const p=document.getElementById('preview');p.innerHTML='';for(let i=1;i<=3;i++){const t=tpls[i-1],img=document.createElement('img');if(t){img.src=t.thumb;img.className='preview-thumb';img.title=`Mẫu ${i} Q:${(t.quality_score||0).toFixed(1)}`}else{img.className='preview-thumb pending';img.alt=`Mẫu ${i} chưa`}p.appendChild(img)}}

async function capture(){
    const btn=document.getElementById('btn-cap'),btxt=document.getElementById('btn-txt');
    btn.disabled=true;btxt.textContent='⏳ Xử lý...';
    const v=document.getElementById('video'),c=document.getElementById('canvas');
    c.width=v.videoWidth||640;c.height=v.videoHeight||480;
    c.getContext('2d').drawImage(v,0,0);
    const thumb=c.toDataURL('image/jpeg',.5);
    try{
        const blob=await new Promise(r=>c.toBlob(r,'image/jpeg',.8));
        const fd=new FormData();
        fd.append('file',blob,'iris.jpg');fd.append('emp_id',eid);fd.append('template_index',step);
        const res=await fetch(`${AI}/api/enroll_multi`,{method:'POST',body:fd});
        if(!res.ok)throw new Error('HTTP '+res.status);
        const d=await res.json();
        if(d.success&&d.status==='ACTIVE'){
            d.thumb=thumb;tpls.push(d);
            msg(`✅ Mẫu ${step} Q:${d.quality_score.toFixed(1)}`,'ok');
            document.querySelector(`.step[data-step="${step}"]`).classList.add('done');
            step++;renderPrev();
            if(step<=3){
                document.querySelector('.step.active')?.classList.remove('active');
                document.querySelector(`.step[data-step="${step}"]`)?.classList.add('active');
                btxt.textContent=`📷 Chụp Mẫu ${step}`;
            }else{btxt.textContent='💾 Đang lưu...';await finalize()}
        }else{msg(`❌ Mẫu ${step}: ${d.message||'Chất lượng thấp'} Q:${(d.quality_score||0).toFixed(1)}`,'err')}
    }catch(e){
        let hint = '';
        if(e.message.includes('404')) hint = ' → Endpoint không tồn tại. Chạy: START_AI_SERVER.bat hoặc xem FIX_404_ERROR.md';
        else if(e.message.includes('0')) hint = ' → Server chưa chạy. Chạy: START_AI_SERVER.bat';
        msg('❌ AI error: '+e.message + hint,'err');
    }finally{btn.disabled=false}
}

async function finalize(){
    try{
        const res=await fetch('enrollment_handler.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({emp_id:eid,templates:tpls})});
        if(!res.ok)throw new Error('HTTP '+res.status);
        const d=await res.json();
        if(d.success){msg('🎉 Thành công! 3 mẫu đã lưu.','ok');document.getElementById('btn-cap').disabled=true;document.getElementById('btn-txt').textContent='✅ Hoàn tất';setTimeout(()=>location.href='employees.php',2000)}
        else throw new Error(d.error||'Lỗi DB')
    }catch(e){msg('❌ DB: '+e.message,'err');document.getElementById('btn-txt').textContent='🔄 Retry'}
}
renderPrev();init();
</script>
<?php require_once 'includes/footer.php'; ?>
