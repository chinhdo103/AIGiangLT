<?php
/**
 * TRANG ĐĂNG NHẬP SINH TRẮC MỐNG MẮT (GIAO DIỆN SÁNG & TỰ ĐỘNG NHẬN DIỆN MẮT)
 * Tự động tìm mắt trong toàn bộ khung hình camera (không cần đưa vào ô).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_guard.php';

// Nếu đã đăng nhập rồi -> Điều hướng thông minh
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: dashboard.php');
    } else {
        header('Location: user_portal.php');
    }
    exit;
}

$error = null;

// Xử lý đăng nhập bằng Mật khẩu (Dự phòng cho Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'password_login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['logged_in']       = true;
        $_SESSION['user_id']         = 'ADMIN_MASTER';
        $_SESSION['user_name']       = 'Quản Trị Viên Hệ Thống';
        $_SESSION['user_email']      = 'admin@iris-security.lab';
        $_SESSION['user_role']       = 'Quản trị viên';
        $_SESSION['is_admin']        = true;
        $_SESSION['auth_method']     = 'CREDENTIALS';
        $_SESSION['lab_id']          = 1;
        $_SESSION['login_time']      = date('H:i:s d/m/Y');

        try {
            $stmt = $pdo->prepare("INSERT INTO access_logs (emp_id, lab_id, action, confidence_score, ai_reason) VALUES (NULL, 1, 'ENTRY_GRANTED', 100.0, 'ĐĂNG NHẬP MASTER ADMIN BẰNG MẬT KHẨU')");
            $stmt->execute();
        } catch (Exception $e) {}

        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Tài khoản hoặc mật khẩu Quản trị viên không chính xác!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập Sinh Trắc Mống Mắt - Iris Security</title>
    
    <!-- Bootstrap 5.3.2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6.5.1 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Design System (Nền Sáng) -->
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Tracking.js để tự động phát hiện mắt trực tiếp trong trình duyệt -->
    <script src="assets/js/tracking-min.js"></script>
    <script src="assets/js/face-min.js"></script>
    <script src="assets/js/eye-min.js"></script>

    <style>
        body {
            background-color: #f1f5f9;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
        }

        .login-card {
            width: 100%;
            max-width: 960px;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            overflow: hidden;
        }

        .nav-tabs-clean {
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 0.5rem 1.5rem 0;
            display: flex;
            gap: 0.5rem;
        }

        .nav-tabs-clean .nav-link {
            border: none;
            color: #64748b;
            font-weight: 600;
            padding: 0.85rem 1.4rem;
            border-radius: 10px 10px 0 0;
            transition: all 0.2s ease;
            background: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-tabs-clean .nav-link.active {
            color: #2563eb;
            background: #ffffff;
            border-top: 2px solid #2563eb;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.02);
        }

        .camera-container-box {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: #0f172a;
            aspect-ratio: 4 / 3;
            max-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
        }

        .camera-container-box video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
            display: block;
        }

        /* Canvas vẽ khung nhận diện mắt tự động - Không dùng transform scaleX để chữ không bị lật ngược */
        .auto-eye-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .auto-detection-indicator {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(6px);
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        
        <!-- Header Thanh Thương Hiệu Sáng & Trang Nhã -->
        <div class="p-4 pb-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">HỆ THỐNG XÁC THỰC MỐNG MẮT</h4>
                    <p class="text-muted small mb-0">Cổng Nhận Diện Tự Động • Không Cần Đưa Mắt Vào Khung Cố Định</p>
                </div>
            </div>
            <div>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 px-3 py-2 font-mono">
                    <i class="fas fa-shield-halved me-1"></i> BẢO MẬT SINH TRẮC HỌC
                </span>
            </div>
        </div>

        <!-- Tab chuyển đổi phương thức -->
        <ul class="nav nav-tabs nav-tabs-clean" id="loginTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="iris-tab" data-bs-toggle="tab" data-bs-target="#irisTabPane" type="button" role="tab">
                    <i class="fas fa-eye text-primary"></i> Quét Mống Mắt (Tự Động Nhận Diện)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#passwordTabPane" type="button" role="tab">
                    <i class="fas fa-key text-secondary"></i> Đăng Nhập Quản Trị Viên (Mật Khẩu)
                </button>
            </li>
        </ul>

        <!-- Nội dung Tab -->
        <div class="tab-content p-4 p-md-5 bg-white" id="loginTabContent">
            
            <!-- ================= TAB 1: QUÉT MỐNG MẮT TỰ ĐỘNG ================= -->
            <div class="tab-pane fade show active" id="irisTabPane" role="tabpanel">
                <div class="row g-4 align-items-center">
                    
                    <!-- Khung Camera Không Có Ô Ép Buộc (Tự Động Bắt Điểm Mắt) -->
                    <div class="col-lg-7">
                        <div class="camera-container-box">
                            <video id="webcam" autoplay playsinline muted></video>
                            <canvas id="eyeOverlayCanvas" class="auto-eye-canvas"></canvas>
                            <canvas id="snapshotCanvas" style="display: none;"></canvas>

                            <!-- Badge thông báo Auto-detect -->
                            <div class="auto-detection-indicator">
                                <span class="rounded-circle bg-success" style="width: 7px; height: 7px;"></span>
                                <span>AI TỰ ĐỘNG BẮT ĐIỂM MẮT</span>
                            </div>

                            <!-- Trạng thái quét nổi -->
                            <div class="status-pill text-dark" id="scanStatusText">
                                <i class="fas fa-eye text-primary me-1"></i> Hãy nhìn tự nhiên vào camera...
                            </div>
                        </div>

                        <!-- Chọn Camera -->
                        <div class="mt-3 d-flex align-items-center gap-2">
                            <label class="small text-muted flex-shrink-0">
                                <i class="fas fa-video me-1"></i> Thiết bị ghi hình:
                            </label>
                            <select id="cameraSelect" class="form-select form-select-sm"></select>
                        </div>
                    </div>

                    <!-- Khu vực Hướng Dẫn & Điều Khiển -->
                    <div class="col-lg-5">
                        <div class="ps-lg-3">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 mb-2">
                                <i class="fas fa-wand-magic-sparkles"></i> AI AUTO-CROP & TRACKING
                            </span>
                            <h4 class="fw-bold mb-2 text-dark">Nhận Diện Tự Nhiên</h4>
                            <p class="text-secondary small mb-4">
                                Bạn không cần phải căng mắt hay đưa sát vào một vòng tròn cố định. Thuật toán thị giác máy tính sẽ <strong>tự động nhận diện khuôn mặt và cô lập vùng mắt</strong> để trích xuất đặc trưng mống mắt.
                            </p>

                            <!-- Nút Quét Bằng Tay -->
                            <button type="button" class="btn btn-cyber-primary w-100 py-3 mb-3 justify-content-center fs-6" id="btnScanNow">
                                <i class="fas fa-camera"></i> QUÉT MỐNG MẮT NGAY
                            </button>

                            <!-- Tự động nhận diện khi phát hiện mắt -->
                            <div class="form-check form-switch mb-4 p-2 bg-light rounded-3 border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" id="autoScanSwitch" checked>
                                <label class="form-check-label small text-dark fw-semibold" for="autoScanSwitch">
                                    Tự động nhận diện liên tục (Auto-detect)
                                </label>
                            </div>

                            <!-- Hoặc Upload file ảnh mống mắt dự phòng -->
                            <div class="pt-3 border-top">
                                <label class="small text-muted mb-2 d-block">
                                    <i class="fas fa-upload me-1"></i> Hoặc tải ảnh mống mắt từ máy tính:
                                </label>
                                <input type="file" id="fileEyeInput" class="form-control form-control-sm" accept="image/*">
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ================= TAB 2: ĐĂNG NHẬP MẬT KHẨU ADMIN ================= -->
            <div class="tab-pane fade" id="passwordTabPane" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-md-7 col-lg-6">
                        <div class="text-center mb-4">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 bg-light border" 
                                 style="width: 56px; height: 56px;">
                                <i class="fas fa-user-shield text-primary fs-4"></i>
                            </div>
                            <h4 class="fw-bold mb-1 text-dark">Đăng Nhập Quản Trị Viên</h4>
                            <p class="text-muted small">Cổng dành riêng cho Quản trị viên hệ thống</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2 small mb-4 d-flex align-items-center gap-2">
                                <i class="fas fa-circle-exclamation fs-5"></i>
                                <div><?= htmlspecialchars($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="action" value="password_login">
                            
                            <div class="mb-3">
                                <label class="form-label">Tài khoản Quản trị</label>
                                <input type="text" name="username" class="form-control" required value="admin">
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Mật khẩu</label>
                                <input type="password" name="password" class="form-control" required value="admin123">
                            </div>

                            <button type="submit" class="btn btn-cyber-primary w-100 py-2 justify-content-center">
                                <i class="fas fa-arrow-right-to-bracket"></i> ĐĂNG NHẬP ADMIN
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer Đơn Giản & Trang Nhã -->
        <div class="px-4 py-3 border-top bg-light d-flex flex-wrap align-items-center justify-content-between small text-muted">
            <div>
                <i class="fas fa-check-circle text-success me-1"></i> Thuật toán Haar Cascade + MobileNetV2: <strong class="text-dark">Sẵn sàng</strong>
            </div>
            <div>
                <a href="kiosk.php" target="_blank" class="text-primary text-decoration-none fw-semibold">
                    <i class="fas fa-door-open me-1"></i> Mở Màn Hình Kiosk Quét Cửa <i class="fas fa-arrow-up-right-from-square small"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const video = document.getElementById('webcam');
    const overlayCanvas = document.getElementById('eyeOverlayCanvas');
    const overlayCtx = overlayCanvas.getContext('2d');
    const snapshotCanvas = document.getElementById('snapshotCanvas');
    const cameraSelect = document.getElementById('cameraSelect');
    const statusText = document.getElementById('scanStatusText');
    const btnScan = document.getElementById('btnScanNow');
    const autoScanSwitch = document.getElementById('autoScanSwitch');
    const fileEyeInput = document.getElementById('fileEyeInput');

    let currentStream = null;
    let isVerifying = false;
    let autoScanInterval = null;
    let trackerTask = null;
    let lastScanTime = 0;

    // 1. Khởi tạo Camera
    async function initCamera(deviceId = null) {
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
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
            statusText.innerHTML = '<i class="fas fa-eye text-primary me-1"></i> Camera đã kết nối • Hãy nhìn tự nhiên vào màn hình';
            await populateCameraList();
            startSmartEyeTracking();
        } catch (err) {
            console.error("Camera Error:", err);
            statusText.innerHTML = '<i class="fas fa-triangle-exclamation text-danger me-1"></i> Không tìm thấy Camera • Hãy chọn ảnh bên dưới';
        }
    }

    async function populateCameraList() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(d => d.kind === 'videoinput');
            cameraSelect.innerHTML = '';
            videoDevices.forEach((device, index) => {
                const opt = document.createElement('option');
                opt.value = device.deviceId;
                opt.text = device.label || `Camera ${index + 1}`;
                cameraSelect.appendChild(opt);
            });
        } catch (e) {}
    }

    cameraSelect.addEventListener('change', () => initCamera(cameraSelect.value));

    // Helper kiểm tra sinh trắc mắt thật qua độ sáng điểm ảnh (Loại bỏ tóc, trán, bàn tay che)
    const probeCanvas = document.createElement('canvas');
    const probeCtx = probeCanvas.getContext('2d', { willReadFrequently: true });
    probeCanvas.width = 36;
    probeCanvas.height = 36;

    function isBiologicalEyePatch(videoEl, rect) {
        try {
            if (!videoEl || !videoEl.videoWidth) return true;
            const sx = (rect.x / videoEl.offsetWidth) * videoEl.videoWidth;
            const sy = (rect.y / videoEl.offsetHeight) * videoEl.videoHeight;
            const sw = (rect.width / videoEl.offsetWidth) * videoEl.videoWidth;
            const sh = (rect.height / videoEl.offsetHeight) * videoEl.videoHeight;

            probeCtx.drawImage(videoEl, sx, sy, sw, sh, 0, 0, 36, 36);
            const imgData = probeCtx.getImageData(0, 0, 36, 36).data;
            let minLum = 255;
            let maxLum = 0;
            let sumLum = 0;
            const n = imgData.length / 4;

            for (let i = 0; i < imgData.length; i += 4) {
                const lum = 0.299 * imgData[i] + 0.587 * imgData[i + 1] + 0.114 * imgData[i + 2];
                if (lum < minLum) minLum = lum;
                if (lum > maxLum) maxLum = lum;
                sumLum += lum;
            }
            const meanLum = sumLum / n;
            const contrast = maxLum - minLum;

            // Mắt người thật: Đồng tử tối (min <= 80), lòng trắng/da sáng (max >= 130), độ tương phản cao (contrast >= 50)
            // Tóc (tối toàn bộ, maxLum thấp) hoặc trán/bàn tay (đều màu, minLum cao) sẽ bị loại bỏ 100%
            return (minLum <= 80 && maxLum >= 125 && contrast >= 50 && meanLum >= 35 && meanLum <= 185);
        } catch (e) {
            return true;
        }
    }

    // 2. Theo Dõi Mắt Thông Minh Trên Trình Duyệt (Smart Eye Tracking Overlay)
    function startSmartEyeTracking() {
        try {
            if (typeof tracking !== 'undefined') {
                const eyeTracker = new tracking.ObjectTracker('eye');
                eyeTracker.setStepSize(1.8);
                eyeTracker.setScaleFactor(1.18);
                eyeTracker.setMinDimension(46);

                eyeTracker.on('track', function(event) {
                    const cw = video.offsetWidth;
                    const ch = video.offsetHeight;
                    if (!cw || !ch) return;

                    overlayCanvas.width = cw;
                    overlayCanvas.height = ch;
                    overlayCtx.clearRect(0, 0, cw, ch);

                    if (event.data && event.data.length > 0) {
                        // Lọc thông minh loại bỏ tóc, trán và nhiễu ngẫu nhiên
                        let validEyes = event.data.filter(rect => {
                            // 1. Kích thước tối thiểu: Mắt người webcam thường >= 44px
                            if (rect.width < 44 || rect.height < 40) return false;

                            // 2. Tỷ lệ mắt xấp xỉ vuông/chữ nhật tự nhiên (0.65 đến 1.45)
                            const ratio = rect.width / rect.height;
                            if (ratio < 0.65 || ratio > 1.45) return false;

                            // 3. Vùng trục Y: Mắt người nằm ở khoảng 24% - 80% chiều cao khung hình
                            // Loại bỏ hoàn toàn vùng tóc và trán phía trên (< 24%)
                            if (rect.y < ch * 0.24 || rect.y > ch * 0.80) return false;

                            // 4. Kiểm định sinh trắc học điểm ảnh thực tế (Pupil/Sclera contrast)
                            if (!isBiologicalEyePatch(video, rect)) return false;

                            return true;
                        });

                        // Nếu phát hiện > 2 ứng viên, ưu tiên lấy 2 ứng viên có diện tích & điểm tin cậy cao nhất
                        if (validEyes.length > 2) {
                            validEyes.sort((a, b) => ((b.total || 1) * b.width) - ((a.total || 1) * a.width));
                            validEyes = validEyes.slice(0, 2);
                        }

                        // Sắp xếp từ trái sang phải theo video
                        validEyes.sort((a, b) => a.x - b.x);

                        if (validEyes.length > 0) {
                            validEyes.forEach((rect, idx) => {
                                // Do video hiển thị qua gương (CSS transform: scaleX(-1)),
                                // ta tính tọa độ hiển thị tương ứng trên Canvas không bị lật
                                const dispX = cw - (rect.x + rect.width);
                                const dispY = rect.y;
                                const dispW = rect.width;
                                const dispH = rect.height;
                                const cx = dispX + dispW / 2;
                                const cy = dispY + dispH / 2;
                                const cornerLen = Math.max(12, Math.min(22, dispW * 0.25));

                                // 1. Khung Autofocus dạng góc hiện đại [   ]
                                overlayCtx.shadowColor = 'rgba(16, 185, 129, 0.7)';
                                overlayCtx.shadowBlur = 8;
                                overlayCtx.strokeStyle = '#10b981';
                                overlayCtx.lineWidth = 2.5;
                                overlayCtx.lineCap = 'round';

                                // Góc trên - trái
                                overlayCtx.beginPath();
                                overlayCtx.moveTo(dispX, dispY + cornerLen);
                                overlayCtx.lineTo(dispX, dispY);
                                overlayCtx.lineTo(dispX + cornerLen, dispY);
                                overlayCtx.stroke();

                                // Góc trên - phải
                                overlayCtx.beginPath();
                                overlayCtx.moveTo(dispX + dispW - cornerLen, dispY);
                                overlayCtx.lineTo(dispX + dispW);
                                overlayCtx.lineTo(dispX + dispW, dispY + cornerLen);
                                overlayCtx.stroke();

                                // Góc dưới - trái
                                overlayCtx.beginPath();
                                overlayCtx.moveTo(dispX, dispY + dispH - cornerLen);
                                overlayCtx.lineTo(dispX, dispY + dispH);
                                overlayCtx.lineTo(dispX + cornerLen, dispY + dispH);
                                overlayCtx.stroke();

                                // Góc dưới - phải
                                overlayCtx.beginPath();
                                overlayCtx.moveTo(dispX + dispW - cornerLen, dispY + dispH);
                                overlayCtx.lineTo(dispX + dispW, dispY + dispH);
                                overlayCtx.lineTo(dispX + dispW, dispY + dispH - cornerLen);
                                overlayCtx.stroke();

                                // 2. Tâm ngắm ngắm con ngươi (+)
                                overlayCtx.shadowBlur = 0;
                                overlayCtx.strokeStyle = 'rgba(52, 211, 153, 0.75)';
                                overlayCtx.lineWidth = 1.5;
                                overlayCtx.beginPath();
                                overlayCtx.moveTo(cx - 6, cy);
                                overlayCtx.lineTo(cx + 6, cy);
                                overlayCtx.moveTo(cx, cy - 6);
                                overlayCtx.lineTo(cx, cy + 6);
                                overlayCtx.stroke();

                                // 3. Nhãn thông tin AI (Chữ xuôi tự nhiên, không bị ngược)
                                const label = (validEyes.length === 2)
                                    ? (idx === 0 ? 'MẮT (PHẢI)' : 'MẮT (TRÁI)')
                                    : 'ĐÃ ĐỊNH VỊ MẮT';

                                overlayCtx.font = 'bold 11px system-ui, -apple-system, sans-serif';
                                const tagW = overlayCtx.measureText(label).width + 16;
                                const tagH = 20;
                                const tagX = Math.max(4, Math.min(cw - tagW - 4, dispX + (dispW - tagW) / 2));
                                const tagY = dispY > 26 ? dispY - 24 : dispY + dispH + 6;

                                // Nền tag kính mờ tối màu
                                overlayCtx.fillStyle = 'rgba(15, 23, 42, 0.85)';
                                overlayCtx.beginPath();
                                overlayCtx.roundRect(tagX, tagY, tagW, tagH, 5);
                                overlayCtx.fill();
                                overlayCtx.strokeStyle = 'rgba(16, 185, 129, 0.5)';
                                overlayCtx.lineWidth = 1;
                                overlayCtx.stroke();

                                // Chữ màu lục bảo sắc nét
                                overlayCtx.fillStyle = '#34d399';
                                overlayCtx.fillText(label, tagX + 8, tagY + 14);
                            });

                            const now = Date.now();
                            // Nếu đang bật auto-scan và đã trôi qua 3 giây từ lần quét trước -> Tự động nhận diện
                            if (autoScanSwitch.checked && !isVerifying && (now - lastScanTime > 3000)) {
                                lastScanTime = now;
                                captureAndVerify();
                            }
                        }
                    }
                });

                if (trackerTask) trackerTask.stop();
                trackerTask = tracking.track('#webcam', eyeTracker);
            }
        } catch (e) {
            console.warn("Tracking note:", e);
        }
    }

    // 3. Chụp và gửi xác thực
    async function captureAndVerify(customBlob = null) {
        if (isVerifying) return;
        isVerifying = true;

        statusText.innerHTML = '<i class="fas fa-spinner fa-spin text-primary me-1"></i> Đang nhận diện mống mắt...';

        let blobToSend = customBlob;

        if (!blobToSend) {
            if (!video.videoWidth) {
                isVerifying = false;
                return;
            }
            snapshotCanvas.width = video.videoWidth;
            snapshotCanvas.height = video.videoHeight;
            const ctx = snapshotCanvas.getContext('2d');
            ctx.drawImage(video, 0, 0, snapshotCanvas.width, snapshotCanvas.height);

            blobToSend = await new Promise(resolve => snapshotCanvas.toBlob(resolve, 'image/jpeg', 0.95));
        }

        const formData = new FormData();
        formData.append('eye_image', blobToSend, 'iris_login.jpg');

        try {
            const response = await fetch('verify_login.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                statusText.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> Xin chào: ${data.user_name}`;
                
                if (autoScanInterval) clearInterval(autoScanInterval);
                if (trackerTask) trackerTask.stop();

                Swal.fire({
                    icon: 'success',
                    title: 'Xác Thực Danh Tính Thành Công!',
                    html: `
                        <div class="text-start p-3 rounded-3 bg-light border mt-2">
                            <div class="mb-1"><strong>Họ và tên:</strong> <span class="text-primary">${data.user_name}</span></div>
                            <div class="mb-1"><strong>Vai trò:</strong> <span class="badge ${data.is_admin ? 'bg-warning text-dark' : 'bg-primary'}">${data.role}</span></div>
                            <div class="mb-1"><strong>Độ khớp Mống mắt:</strong> <span class="text-success fw-bold">${data.confidence}%</span></div>
                            <div class="text-muted small mt-2"><i class="fas fa-arrow-right me-1"></i> Đang tự động chuyển hướng đến ${data.is_admin ? 'Trang Quản Trị' : 'Cổng Cá Nhân'}...</div>
                        </div>
                    `,
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
                    window.location.href = data.redirect;
                });

            } else {
                statusText.innerHTML = `<i class="fas fa-times-circle text-danger me-1"></i> ${data.message || 'Chưa nhận diện được'}`;
                setTimeout(() => {
                    if (!isVerifying) {
                        statusText.innerHTML = '<i class="fas fa-eye text-primary me-1"></i> Hãy nhìn tự nhiên vào camera...';
                    }
                }, 2000);
            }

        } catch (err) {
            console.error("Verify API Error:", err);
            statusText.innerHTML = '<i class="fas fa-triangle-exclamation text-danger me-1"></i> Lỗi kết nối máy chủ AI';
        } finally {
            isVerifying = false;
        }
    }

    // 4. Sự kiện Quét Thủ Công
    btnScan.addEventListener('click', () => {
        captureAndVerify();
    });

    // 5. Quét qua file tải lên
    fileEyeInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files[0]) {
            captureAndVerify(e.target.files[0]);
        }
    });

    // 6. Tự động Quét Dự Phòng Mỗi 4s
    function setupFallbackAutoScan() {
        if (autoScanInterval) clearInterval(autoScanInterval);
        if (autoScanSwitch.checked) {
            autoScanInterval = setInterval(() => {
                const activeTab = document.querySelector('#loginTabs .nav-link.active');
                if (activeTab && activeTab.id === 'iris-tab' && !isVerifying) {
                    captureAndVerify();
                }
            }, 4000);
        }
    }

    autoScanSwitch.addEventListener('change', setupFallbackAutoScan);

    window.addEventListener('DOMContentLoaded', () => {
        initCamera();
        setupFallbackAutoScan();
    });
</script>
</body>
</html>
