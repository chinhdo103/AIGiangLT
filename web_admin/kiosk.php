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

    // HỆ THỐNG THỊ GIÁC MÁY TÍNH & BỘ ĐÔI NHẬN DIỆN MẮT KIOSK (Hybrid Vision & Smart Eye Lock)
    const visionCanvas = document.createElement('canvas');
    visionCanvas.width = 160;
    visionCanvas.height = 120;
    const visionCtx = visionCanvas.getContext('2d', { willReadFrequently: true });
    let lastVisionLums = null;
    let latestTrackerEyes = null;
    let visionLoopTimer = null;

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

            let darkestLum = 255;
            let darkestX = 80;
            let darkestY = 60;

            for (let i = 0; i < data.length; i += 4) {
                const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                sumLum += lum;
                sumSqLum += lum * lum;
                if (lum < minLum) minLum = lum;
                if (lum > maxLum) maxLum = lum;

                if (lastVisionLums) {
                    motionDiff += Math.abs(lum - lastVisionLums[i / 4]);
                }

                const px = (i / 4) % 160;
                const py = Math.floor((i / 4) / 160);
                if (px > 24 && px < 136 && py > 20 && py < 96) {
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

            const hasPerson = (stdDev >= 10 && contrast >= 20 && meanLum >= 15 && meanLum <= 245);
            const isSteady = hasPerson && (avgMotion < 7.5);

            const cw = video.offsetWidth || video.clientWidth || video.videoWidth || 640;
            const ch = video.offsetHeight || video.clientHeight || video.videoHeight || 480;
            const eyeX = (1 - (darkestX / 160)) * cw;
            const eyeY = (darkestY / 120) * ch;

            return {
                hasPerson,
                isSteady,
                avgMotion,
                stdDev,
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

            ctx.beginPath();
            ctx.moveTo(dispX, dispY + cornerLen); ctx.lineTo(dispX, dispY); ctx.lineTo(dispX + cornerLen, dispY);
            ctx.moveTo(dispX + dispW - cornerLen, dispY); ctx.lineTo(dispX + dispW); ctx.lineTo(dispX + dispW, dispY + cornerLen);
            ctx.moveTo(dispX, dispY + dispH - cornerLen); ctx.lineTo(dispX, dispY + dispH); ctx.lineTo(dispX + cornerLen, dispY + dispH);
            ctx.moveTo(dispX + dispW - cornerLen, dispY + dispH); ctx.lineTo(dispX + dispW); ctx.lineTo(dispX + dispW, dispY + cornerLen);
            ctx.stroke();

            ctx.shadowBlur = 0;
            ctx.strokeStyle = 'rgba(52, 211, 153, 0.9)';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(cx - 5, cy); ctx.lineTo(cx + 5, cy);
            ctx.moveTo(cx, cy - 5); ctx.lineTo(cx, cy + 5);
            ctx.stroke();

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

                if (kioskHUD) {
                    kioskHUD.setTarget(targetX, targetY, targetRadius, true);
                }

                if (now < cooldownUntil) {
                    if (statusPill && !isScanning) {
                        statusPill.innerHTML = '<i class="fas fa-clock text-info me-1"></i> Đang chuẩn bị phiên tiếp theo...';
                    }
                    if (kioskHUD && !isScanning) {
                        kioskHUD.setState('COOLDOWN');
                    }
                    return;
                }

                const jitter = Math.hypot(targetX - lastTrackPos.x, targetY - lastTrackPos.y);
                lastTrackPos = { x: targetX, y: targetY };

                if (jitter < 55 || vision.isSteady) {
                    stableLockFrames++;
                    const lockPercent = Math.min(100, Math.round((stableLockFrames / 3) * 100));

                    if (kioskHUD) {
                        kioskHUD.setState('LOCKING');
                        kioskHUD.setLockProgress(lockPercent / 100);
                    }

                    if (statusPill && !isScanning) {
                        statusPill.innerHTML = `<i class="fas fa-bullseye text-warning fa-spin me-1"></i> [ĐÃ THẤY MẮT] Khóa nét: <strong>${lockPercent}%</strong> (Giữ yên)...`;
                    }

                    if (lockPercent >= 100 && isAutoPresenceActive && !isScanning) {
                        cooldownUntil = now + 4000;
                        stableLockFrames = 0;
                        performScan();
                    }
                } else {
                    stableLockFrames = Math.max(0, stableLockFrames - 1);
                    const lockPercent = Math.min(100, Math.round((stableLockFrames / 3) * 100));
                    if (kioskHUD) kioskHUD.setLockProgress(lockPercent / 100);
                    if (statusPill && !isScanning) {
                        statusPill.innerHTML = '<i class="fas fa-arrows-to-eye text-primary me-1"></i> Đã thấy mắt - Vui lòng nhìn thẳng và giữ yên...';
                    }
                }
            } else {
                consecutiveEmptyFrames++;
                consecutivePresentFrames = 0;
                stableLockFrames = 0;

                if (kioskHUD) {
                    kioskHUD.setLockProgress(0);
                }

                if (consecutiveEmptyFrames >= 5) {
                    if (kioskHUD && !isScanning) {
                        kioskHUD.setTarget(0, 0, 42, false);
                        kioskHUD.setState('STANDBY');
                    }

                    if (statusPill && !isScanning) {
                        statusPill.innerHTML = '<i class="fas fa-user-clock text-secondary me-1"></i> Chờ người tiếp cận... (Đứng trước camera để quét)';
                    }
                }
            }
        } catch (err) {
            console.error("Kiosk process error:", err);
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
