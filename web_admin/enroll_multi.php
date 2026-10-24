<!-- 3-Step Enrollment UI -->
<div class="card border-0 shadow-glow">
    <div class="card-body p-4">
        <h4 class="mb-4">Đăng Ký Mống Mắt (3 Mẫu)</h4>
        
        <!-- Progress Stepper -->
        <div class="enrollment-stepper mb-4">
            <div class="step active" data-step="1">
                <div class="step-circle">1</div>
                <span>Mẫu 1</span>
            </div>
            <div class="step" data-step="2">
                <div class="step-circle">2</div>
                <span>Mẫu 2</span>
            </div>
            <div class="step" data-step="3">
                <div class="step-circle">3</div>
                <span>Hoàn Tất</span>
            </div>
        </div>
        
        <!-- Capture Area -->
        <div id="capture-area">
            <video id="video" autoplay></video>
            <canvas id="canvas" style="display:none"></canvas>
            <div id="preview"></div>
        </div>
        
        <button class="btn btn-primary" onclick="captureTemplate()">Chụp Mẫu</button>
        <div id="status"></div>
    </div>
</div>

<script>
let currentStep = 1;
let empId = <?= $emp_id ?>;
let templates = [];

async function captureTemplate() {
    const canvas = document.getElementById('canvas');
    const video = document.getElementById('video');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    
    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg'));
    const formData = new FormData();
    formData.append('file', blob);
    formData.append('emp_id', empId);
    formData.append('template_index', currentStep);
    
    const res = await fetch('http://localhost:8000/api/enroll_multi', {
        method: 'POST',
        body: formData
    });
    
    const data = await res.json();
    
    if (data.success && data.status === 'ACTIVE') {
        templates.push(data);
        currentStep++;
        
        if (currentStep <= 3) {
            updateStepper();
        } else {
            await finalizeEnrollment();
        }
    }
}

async function finalizeEnrollment() {
    const res = await fetch('enrollment_handler.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            emp_id: empId,
            templates: templates
        })
    });
    
    if (res.ok) {
        alert('Đăng ký thành công!');
        location.href = 'employees.php';
    }
}

function updateStepper() {
    document.querySelectorAll('.step').forEach((el, i) => {
        el.classList.toggle('active', i + 1 === currentStep);
    });
}

// Start webcam
navigator.mediaDevices.getUserMedia({video: true})
    .then(stream => document.getElementById('video').srcObject = stream);
</script>

<style>
.enrollment-stepper {
    display: flex;
    justify-content: space-around;
    padding: 20px 0;
}
.step {
    text-align: center;
    opacity: 0.5;
}
.step.active {
    opacity: 1;
}
.step-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--accent-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-weight: bold;
}
#capture-area {
    position: relative;
    width: 100%;
    max-width: 640px;
    margin: 0 auto 20px;
}
#video {
    width: 100%;
    border-radius: 12px;
}
</style>
