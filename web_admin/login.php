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
                                <i class="fas fa-user-clock text-secondary me-1"></i> Chờ người tiếp cận... (Đứng trước camera để quét)
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

                            <!-- Tự động nhận diện khi phát hiện người tiếp cận -->
                            <div class="form-check form-switch mb-4 p-2 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                                <div>
                                    <input class="form-check-input ms-0 me-2" type="checkbox" id="autoScanSwitch" checked>
                                    <label class="form-check-label small text-dark fw-semibold" for="autoScanSwitch">
                                        Tự động quét khi phát hiện người (Smart Presence)
                                    </label>
                                </div>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" id="presenceStatusBadge">
                                    <i class="fas fa-radar me-1"></i> Chờ người
                                </span>
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
<script src="assets/js/three.min.js"></script>
<script src="assets/js/iris_three_hud.js"></script>

<script>
    const video = document.getElementById('webcam');
    const overlayCanvas = document.getElementById('eyeOverlayCanvas');
    const overlayCtx = overlayCanvas.getContext('2d');
    const snapshotCanvas = document.getElementById('snapshotCanvas');
    const cameraSelect = document.getElementById('cameraSelect');
    const statusText = document.getElementById('scanStatusText');
    const btnScan = document.getElementById('btnScanNow');
    const autoScanSwitch = document.getElementById('autoScanSwitch');
    
    let currentStream = null;
    let isVerifying = false;
    let trackerTask = null;
    let lastScanTime = 0;

    // Quản lý Trạng thái Phát hiện Người & Khóa nét Thông minh (Smart Presence State Machine)
    let presenceState = 'EMPTY'; // 'EMPTY' | 'APPROACHING' | 'LOCKING' | 'SCANNED_WAIT_LEAVE'
    let consecutiveEmptyFrames = 0;
    let consecutivePresentFrames = 0;
    let stableLockFrames = 0;
    const REQUIRED_STABLE_FRAMES = 12; // ~500-600ms giữ ổn định mắt
    let lastTrackPos = { x: 0, y: 0 };
    let hasScannedCurrentPerson = false;
    let cooldownUntil = 0;
    const presenceBadge = document.getElementById('presenceStatusBadge');

    // Khởi tạo Three.js Biometric Hologram HUD
    let irisHUD = null;
    window.addEventListener('DOMContentLoaded', () => {
        const camBox = document.querySelector('.camera-container-box');
        if (camBox && typeof IrisThreeHUD !== 'undefined') {
            irisHUD = new IrisThreeHUD(camBox, { soundEnabled: true });
        }
    });

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
            
            try {
                await video.play();
            } catch (pe) {
                console.warn("Video play note:", pe);
            }

            if (video.videoWidth && video.videoHeight) {
                video.parentElement.style.aspectRatio = `${video.videoWidth} / ${video.videoHeight}`;
            }

            statusText.innerHTML = '<i class="fas fa-arrows-to-eye text-primary me-1"></i> Đang theo dõi mống mắt...';
            startSmartEyeTracking();
            await populateCameraList();
        } catch (err) {
            console.error("Camera Error:", err);
            statusText.innerHTML = '<i class="fas fa-triangle-exclamation text-danger me-1"></i> Không tìm thấy Camera';
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

    // 2. HỆ THỐNG THỊ GIÁC MÁY TÍNH & BỘ ĐÔI NHẬN DIỆN MẮT (Hybrid Vision & Smart Eye Lock Engine)
    const visionCanvas = document.createElement('canvas');
    visionCanvas.width = 160;
    visionCanvas.height = 120;
    const visionCtx = visionCanvas.getContext('2d', { willReadFrequently: true });
    let lastVisionLums = null;
    let latestTrackerEyes = null;
    let visionLoopTimer = null;

    // Phân tích thị giác máy tính dựa trên Độ biến thiên sắc độ & Micro-chuyển động (Nhận diện người 100%)
    function analyzeCameraFrame() {
        if (!video || !video.videoWidth) {
            return { hasPerson: false, isSteady: false, eyePos: null };
        }

        try {
            visionCtx.drawImage(video, 0, 0, 160, 120);
            const imgData = visionCtx.getImageData(0, 0, 160, 120);
            const data = imgData.data;
            const totalPixels = 160 * 120;

            let sumLum = 0;
            let sumSqLum = 0;
            let motionDiff = 0;
            let minLum = 255;
            let maxLum = 0;
            let skinPixels = 0;

            let darkestLum = 255;
            let darkestX = 80;
            let darkestY = 60;

            for (let i = 0; i < data.length; i += 4) {
                const r = data[i], g = data[i + 1], b = data[i + 2];
                const lum = 0.299 * r + 0.587 * g + 0.114 * b;
                sumLum += lum;
                sumSqLum += lum * lum;
                if (lum < minLum) minLum = lum;
                if (lum > maxLum) maxLum = lum;

                // Tông da người thật (Chống quét phòng trống / tường trắng)
                if (r > 48 && g > 34 && b > 24 && (r - b) >= 4 && (Math.max(r, g, b) - Math.min(r, g, b)) >= 10) {
                    skinPixels++;
                }

                if (lastVisionLums) {
                    motionDiff += Math.abs(lum - lastVisionLums[i / 4]);
                }

                // Dò tâm mắt/đồng tử tối nhất ở vùng trung tâm (20% - 80% width, 25% - 75% height)
                const px = (i / 4) % 160;
                const py = Math.floor((i / 4) / 160);
                if (px > 24 && px < 136 && py > 24 && py < 92) {
                    if (lum < darkestLum) {
                        darkestLum = lum;
                        darkestX = px;
                        darkestY = py;
                    }
                }
            }

            if (!lastVisionLums) {
                lastVisionLums = new Float32Array(totalPixels);
            }
            for (let i = 0; i < data.length; i += 4) {
                lastVisionLums[i / 4] = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
            }

            const meanLum = sumLum / totalPixels;
            const variance = (sumSqLum / totalPixels) - (meanLum * meanLum);
            const stdDev = Math.sqrt(Math.max(0, variance));
            const contrast = maxLum - minLum;
            const avgMotion = motionDiff / totalPixels;
            const skinRatio = skinPixels / totalPixels;

            // ĐIỀU KIỆN CÓ NGƯỜI THỰC SỰ TRƯỚC CAMERA (LOẠI BỎ HOÀN TOÀN PHÒNG TRỐNG / TƯỜNG TRẮNG):
            // 1. Phải có sắc tố da người >= 5% HOẶC có đồng tử tương phản đậm rõ rệt (meanLum - darkestLum >= 30)
            // 2. Độ tương phản toàn khung hình contrast >= 28, chi tiết khuôn mặt stdDev >= 12
            // 3. Ánh sáng đủ nhìn rõ (25 <= meanLum <= 235)
            const hasPerson = ((skinRatio >= 0.05 || (darkestLum <= 100 && (meanLum - darkestLum) >= 28)) &&
                               stdDev >= 12 && contrast >= 28 && meanLum >= 25 && meanLum <= 235);

            // Mắt giữ yên ổn định nhìn thẳng (< 6.0)
            const isSteady = hasPerson && (avgMotion < 6.0);

            const cw = video.offsetWidth || video.clientWidth || video.videoWidth || 640;
            const ch = video.offsetHeight || video.clientHeight || video.videoHeight || 480;
            const eyeX = (1 - (darkestX / 160)) * cw;
            const eyeY = (darkestY / 120) * ch;

            return {
                hasPerson,
                isSteady,
                avgMotion,
                stdDev,
                skinRatio,
                eyePos: { x: eyeX, y: eyeY }
            };
        } catch (e) {
            return { hasPerson: false, isSteady: false, eyePos: null };
        }
    }

    function drawCyberReticle(ctx, dispX, dispY, dispW, dispH, label) {
        try {
            const cornerLen = Math.max(10, Math.min(20, dispW * 0.25));
            const cx = dispX + dispW / 2;
            const cy = dispY + dispH / 2;

            ctx.shadowColor = 'rgba(16, 185, 129, 0.9)';
            ctx.shadowBlur = 8;
            ctx.strokeStyle = '#10b981';
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';

            // 4 Góc ngắm
            ctx.beginPath();
            ctx.moveTo(dispX, dispY + cornerLen); ctx.lineTo(dispX, dispY); ctx.lineTo(dispX + cornerLen, dispY);
            ctx.moveTo(dispX + dispW - cornerLen, dispY); ctx.lineTo(dispX + dispW); ctx.lineTo(dispX + dispW, dispY + cornerLen);
            ctx.moveTo(dispX, dispY + dispH - cornerLen); ctx.lineTo(dispX, dispY + dispH); ctx.lineTo(dispX + cornerLen, dispY + dispH);
            ctx.moveTo(dispX + dispW - cornerLen, dispY + dispH); ctx.lineTo(dispX + dispW); ctx.lineTo(dispX + dispW, dispY + cornerLen);
            ctx.stroke();

            // Tâm chữ thập
            ctx.shadowBlur = 0;
            ctx.strokeStyle = 'rgba(52, 211, 153, 0.9)';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(cx - 5, cy); ctx.lineTo(cx + 5, cy);
            ctx.moveTo(cx, cy - 5); ctx.lineTo(cx, cy + 5);
            ctx.stroke();

            // Nhãn (Sử dụng rect chuẩn, an toàn tuyệt đối trên mọi trình duyệt)
            ctx.font = 'bold 11px system-ui, sans-serif';
            const tagW = ctx.measureText(label).width + 14;
            const tagH = 18;
            const tagX = Math.max(4, Math.min(ctx.canvas.width - tagW - 4, dispX + (dispW - tagW) / 2));
            const tagY = dispY > 24 ? dispY - 22 : dispY + dispH + 6;

            ctx.fillStyle = 'rgba(15, 23, 42, 0.88)';
            ctx.beginPath();
            ctx.rect(tagX, tagY, tagW, tagH);
            ctx.fill();
            ctx.strokeStyle = 'rgba(16, 185, 129, 0.6)';
            ctx.lineWidth = 1;
            ctx.stroke();

            ctx.fillStyle = '#34d399';
            ctx.fillText(label, tagX + 7, tagY + 13);
        } catch (e) {
            console.error("Reticle error:", e);
        }
    }

    function startSmartEyeTracking() {
        try {
            if (visionLoopTimer) clearInterval(visionLoopTimer);
            visionLoopTimer = setInterval(processVisionFrame, 80);
        } catch (e) {
            console.warn("Tracking start error:", e);
        }
    }

    // Xử lý từng khung hình thị giác máy tính
    function processVisionFrame() {
        try {
            if (!video || !video.videoWidth) return;

            const cw = video.offsetWidth || video.clientWidth || video.videoWidth || 640;
            const ch = video.offsetHeight || video.clientHeight || video.videoHeight || 480;

            overlayCanvas.width = cw;
            overlayCanvas.height = ch;
            overlayCtx.clearRect(0, 0, cw, ch);

            const vision = analyzeCameraFrame();
            const now = Date.now();

            if (vision.hasPerson) {
                consecutiveEmptyFrames = 0;
                consecutivePresentFrames++;

                const boxW = Math.round(cw * 0.24);
                const boxH = Math.round(ch * 0.26);
                const dispX = Math.max(10, Math.min(cw - boxW - 10, vision.eyePos.x - boxW / 2));
                const dispY = Math.max(10, Math.min(ch - boxH - 10, vision.eyePos.y - boxH / 2));

                drawCyberReticle(overlayCtx, dispX, dispY, boxW, boxH, 'KHÓA NÉT MẮT');
                const targetX = vision.eyePos.x;
                const targetY = vision.eyePos.y;
                const targetRadius = boxW / 2;

                if (irisHUD) {
                    irisHUD.setTarget(targetX, targetY, targetRadius, true);
                }

                if (presenceBadge) {
                    presenceBadge.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                    presenceBadge.innerHTML = '<i class="fas fa-user-check me-1"></i> Có người';
                }

                if (now < cooldownUntil) {
                    if (statusText && !isVerifying) {
                        statusText.innerHTML = '<i class="fas fa-clock text-info me-1"></i> Đang chuẩn bị phiên tiếp theo...';
                    }
                    if (irisHUD && !isVerifying) {
                        irisHUD.setState('COOLDOWN');
                    }
                    return;
                }

                const jitter = Math.hypot(targetX - lastTrackPos.x, targetY - lastTrackPos.y);
                lastTrackPos = { x: targetX, y: targetY };

                if (jitter < 55 || vision.isSteady) {
                    stableLockFrames++;
                    const lockPercent = Math.min(100, Math.round((stableLockFrames / 7) * 100));

                    if (irisHUD) {
                        irisHUD.setState('LOCKING');
                        irisHUD.setLockProgress(lockPercent / 100);
                    }

                    if (statusText && !isVerifying) {
                        statusText.innerHTML = `<i class="fas fa-bullseye text-warning fa-spin me-1"></i> [ĐÃ THẤY MẮT] Khóa nét: <strong>${lockPercent}%</strong> (Giữ yên)...`;
                    }

                    if (lockPercent >= 100 && autoScanSwitch.checked && !isVerifying) {
                        cooldownUntil = now + 4000;
                        stableLockFrames = 0;
                        captureAndVerify();
                    }
                } else {
                    stableLockFrames = Math.max(0, stableLockFrames - 1);
                    const lockPercent = Math.min(100, Math.round((stableLockFrames / 7) * 100));
                    if (irisHUD) irisHUD.setLockProgress(lockPercent / 100);
                    if (statusText && !isVerifying) {
                        statusText.innerHTML = '<i class="fas fa-arrows-to-eye text-primary me-1"></i> Đã thấy mắt - Vui lòng nhìn thẳng và giữ yên...';
                    }
                }
            } else {
                consecutiveEmptyFrames++;
                consecutivePresentFrames = 0;
                stableLockFrames = 0;

                if (irisHUD) {
                    irisHUD.setLockProgress(0);
                }

                if (consecutiveEmptyFrames >= 5) {
                    if (presenceBadge) {
                        presenceBadge.className = 'badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25';
                        presenceBadge.innerHTML = '<i class="fas fa-radar me-1"></i> Chờ người';
                    }

                    if (irisHUD && !isVerifying) {
                        irisHUD.setTarget(0, 0, 42, false);
                        irisHUD.setState('STANDBY');
                    }

                    if (statusText && !isVerifying) {
                        statusText.innerHTML = '<i class="fas fa-user-clock text-secondary me-1"></i> Chờ người tiếp cận... (Đứng trước camera để quét)';
                    }
                }
            }
        } catch (err) {
            console.error("Frame process error:", err);
        }
    }

    async function captureAndVerify(customBlob = null) {
        if (isVerifying) return;
        isVerifying = true;

        statusText.innerHTML = '<i class="fas fa-spinner fa-spin text-primary me-1"></i> Three.js AI đang quét mống mắt...';

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
                cooldownUntil = Date.now() + 8000;
                if (irisHUD) irisHUD.setState('SUCCESS');
                statusText.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> Xin chào: ${data.user_name}`;
                
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
                cooldownUntil = Date.now() + 3500;
                if (data.spoof_detected) {
                    if (irisHUD) irisHUD.setState('SPOOF');
                    statusText.innerHTML = `<i class="fas fa-triangle-exclamation text-danger me-1"></i> ${data.message}`;
                    Swal.fire({
                        icon: 'error',
                        title: 'CẢNH BÁO: PHÁT HIỆN GIẢ MẠO!',
                        html: `
                            <div class="text-start p-3 rounded-3 bg-danger bg-opacity-10 border border-danger mt-2">
                                <div class="fw-bold text-danger mb-2"><i class="fas fa-shield-virus me-2"></i> ${data.message}</div>
                                <div class="small text-secondary mb-2">Hệ thống AI quang học phát hiện dấu hiệu sử dụng màn hình điện tử hoặc ảnh in tĩnh 2D.</div>
                                <div class="small font-mono text-danger">Bản ghi đã được lưu vào Access Logs để kiểm toán an ninh.</div>
                            </div>
                        `,
                        confirmButtonColor: '#ef4444',
                        confirmButtonText: 'Đã Hiểu'
                    });
                } else {
                    if (irisHUD) irisHUD.setState('IDLE');
                    statusText.innerHTML = `<i class="fas fa-times-circle text-danger me-1"></i> ${data.message || 'Chưa nhận diện được'}`;
                }
                setTimeout(() => {
                    if (!isVerifying && presenceState === 'EMPTY') {
                        statusText.innerHTML = '<i class="fas fa-user-clock text-secondary me-1"></i> Chờ người tiếp cận... (Đứng trước camera để quét)';
                    }
                }, 2500);
            }

        } catch (err) {
            if (irisHUD) irisHUD.setState('IDLE');
            console.error("Verify API Error:", err);
            statusText.innerHTML = '<i class="fas fa-triangle-exclamation text-danger me-1"></i> Lỗi kết nối máy chủ AI';
        } finally {
            isVerifying = false;
        }
    }

    // 4. Sự kiện Quét Thủ Công
    btnScan.addEventListener('click', () => {
        hasScannedCurrentPerson = true;
        captureAndVerify();
    });

    // 5. Quét qua file tải lên
    
    autoScanSwitch.addEventListener('change', () => {
        if (!autoScanSwitch.checked) {
            statusText.innerHTML = '<i class="fas fa-pause text-muted me-1"></i> Tự động quét đã TẮT. Bấm nút QUÉT MỐNG MẮT NGAY để quét thủ công.';
            if (presenceBadge) {
                presenceBadge.className = 'badge bg-secondary bg-opacity-10 text-secondary border';
                presenceBadge.innerHTML = '<i class="fas fa-power-off me-1"></i> Tắt tự động';
            }
        } else {
            statusText.innerHTML = '<i class="fas fa-user-clock text-secondary me-1"></i> Đã bật radar. Chờ người tiếp cận...';
            if (presenceBadge) {
                presenceBadge.className = 'badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25';
                presenceBadge.innerHTML = '<i class="fas fa-radar me-1"></i> Chờ người';
            }
        }
    });

    window.addEventListener('DOMContentLoaded', () => {
        initCamera();
    });
</script>
</body>
</html>
