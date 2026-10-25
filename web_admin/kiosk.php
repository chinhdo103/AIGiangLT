<?php
/**
 * TRẠM KIOSK KIỂM SOÁT RA VÀO (GIAO DIỆN SÁNG & TỰ ĐỘNG BẮT ĐIỂM MẮT)
 * Nhận diện mống mắt tự động trong toàn bộ khung hình, không cần căn chỉnh vào ô.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_guard.php';

$currentUser = current_user();

// Lấy danh sách phòng lab
$labs = $pdo->query("SELECT * FROM laboratories ORDER BY lab_id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Kiểm Soát Ra Vào - Iris Terminal</title>
    
    <!-- Bootstrap 5.3.2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6.5.1 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Design System (Nền Sáng) -->
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Tracking.js để tự động nhận diện mắt trên khung hình -->
    <script src="assets/js/tracking-min.js"></script>
    <script src="assets/js/face-min.js"></script>
    <script src="assets/js/eye-min.js"></script>

    <style>
        body {
            background-color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .kiosk-navbar {
            padding: 1rem 2rem;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .kiosk-main {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
        }

        .terminal-box {
            width: 100%;
            max-width: 980px;
        }

        .scanner-frame {
            aspect-ratio: 4 / 3;
            max-height: 420px;
            background: #0f172a;
            border-radius: 18px;
            position: relative;
            overflow: hidden;
            border: 2px solid #cbd5e1;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .scanner-frame video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
            display: block;
        }

        .tracking-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .door-status-banner {
            padding: 1.25rem;
            border-radius: 14px;
            text-align: center;
            font-weight: 700;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .door-status-locked {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #e11d48;
        }

        .door-status-unlocked {
            background: #ecfdf5;
            border: 2px solid #10b981;
            color: #059669;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
        }
    </style>
</head>
<body>

    <!-- Header Sáng Thanh Lịch -->
    <header class="kiosk-navbar">
        <div class="d-flex align-items-center gap-3">
            <div class="brand-icon">
                <i class="fas fa-eye"></i>
            </div>
            <div>
                <span class="fw-bold text-dark font-mono fs-5" style="letter-spacing: 0.5px;">TRẠM KIỂM SOÁT CỬA RA VÀO</span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2 font-mono small">
                    <i class="fas fa-circle" style="font-size: 8px;"></i> AUTO-DETECT ACTIVE
                </span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <?php if ($currentUser && $currentUser['is_admin']): ?>
                <a href="dashboard.php" class="btn btn-sm btn-cyber-secondary">
                    <i class="fas fa-gauge-high"></i> Bảng Quản Trị
                </a>
            <?php elseif ($currentUser): ?>
                <a href="user_portal.php" class="btn btn-sm btn-cyber-secondary">
                    <i class="fas fa-id-badge"></i> Cổng Cá Nhân
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-sm btn-cyber-secondary">
                    <i class="fas fa-arrow-right-to-bracket"></i> Đăng Nhập
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Terminal Area -->
    <main class="kiosk-main">
        <div class="terminal-box">
            
            <div class="row g-4 align-items-center">
                
                <!-- Cột Khung Quét Video Tự Động Định Vị Mắt -->
                <div class="col-lg-7">
                    <div class="scanner-frame">
                        <video id="kioskVideo" autoplay playsinline muted></video>
                        <canvas id="kioskOverlayCanvas" class="tracking-canvas"></canvas>
                        <canvas id="kioskCanvas" style="display: none;"></canvas>

                        <!-- Trạng thái nổi phía dưới -->
                        <div class="status-pill text-dark" id="kioskStatusPill">
                            <i class="fas fa-user-clock text-secondary me-1"></i> Chờ người tiếp cận... (Đứng trước camera để mở cửa)
                        </div>
                    </div>

                    <!-- Lựa chọn Camera -->
                    <div class="mt-3 d-flex align-items-center gap-2">
                        <label class="small text-muted flex-shrink-0">
                            <i class="fas fa-video me-1"></i> Camera:
                        </label>
                        <select id="kioskCameraSelect" class="form-select form-select-sm"></select>
                    </div>
                </div>

                <!-- Cột Trạng Thái Cửa & Điều Khiển -->
                <div class="col-lg-5">
                    <div class="glass-card p-4">
                        
                        <div class="mb-4">
                            <label class="form-label text-dark small fw-bold">
                                <i class="fas fa-building text-primary me-1"></i> CỬA KIỂM SOÁT PHÒNG THÍ NGHIỆM:
                            </label>
                            <select id="kioskLabSelect" class="form-select">
                                <?php foreach ($labs as $lab): ?>
                                    <option value="<?= $lab['lab_id'] ?>">
                                        <?= htmlspecialchars($lab['lab_name']) ?> (Cấp độ: <?= $lab['security_level'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Trạng thái Cửa Thực Tế -->
                        <div class="mb-4">
                            <div class="door-status-banner door-status-locked" id="doorBanner">
                                <i class="fas fa-door-closed fs-3" id="doorIcon"></i>
                                <div class="text-start">
                                    <div class="fs-6 fw-bold" id="doorTitle">CỬA ĐANG ĐÓNG (LOCKED)</div>
                                    <div class="small fw-normal text-muted" id="doorSub">Nhìn vào camera để mở cửa</div>
                                </div>
                            </div>
                        </div>

                        <!-- Nút Bật/Tắt Tự Động Quét Phát Hiện Người Tiếp Cận -->
                        <button type="button" class="btn btn-cyber-danger w-100 py-3 mb-3 justify-content-center" id="btnToggleAuto" onclick="toggleAutoScan()" data-active="true">
                            <i class="fas fa-radar me-1"></i> 🔴 ĐANG TỰ ĐỘNG PHÁT HIỆN NGƯỜI (BẤM ĐỂ TẮT)
                        </button>

                        <button type="button" class="btn btn-cyber-secondary w-100 py-2 justify-content-center" onclick="performScan()">
                            <i class="fas fa-camera me-1"></i> Quét Ngay Bằng Tay (Thủ Công)
                        </button>

                        <div class="mt-4 pt-3 border-top small text-muted">
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fas fa-wand-magic-sparkles text-primary me-1"></i> Cơ chế:</span>
                                <span class="text-dark fw-semibold">Tự động bắt điểm mắt (Auto-Crop)</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-shield-halved text-success me-1"></i> Chống giả mạo:</span>
                                <span class="text-success fw-semibold">Liveness Verification</span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>
    </main>

<script src="assets/js/three.min.js"></script>
<script src="assets/js/iris_three_hud.js"></script>

<script>
    const video = document.getElementById('kioskVideo');
    const overlayCanvas = document.getElementById('kioskOverlayCanvas');
    const overlayCtx = overlayCanvas.getContext('2d');
    const canvas = document.getElementById('kioskCanvas');
    const ctx = canvas.getContext('2d');
    const cameraSelect = document.getElementById('kioskCameraSelect');
    const labSelect = document.getElementById('kioskLabSelect');
    const statusPill = document.getElementById('kioskStatusPill');
    const doorBanner = document.getElementById('doorBanner');
    const doorIcon = document.getElementById('doorIcon');
    const doorTitle = document.getElementById('doorTitle');
    const doorSub = document.getElementById('doorSub');
    const btnToggle = document.getElementById('btnToggleAuto');

    let currentStream = null;
    let isScanning = false;
    let audioCtx = null;
    let trackerTask = null;
    let isAutoPresenceActive = true;

    // Quản lý Trạng thái Phát hiện Người & Khóa nét Kiosk (Smart Presence State Machine)
    let presenceState = 'EMPTY';
    let consecutiveEmptyFrames = 0;
    let consecutivePresentFrames = 0;
    let stableLockFrames = 0;
    const REQUIRED_STABLE_FRAMES = 12; // ~500-600ms giữ ổn định mắt
    let lastTrackPos = { x: 0, y: 0 };
    let hasScannedCurrentPerson = false;
    let cooldownUntil = 0;

    // Khởi tạo Three.js Biometric Hologram HUD
    let kioskHUD = null;
    window.addEventListener('DOMContentLoaded', () => {
        const frameBox = document.querySelector('.scanner-frame');
        if (frameBox && typeof IrisThreeHUD !== 'undefined') {
            kioskHUD = new IrisThreeHUD(frameBox, { soundEnabled: true });
        }
    });

    function playBeep(type) {
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);

            if (type === 'success') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(880.00, audioCtx.currentTime + 0.15);
                gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.5);
            } else {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(150, audioCtx.currentTime);
                osc.frequency.setValueAtTime(110, audioCtx.currentTime + 0.15);
                gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.4);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.4);
            }
        } catch (e) {}
    }

    async function initKioskCamera(deviceId = null) {
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
            statusPill.innerHTML = '<i class="fas fa-eye text-primary me-1"></i> Camera hoạt động • Hãy nhìn tự nhiên';
            await loadKioskDevices();
            startSmartEyeTracking();
        } catch (e) {
            console.error(e);
            statusPill.innerHTML = '<i class="fas fa-triangle-exclamation text-danger me-1"></i> Không thể truy cập Camera';
        }
    }

    async function loadKioskDevices() {
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

    cameraSelect.addEventListener('change', () => initKioskCamera(cameraSelect.value));

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

            return (minLum <= 80 && maxLum >= 125 && contrast >= 50 && meanLum >= 35 && meanLum <= 185);
        } catch (e) {
            return true;
        }
    }

    // Bắt điểm mắt & khuôn mặt tự động bằng Tracking.js (Dual Face & Eye Tracker)
    function startSmartEyeTracking() {
        try {
            if (typeof tracking !== 'undefined') {
                const tracker = new tracking.ObjectTracker(['face', 'eye']);
                tracker.setStepSize(1.6);
                tracker.setScaleFactor(1.15);
                tracker.setMinDimension(28); // Hỗ trợ cả mắt nhỏ và khoảng cách xa

                tracker.on('track', function(event) {
                    const cw = video.offsetWidth;
                    const ch = video.offsetHeight;
                    if (!cw || !ch) return;

                    overlayCanvas.width = cw;
                    overlayCanvas.height = ch;
                    overlayCtx.clearRect(0, 0, cw, ch);

                    if (event.data && event.data.length > 0) {
                        const faces = event.data.filter(r => r.width >= 65);
                        const rawEyes = event.data.filter(r => r.width >= 24 && r.width < 160);

                        let validEyes = [];

                        if (rawEyes.length > 0) {
                            validEyes = rawEyes;
                        } else if (faces.length > 0) {
                            const f = faces[0];
                            const eyeW = Math.round(f.width * 0.28);
                            const eyeH = Math.round(f.height * 0.24);
                            const eyeY = Math.round(f.y + f.height * 0.30);
                            validEyes = [
                                { x: Math.round(f.x + f.width * 0.18), y: eyeY, width: eyeW, height: eyeH },
                                { x: Math.round(f.x + f.width * 0.54), y: eyeY, width: eyeW, height: eyeH }
                            ];
                        }

                        if (validEyes.length > 2) {
                            validEyes.sort((a, b) => b.width - a.width);
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
                                const cornerLen = Math.max(10, Math.min(22, dispW * 0.28));

                                overlayCtx.shadowColor = 'rgba(16, 185, 129, 0.8)';
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
                                overlayCtx.lineTo(dispX + dispW, dispY + dispH);
                                overlayCtx.lineTo(dispX + dispW, dispY + dispH - cornerLen);
                                overlayCtx.stroke();

                                overlayCtx.shadowBlur = 0;
                                overlayCtx.strokeStyle = 'rgba(52, 211, 153, 0.85)';
                                overlayCtx.lineWidth = 1.5;
                                overlayCtx.beginPath();
                                overlayCtx.moveTo(cx - 5, cy); overlayCtx.lineTo(cx + 5, cy);
                                overlayCtx.moveTo(cx, cy - 5); overlayCtx.lineTo(cx, cy + 5);
                                overlayCtx.stroke();

                                const label = (validEyes.length === 2)
                                    ? (idx === 0 ? 'MẮT PHẢI' : 'MẮT TRÁI')
                                    : 'KHÓA NÉT MẮT';

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

                            consecutiveEmptyFrames = 0;
                            consecutivePresentFrames++;

                            const primary = validEyes[0];
                            const dispX = cw - (primary.x + primary.width);
                            const cx = dispX + primary.width / 2;
                            const cy = primary.y + primary.height / 2;

                            if (kioskHUD) {
                                kioskHUD.setTarget(cx, cy, primary.width / 2, true);
                            }

                            const now = Date.now();

                            // Cooldown ngắn 2.8s sau lần quét trước
                            if (now < cooldownUntil) {
                                if (statusPill && !isScanning) {
                                    statusPill.innerHTML = '<i class="fas fa-clock text-info me-1"></i> Đang chuẩn bị phiên tiếp theo...';
                                }
                                if (kioskHUD && !isScanning) {
                                    kioskHUD.setState('COOLDOWN');
                                }
                                return;
                            }

                            // Đo độ ổn định vị trí (Jitter Check: ΔX, ΔY < 38px)
                            const jitter = Math.hypot(cx - lastTrackPos.x, cy - lastTrackPos.y);
                            lastTrackPos = { x: cx, y: cy };

                            if (jitter < 38) {
                                stableLockFrames++;
                                const lockPercent = Math.min(100, Math.round((stableLockFrames / 5) * 100)); // 5 frames (~350ms) là đủ!

                                if (kioskHUD) {
                                    kioskHUD.setState('LOCKING');
                                    kioskHUD.setLockProgress(lockPercent / 100);
                                }

                                if (statusPill && !isScanning) {
                                    statusPill.innerHTML = `<i class="fas fa-bullseye text-warning fa-spin me-1"></i> [ĐÃ THẤY MẮT] Khóa nét: <strong>${lockPercent}%</strong> (Giữ yên khuôn mặt)...`;
                                }

                                if (lockPercent >= 100 && isAutoPresenceActive && !isScanning) {
                                    cooldownUntil = now + 3200;
                                    stableLockFrames = 0;
                                    performScan();
                                }
                            } else {
                                stableLockFrames = Math.max(0, stableLockFrames - 1);
                                const lockPercent = Math.min(100, Math.round((stableLockFrames / 5) * 100));
                                if (kioskHUD) kioskHUD.setLockProgress(lockPercent / 100);
                                if (statusPill && !isScanning) {
                                    statusPill.innerHTML = '<i class="fas fa-arrows-to-eye text-primary me-1"></i> Đã thấy mắt - Vui lòng nhìn thẳng và giữ yên...';
                                }
                            }
                        } else {
                            handleKioskNoPerson();
                        }
                    } else {
                        handleKioskNoPerson();
                    }
                });

                if (trackerTask) trackerTask.stop();
                trackerTask = tracking.track('#kioskVideo', tracker);
            }
        } catch (e) {}
    }

    // Xử lý khi không có người trong tầm nhìn Kiosk
    function handleKioskNoPerson() {
        consecutiveEmptyFrames++;
        consecutivePresentFrames = 0;
        stableLockFrames = 0;

        if (kioskHUD) {
            kioskHUD.setLockProgress(0);
        }

        // Khi khung hình trống > 20 frame (~1.0s không thấy ai trước camera)
        // -> Xác nhận người cũ ĐÃ RỜI ĐI, sẵn sàng đón NGƯỜI MỚI!
        if (consecutiveEmptyFrames > 20) {
            hasScannedCurrentPerson = false;
            presenceState = 'EMPTY';
            cooldownUntil = 0;

            if (kioskHUD && !isScanning) {
                kioskHUD.setTarget(0, 0, 45, false);
                kioskHUD.setState('STANDBY');
            }

            if (statusPill && !isScanning) {
                statusPill.innerHTML = '<i class="fas fa-user-clock text-secondary me-1"></i> Chờ người tiếp cận... (Đứng trước camera để mở cửa)';
            }
        }
    }

    async function performScan() {
        if (isScanning) return;
        isScanning = true;

        statusPill.innerHTML = '<i class="fas fa-spinner fa-spin text-primary me-1"></i> Three.js AI đang quét sinh trắc học...';

        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.95));

        const formData = new FormData();
        formData.append('eye_image', blob, 'kiosk_capture.jpg');
        formData.append('lab_id', labSelect.value);

        try {
            const resp = await fetch('verify_process.php', {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();

            if (data.status === 'success' && data.door === 'UNLOCKED') {
                cooldownUntil = Date.now() + 8000;
                if (kioskHUD) kioskHUD.setState('SUCCESS');
                playBeep('success');
                doorBanner.className = 'door-status-banner door-status-unlocked';
                doorIcon.className = 'fas fa-door-open fs-2 text-success';
                doorTitle.innerText = `CỬA ĐÃ MỞ (UNLOCKED)`;
                doorSub.innerText = `Độ khớp: ${data.confidence}% • ${data.ai_message}`;
                statusPill.innerHTML = `<i class="fas fa-circle-check text-success me-1"></i> Mở cửa thành công (${data.confidence}%)`;

                setTimeout(() => {
                    doorBanner.className = 'door-status-banner door-status-locked';
                    doorIcon.className = 'fas fa-door-closed fs-3';
                    doorTitle.innerText = `CỬA ĐANG ĐÓNG (LOCKED)`;
                    doorSub.innerText = `Nhìn vào camera để mở cửa`;
                    if (presenceState === 'EMPTY') {
                        statusPill.innerHTML = '<i class="fas fa-user-clock text-secondary me-1"></i> Chờ người tiếp cận... (Đứng trước camera để mở cửa)';
                    }
                }, 8000);

            } else {
                cooldownUntil = Date.now() + 3500;
                if (data.spoof_detected) {
                    if (kioskHUD) kioskHUD.setState('SPOOF');
                    playBeep('error');
                    doorBanner.className = 'door-status-banner door-status-locked border-danger bg-danger bg-opacity-10';
                    doorIcon.className = 'fas fa-shield-virus fs-2 text-danger';
                    doorTitle.innerText = `CẢNH BÁO: PHÁT HIỆN GIẢ MẠO!`;
                    doorSub.innerText = data.ai_message;
                    statusPill.innerHTML = `<i class="fas fa-triangle-exclamation text-danger me-1"></i> ${data.ai_message}`;
                } else {
                    if (kioskHUD) kioskHUD.setState('IDLE');
                    playBeep('error');
                    doorBanner.className = 'door-status-banner door-status-locked';
                    doorIcon.className = 'fas fa-shield-virus fs-3 text-danger';
                    doorTitle.innerText = `TỪ CHỐI TRUY CẬP!`;
                    doorSub.innerText = data.ai_message || 'Mống mắt không khớp';
                    statusPill.innerHTML = `<i class="fas fa-circle-xmark text-danger me-1"></i> ${data.ai_message || 'Không khớp'}`;
                }

                setTimeout(() => {
                    if (!isScanning && presenceState === 'EMPTY') {
                        statusPill.innerHTML = '<i class="fas fa-user-clock text-secondary me-1"></i> Chờ người tiếp cận... (Đứng trước camera để mở cửa)';
                    }
                }, 2500);
            }

        } catch (err) {
            if (kioskHUD) kioskHUD.setState('IDLE');
            console.error(err);
            statusPill.innerHTML = '<i class="fas fa-triangle-exclamation text-danger me-1"></i> Lỗi kết nối máy chủ AI';
        } finally {
            isScanning = false;
        }
    }

    function toggleAutoScan() {
        isAutoPresenceActive = !isAutoPresenceActive;
        if (!isAutoPresenceActive) {
            btnToggle.setAttribute('data-active', 'false');
            btnToggle.innerHTML = '<i class="fas fa-play me-1"></i> 🟢 BẬT TỰ ĐỘNG PHÁT HIỆN NGƯỜI TIẾP CẬN';
            btnToggle.className = 'btn btn-cyber-primary w-100 py-3 mb-3 justify-content-center';
            statusPill.innerHTML = '<i class="fas fa-pause me-1"></i> Tự động quét đã TẮT (Dùng nút Quét Bằng Tay bên dưới)';
        } else {
            btnToggle.setAttribute('data-active', 'true');
            btnToggle.innerHTML = '<i class="fas fa-radar me-1"></i> 🔴 ĐANG TỰ ĐỘNG PHÁT HIỆN NGƯỜI (BẤM ĐỂ TẮT)';
            btnToggle.className = 'btn btn-cyber-danger w-100 py-3 mb-3 justify-content-center';
            statusPill.innerHTML = '<i class="fas fa-user-clock text-secondary me-1"></i> Đã bật radar. Chờ người tiếp cận...';
        }
    }

    window.addEventListener('DOMContentLoaded', () => {
        initKioskCamera();
    });
</script>
</body>
</html>
