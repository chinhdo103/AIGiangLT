<?php
/**
 * TRANG ĐĂNG KÝ NHÂN VIÊN MỚI & THU THẬP MỐNG MẮT (GIAO DIỆN SÁNG & TỰ ĐỘNG BẮT ĐIỂM)
 * Bắt buộc quyền Quản trị viên (Admin).
 */
require_once __DIR__ . '/auth_guard.php';
require_admin();

require_once __DIR__ . '/config.php';

$pageTitle = "Đăng Ký Mống Mắt Mới";
$currentPage = "enroll";

// Lấy danh sách phòng lab
$labs = $pdo->query("SELECT * FROM laboratories ORDER BY lab_id ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Tracking.js để tự động nhận diện mắt trên khung hình -->
<script src="assets/js/tracking-min.js"></script>
<script src="assets/js/eye-min.js"></script>

<div class="main-wrapper">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="content-container">

        <!-- Tiêu đề trang -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Đăng Ký Nhân Sự & Thu Thập Mống Mắt</h3>
                <p class="text-secondary small mb-0">Hệ thống AI tự động phát hiện mắt từ khuôn mặt, trích xuất đặc trưng 1280 chiều MobileNetV2</p>
            </div>
            <a href="employees.php" class="btn btn-cyber-secondary">
                <i class="fas fa-users"></i> Xem Danh Sách Nhân Sự
            </a>
        </div>

        <div class="row g-4">
            
            <!-- CỘT TRÁI: FORM ĐIỀN THÔNG TIN -->
            <div class="col-lg-5">
                <div class="glass-card p-4 h-100">
                    <h5 class="fw-bold text-dark mb-3">
                        <i class="fas fa-user-pen text-primary me-2"></i> 1. Thông Tin Hồ Sơ
                    </h5>
                    
                    <form id="enrollForm">
                        <input type="hidden" name="action" value="register">

                        <div class="mb-3">
                            <label class="form-label">Họ và Tên Nhân Viên <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" id="fullname" class="form-control" placeholder="Ví dụ: Đỗ Công Chính" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Doanh Nghiệp <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="chinh.do@lab-security.ai" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-dark fw-bold">
                                <i class="fas fa-shield-alt text-primary me-1"></i> Phân Quyền & Vai Trò (Role)
                            </label>
                            <select name="role" id="role" class="form-select" required>
                                <option value="Nhà nghiên cứu">Nhà nghiên cứu (RESEARCHER) - Vào Cổng User & Phòng Lab</option>
                                <option value="Quản trị viên">Quản trị viên (ADMIN) - Toàn quyền Dashboard & Quản trị</option>
                                <option value="Nhân viên">Nhân viên (STAFF) - Chỉ vào Cửa được cấp phép</option>
                            </select>
                            <div class="text-muted small mt-1">
                                Người có vai trò <strong>Quản trị viên</strong> mới được vào trang Quản trị.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Phòng Lab Được Cấp Phép Ra/Vào</label>
                            <select name="lab_id" id="lab_id" class="form-select">
                                <?php foreach ($labs as $lab): ?>
                                    <option value="<?= $lab['lab_id'] ?>">
                                        <?= htmlspecialchars($lab['lab_name']) ?> (Mức an ninh: <?= $lab['security_level'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Trạng thái ảnh đã chụp -->
                        <div class="p-3 rounded-3 mb-3 small bg-light border" id="photoStatusCard">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted"><i class="fas fa-camera me-1"></i> Mẫu mống mắt:</span>
                                <span class="text-danger fw-semibold" id="photoStatusBadge">
                                    <i class="fas fa-times-circle"></i> Chưa chụp ảnh
                                </span>
                            </div>
                        </div>

                        <!-- Nút Submit Hoàn Tất -->
                        <button type="submit" class="btn btn-cyber-primary w-100 py-3 justify-content-center" id="btnSubmitEnroll">
                            <i class="fas fa-floppy-disk"></i> LƯU HỒ SƠ & TRÍCH XUẤT MỐNG MẮT
                        </button>
                    </form>
                </div>
            </div>

            <!-- CỘT PHẢI: CAMERA CHỤP MỐNG MẮT (KHÔNG CẦN Ô ÉP BUỘC) -->
            <div class="col-lg-7">
                <div class="glass-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-camera text-primary me-2"></i> 2. Thu Thập Mống Mắt (Tự Động Bắt Điểm)
                        </h5>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                            AUTO-CROP SẴN SÀNG
                        </span>
                    </div>

                    <!-- Khung Camera Không Cần Ô Cố Định -->
                    <div class="biometric-reticle-container" style="aspect-ratio: 4 / 3; max-height: 380px;">
                        <video id="enrollWebcam" autoplay playsinline muted></video>
                        <canvas id="enrollOverlayCanvas" class="tracking-canvas-overlay"></canvas>
                        <canvas id="enrollCanvas" style="display: none;"></canvas>

                        <div class="status-pill text-dark" id="enrollCameraStatus">
                            <i class="fas fa-eye text-primary me-1"></i> Đang khởi tạo camera...
                        </div>
                    </div>

                    <!-- Điều khiển Camera -->
                    <div class="mt-3 row g-2 align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2">
                                <label class="small text-muted flex-shrink-0">
                                    <i class="fas fa-video me-1"></i> Camera:
                                </label>
                                <select id="enrollCameraSelect" class="form-select form-select-sm"></select>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex gap-2">
                            <button type="button" class="btn btn-cyber-primary flex-grow-1" id="btnSnapEye">
                                <i class="fas fa-camera"></i> Chụp Mắt Ngay
                            </button>
                        </div>
                    </div>

                    <!-- Fallback Upload Ảnh từ Máy tính -->
                    <div class="mt-4 pt-3 border-top">
                        <label class="form-label small text-muted">
                            <i class="fas fa-file-arrow-up text-primary me-1"></i> Hoặc tải ảnh mống mắt từ máy tính:
                        </label>
                        <input type="file" id="fallbackImageInput" class="form-control form-control-sm" accept="image/*">
                    </div>

                    <!-- Xem trước ảnh đã chụp -->
                    <div id="capturedPreviewArea" class="mt-3 p-3 rounded-3 bg-light border" style="display: none;">
                        <div class="d-flex align-items-center gap-3">
                            <img id="previewImg" src="" style="width: 70px; height: 70px; object-fit: cover; border-radius: 10px; border: 2px solid #10b981;">
                            <div>
                                <div class="text-success fw-bold small"><i class="fas fa-check-circle me-1"></i> Ảnh mống mắt đã sẵn sàng!</div>
                                <div class="text-muted small">Thuật toán AI sẽ tự động tách vùng mắt khỏi khuôn mặt khi bạn bấm Lưu.</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

    </div>

<script>
    const video = document.getElementById('enrollWebcam');
    const overlayCanvas = document.getElementById('enrollOverlayCanvas');
    const overlayCtx = overlayCanvas.getContext('2d');
    const canvas = document.getElementById('enrollCanvas');
    const cameraSelect = document.getElementById('enrollCameraSelect');
    const statusText = document.getElementById('enrollCameraStatus');
    const btnSnap = document.getElementById('btnSnapEye');
    const fallbackInput = document.getElementById('fallbackImageInput');
    const form = document.getElementById('enrollForm');
    const photoBadge = document.getElementById('photoStatusBadge');
    const previewArea = document.getElementById('capturedPreviewArea');
    const previewImg = document.getElementById('previewImg');
    const btnSubmit = document.getElementById('btnSubmitEnroll');

    let currentStream = null;
    let capturedBlob = null;
    let trackerTask = null;

    async function startCamera(deviceId = null) {
        if (currentStream) {
            currentStream.getTracks().forEach(t => t.stop());
        }

        const constraints = {
            video: deviceId ? { deviceId: { exact: deviceId } } : { width: { ideal: 1280, min: 640 }, height: { ideal: 720, min: 480 }, facingMode: "user" },
            audio: false
        };

        try {
            currentStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = currentStream;
            video.onloadedmetadata = () => {
                if (video.videoWidth && video.videoHeight) {
                    video.parentElement.style.aspectRatio = `${video.videoWidth} / ${video.videoHeight}`;
                }
            };
            statusText.innerHTML = '<i class="fas fa-circle-check text-success me-1"></i> Camera sẵn sàng • Hãy nhìn tự nhiên vào màn hình';
            await loadCameras();
            startTracking();
        } catch (e) {
            console.error(e);
            statusText.innerHTML = '<i class="fas fa-triangle-exclamation text-danger me-1"></i> Không thể bật camera • Hãy chọn ảnh tải lên';
        }
    }

    async function loadCameras() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const vids = devices.filter(d => d.kind === 'videoinput');
            cameraSelect.innerHTML = '';
            vids.forEach((d, i) => {
                const opt = document.createElement('option');
                opt.value = d.deviceId;
                opt.text = d.label || `Camera ${i + 1}`;
                cameraSelect.appendChild(opt);
            });
        } catch (e) {}
    }

    cameraSelect.addEventListener('change', () => startCamera(cameraSelect.value));

    function startTracking() {
        try {
            if (typeof tracking !== 'undefined') {
                const eyeTracker = new tracking.ObjectTracker('eye');
                eyeTracker.setStepSize(1.7);
                eyeTracker.setScaleFactor(1.15);
                eyeTracker.setMinDimension(40);

                eyeTracker.on('track', function(event) {
                    const cw = video.offsetWidth;
                    const ch = video.offsetHeight;
                    if (!cw || !ch) return;

                    overlayCanvas.width = cw;
                    overlayCanvas.height = ch;
                    overlayCtx.clearRect(0, 0, cw, ch);

                    if (event.data && event.data.length > 0) {
                        let validEyes = event.data.filter(rect => {
                            if (rect.width < 38 || rect.height < 38) return false;
                            const ratio = rect.width / rect.height;
                            if (ratio < 0.68 || ratio > 1.45) return false;
                            if (rect.y < ch * 0.18 || rect.y > ch * 0.78) return false;
                            if (rect.total && rect.total < 2) return false;
                            return true;
                        });

                        if (validEyes.length > 2) {
                            validEyes.sort((a, b) => ((b.total || 1) * b.width) - ((a.total || 1) * a.width));
                            validEyes = validEyes.slice(0, 2);
                        }

                        validEyes.sort((a, b) => a.x - b.x);

                        if (validEyes.length > 0) {
                            validEyes.forEach((rect, idx) => {
                                const dispX = cw - (rect.x + rect.width);
                                const dispY = rect.y;
                                const dispW = rect.width;
                                const dispH = rect.height;
                                const cx = dispX + dispW / 2;
                                const cy = dispY + dispH / 2;
                                const cornerLen = Math.max(12, Math.min(22, dispW * 0.25));

                                overlayCtx.shadowColor = 'rgba(16, 185, 129, 0.7)';
                                overlayCtx.shadowBlur = 8;
                                overlayCtx.strokeStyle = '#10b981';
                                overlayCtx.lineWidth = 2.5;
                                overlayCtx.lineCap = 'round';

                                overlayCtx.beginPath();
                                overlayCtx.moveTo(dispX, dispY + cornerLen);
                                overlayCtx.lineTo(dispX, dispY);
                                overlayCtx.lineTo(dispX + cornerLen, dispY);
                                overlayCtx.stroke();

                                overlayCtx.beginPath();
                                overlayCtx.moveTo(dispX + dispW - cornerLen, dispY);
                                overlayCtx.lineTo(dispX + dispW);
                                overlayCtx.lineTo(dispX + dispW, dispY + cornerLen);
                                overlayCtx.stroke();

                                overlayCtx.beginPath();
                                overlayCtx.moveTo(dispX, dispY + dispH - cornerLen);
                                overlayCtx.lineTo(dispX, dispY + dispH);
                                overlayCtx.lineTo(dispX + cornerLen, dispY + dispH);
                                overlayCtx.stroke();

                                overlayCtx.beginPath();
                                overlayCtx.moveTo(dispX + dispW - cornerLen, dispY + dispH);
                                overlayCtx.lineTo(dispX + dispW);
                                overlayCtx.lineTo(dispX + dispW, dispY + dispH - cornerLen);
                                overlayCtx.stroke();

                                overlayCtx.shadowBlur = 0;
                                overlayCtx.strokeStyle = 'rgba(52, 211, 153, 0.75)';
                                overlayCtx.lineWidth = 1.5;
                                overlayCtx.beginPath();
                                overlayCtx.moveTo(cx - 6, cy);
                                overlayCtx.lineTo(cx + 6, cy);
                                overlayCtx.moveTo(cx, cy - 6);
                                overlayCtx.lineTo(cx, cy + 6);
                                overlayCtx.stroke();

                                const label = (validEyes.length === 2)
                                    ? (idx === 0 ? 'MẮT (PHẢI)' : 'MẮT (TRÁI)')
                                    : 'ĐÃ ĐỊNH VỊ MẮT';

                                overlayCtx.font = 'bold 11px system-ui, -apple-system, sans-serif';
                                const tagW = overlayCtx.measureText(label).width + 16;
                                const tagH = 20;
                                const tagX = Math.max(4, Math.min(cw - tagW - 4, dispX + (dispW - tagW) / 2));
                                const tagY = dispY > 26 ? dispY - 24 : dispY + dispH + 6;

                                overlayCtx.fillStyle = 'rgba(15, 23, 42, 0.85)';
                                overlayCtx.beginPath();
                                overlayCtx.roundRect(tagX, tagY, tagW, tagH, 5);
                                overlayCtx.fill();
                                overlayCtx.strokeStyle = 'rgba(16, 185, 129, 0.5)';
                                overlayCtx.lineWidth = 1;
                                overlayCtx.stroke();

                                overlayCtx.fillStyle = '#34d399';
                                overlayCtx.fillText(label, tagX + 8, tagY + 14);
                            });
                        }
                    }
                });

                if (trackerTask) trackerTask.stop();
                trackerTask = tracking.track('#enrollWebcam', eyeTracker);
            }
        } catch (e) {}
    }

    btnSnap.addEventListener('click', async () => {
        if (!video.videoWidth) return;

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        capturedBlob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', 0.95));

        previewImg.src = URL.createObjectURL(capturedBlob);
        previewArea.style.display = 'block';
        photoBadge.innerHTML = '<i class="fas fa-circle-check text-success"></i> Đã chụp từ Camera';
        photoBadge.className = 'text-success fw-semibold';

        Swal.fire({
            icon: 'success',
            title: 'Đã Chụp Mắt Thành Công!',
            text: 'Mẫu ảnh đã được ghim. Giờ hãy điền thông tin và bấm Lưu hồ sơ.',
            timer: 1500,
            showConfirmButton: false
        });
    });

    fallbackInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files[0]) {
            capturedBlob = e.target.files[0];
            previewImg.src = URL.createObjectURL(capturedBlob);
            previewArea.style.display = 'block';
            photoBadge.innerHTML = '<i class="fas fa-circle-check text-success"></i> Đã chọn file ảnh';
            photoBadge.className = 'text-success fw-semibold';
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!capturedBlob) {
            Swal.fire({
                icon: 'warning',
                title: 'Thiếu Ảnh Mống Mắt!',
                text: 'Vui lòng nhìn vào camera bấm "Chụp Mắt Ngay" hoặc tải ảnh lên trước khi lưu.'
            });
            return;
        }

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Đang trích xuất Vector Mống Mắt qua CNN...';

        const formData = new FormData(form);
        formData.append('eye_image', capturedBlob, 'iris_enroll.jpg');

        try {
            const resp = await fetch('employee_manager.php', {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();

            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Đăng Ký Thành Công!',
                    text: data.message,
                    confirmButtonText: 'Xem danh sách nhân sự'
                }).then(() => {
                    window.location.href = 'employees.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi Đăng Ký Sinh Trắc!',
                    text: data.message || 'Không thể trích xuất mống mắt.'
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi Máy Chủ!',
                text: 'Không thể kết nối đến máy chủ AI hoặc CSDL: ' + err
            });
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-floppy-disk"></i> LƯU HỒ SƠ & TRÍCH XUẤT MỐNG MẮT';
        }
    });

    window.addEventListener('DOMContentLoaded', () => startCamera());
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
