/**
 * IRIS THREE.JS FUTURISTIC BIOMETRIC HUD v2.5
 * Thiết kế Tối giản - Tinh tế - Chuẩn Sci-Fi (Apple FaceID / Cyberpunk Style)
 * - Loại bỏ hoàn toàn lưới đa diện thô kệch
 * - Tự động ẩn khung ngắm khi chưa có người (tránh che mặt khi rảnh)
 * - Quét tia Laser mượt mà trên toàn khung hình
 * - Khung ngắm Neon Lục Bảo bám dính chính xác vào tâm mắt
 * - Vòng khóa nét tiến trình 360 độ (0% -> 100%)
 */

class IrisThreeHUD {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.querySelector(container) : container;
        if (!this.container) return;

        this.options = Object.assign({
            primaryColor: 0x06b6d4,   // Cyan Neon hiện đại
            lockColor: 0x10b981,      // Emerald Green khi khóa nét
            spoofColor: 0xef4444,     // Crimson Red khi giả mạo
            successColor: 0x22c55e,   // Brilliant Green khi thành công
            soundEnabled: true
        }, options);

        this.state = 'STANDBY';
        this.targetPos = { x: 0, y: 0, radius: 42, detected: false };
        this.currentPos = { x: 0, y: 0, radius: 42 };
        this.lockProgress = 0;
        this.animationId = null;
        this.audioCtx = null;
        this.lastSoundTime = 0;

        this.initThree();
        this.initAudio();
        this.buildBiometricHUD();
        this.handleResize();
        this.animate();

        window.addEventListener('resize', () => this.handleResize());
    }

    initThree() {
        this.canvas = document.createElement('canvas');
        this.canvas.className = 'iris-three-canvas';
        this.canvas.style.position = 'absolute';
        this.canvas.style.top = '0';
        this.canvas.style.left = '0';
        this.canvas.style.width = '100%';
        this.canvas.style.height = '100%';
        this.canvas.style.pointerEvents = 'none';
        this.canvas.style.zIndex = '10';

        const compStyle = window.getComputedStyle(this.container);
        if (compStyle.position === 'static') {
            this.container.style.position = 'relative';
        }
        this.container.appendChild(this.canvas);

        const width = this.container.clientWidth || 640;
        const height = this.container.clientHeight || 480;

        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            alpha: true,
            antialias: true
        });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

        this.camera = new THREE.OrthographicCamera(
            -width / 2, width / 2,
            height / 2, -height / 2,
            1, 1000
        );
        this.camera.position.z = 500;

        this.scene = new THREE.Scene();
    }

    initAudio() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                this.audioCtx = new AudioContext();
            }
        } catch (e) {}
    }

    playSound(type) {
        if (!this.options.soundEnabled || !this.audioCtx) return;
        const now = Date.now();
        if (now - this.lastSoundTime < 280) return;
        this.lastSoundTime = now;

        if (this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }

        const ctx = this.audioCtx;
        const t = ctx.currentTime;

        if (type === 'lock') {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, t);
            osc.frequency.exponentialRampToValueAtTime(1450, t + 0.07);
            gain.gain.setValueAtTime(0.06, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.08);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(t);
            osc.stop(t + 0.08);

        } else if (type === 'spoof') {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, t);
            osc.frequency.setValueAtTime(160, t + 0.12);
            gain.gain.setValueAtTime(0.12, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.28);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(t);
            osc.stop(t + 0.28);

        } else if (type === 'success') {
            [523.25, 659.25, 783.99].forEach((freq, idx) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, t + idx * 0.05);
                gain.gain.setValueAtTime(0.09, t + idx * 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, t + idx * 0.05 + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(t + idx * 0.05);
                osc.stop(t + idx * 0.05 + 0.35);
            });
        }
    }

    buildBiometricHUD() {
        // 1. TIA QUÉT LASER TOÀN KHUNG HÌNH (Full-frame Ambient Scan Line)
        const w = this.container.clientWidth || 640;
        const sweepGeo = new THREE.PlaneGeometry(w, 2);
        this.sweepMat = new THREE.MeshBasicMaterial({
            color: this.options.primaryColor,
            transparent: true,
            opacity: 0.4
        });
        this.sweepLaser = new THREE.Mesh(sweepGeo, this.sweepMat);
        this.scene.add(this.sweepLaser);

        // 2. KHUNG NGẮM MẮT TƯƠNG LAI (Eye Reticle Group - Chỉ hiện khi phát hiện người!)
        this.reticleGroup = new THREE.Group();
        this.reticleGroup.visible = false; // Mặc định ẩn, không che giữa mặt
        this.scene.add(this.reticleGroup);

        // A. Vòng tròn ngoài viền sáng mỏng
        const outerGeo = new THREE.RingGeometry(44, 46.5, 48);
        this.outerMat = new THREE.MeshBasicMaterial({
            color: this.options.primaryColor,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.85
        });
        this.outerRing = new THREE.Mesh(outerGeo, this.outerMat);
        this.reticleGroup.add(this.outerRing);

        // B. 4 Vạch chia góc 90 độ (Precision Ticks)
        this.ticksGroup = new THREE.Group();
        const tickGeo = new THREE.PlaneGeometry(1.5, 8);
        const tickMat = new THREE.MeshBasicMaterial({
            color: this.options.primaryColor,
            transparent: true,
            opacity: 0.8
        });
        [0, Math.PI / 2, Math.PI, Math.PI * 1.5].forEach(ang => {
            const tick = new THREE.Mesh(tickGeo, tickMat);
            tick.position.x = Math.cos(ang) * 51;
            tick.position.y = Math.sin(ang) * 51;
            tick.rotation.z = ang - Math.PI / 2;
            this.ticksGroup.add(tick);
        });
        this.reticleGroup.add(this.ticksGroup);

        // C. Vòng tròn khóa nét tiến trình (Lock Progress Arc Ring)
        const progressGeo = new THREE.RingGeometry(36, 40, 48);
        this.progressMat = new THREE.MeshBasicMaterial({
            color: this.options.lockColor,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0
        });
        this.progressRing = new THREE.Mesh(progressGeo, this.progressMat);
        this.reticleGroup.add(this.progressRing);

        // D. Tâm chữ thập mảnh ngọc lục bảo (+)
        this.crosshairGroup = new THREE.Group();
        const crossMat = new THREE.LineBasicMaterial({
            color: this.options.primaryColor,
            transparent: true,
            opacity: 0.75,
            linewidth: 1.5
        });
        const crossGeoH = new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(-6, 0, 0), new THREE.Vector3(6, 0, 0)
        ]);
        const crossGeoV = new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(0, -6, 0), new THREE.Vector3(0, 6, 0)
        ]);
        this.crosshairGroup.add(new THREE.Line(crossGeoH, crossMat));
        this.crosshairGroup.add(new THREE.Line(crossGeoV, crossMat));
        this.reticleGroup.add(this.crosshairGroup);

        // E. 4 Góc định vị Cyber (Corners HUD)
        this.bracketsGroup = new THREE.Group();
        const cornerMat = new THREE.LineBasicMaterial({
            color: this.options.primaryColor,
            transparent: true,
            opacity: 0.9,
            linewidth: 2
        });
        const d = 56;
        const cl = 12;
        const corners = [
            [[-d, d - cl], [-d, d], [-d + cl, d]],       // Top Left
            [[d - cl, d], [d, d], [d, d - cl]],          // Top Right
            [[-d, -d + cl], [-d, -d], [-d + cl, -d]],    // Bottom Left
            [[d - cl, -d], [d, -d], [d, -d + cl]]        // Bottom Right
        ];
        corners.forEach(pts => {
            const v3s = pts.map(p => new THREE.Vector3(p[0], p[1], 0));
            const cGeo = new THREE.BufferGeometry().setFromPoints(v3s);
            this.bracketsGroup.add(new THREE.Line(cGeo, cornerMat));
        });
        this.reticleGroup.add(this.bracketsGroup);

        // 3. VÒNG NỔ XUNG QUANG THÀNH CÔNG (Success Shockwave Ring)
        const burstGeo = new THREE.RingGeometry(42, 46, 48);
        this.burstMat = new THREE.MeshBasicMaterial({
            color: this.options.successColor,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0
        });
        this.burstRing = new THREE.Mesh(burstGeo, this.burstMat);
        this.burstRing.visible = false;
        this.scene.add(this.burstRing);
    }

    setLockProgress(progress) {
        this.lockProgress = Math.max(0, Math.min(1, progress));
        if (this.lockProgress <= 0.05) {
            this.progressMat.opacity = 0;
            this.progressRing.scale.set(1.25, 1.25, 1);
        } else {
            // Khi tiến độ tăng, vòng tiến trình co lại khóa chặt vào mắt
            const s = 1.25 - (this.lockProgress * 0.25);
            this.progressRing.scale.set(s, s, 1);
            this.progressMat.opacity = 0.35 + (this.lockProgress * 0.65);
        }
    }

    setTarget(x, y, radius = 42, detected = true) {
        const w = this.container.clientWidth || 640;
        const h = this.container.clientHeight || 480;

        if (detected) {
            this.targetPos.x = x - w / 2;
            this.targetPos.y = (h / 2) - y;
            this.targetPos.radius = radius;
            this.targetPos.detected = true;
            this.reticleGroup.visible = true;

            if (this.state === 'STANDBY' || this.state === 'IDLE') {
                this.setState('DETECTED');
            }
        } else {
            this.targetPos.detected = false;
            if (this.state === 'DETECTED' || this.state === 'LOCKING') {
                this.setState('STANDBY');
                this.setLockProgress(0);
            }
        }
    }

    setState(newState) {
        if (this.state === newState && newState !== 'SPOOF') return;
        this.state = newState;

        let activeColor = this.options.primaryColor;

        if (newState === 'STANDBY' || newState === 'IDLE') {
            activeColor = this.options.primaryColor;
            this.reticleGroup.visible = false; // Ẩn khi chờ người để nhìn tự nhiên
            this.sweepMat.opacity = 0.35;
            this.setLockProgress(0);
        } else if (newState === 'DETECTED') {
            activeColor = 0x38bdf8; // Sky Blue
            this.reticleGroup.visible = true;
            this.outerMat.opacity = 0.85;
            this.sweepMat.opacity = 0.55;
            this.playSound('lock');
        } else if (newState === 'LOCKING') {
            activeColor = this.options.lockColor; // Emerald Green
            this.reticleGroup.visible = true;
            this.outerMat.opacity = 1.0;
            this.sweepMat.opacity = 0.75;
        } else if (newState === 'SCANNING') {
            activeColor = 0x10b981;
            this.reticleGroup.visible = true;
            this.outerMat.opacity = 1.0;
        } else if (newState === 'SPOOF') {
            activeColor = this.options.spoofColor;
            this.reticleGroup.visible = true;
            this.playSound('spoof');
            this.triggerSpoofGlitch();
        } else if (newState === 'SUCCESS') {
            activeColor = this.options.successColor;
            this.playSound('success');
            this.triggerSuccessBurst();
        } else if (newState === 'COOLDOWN') {
            activeColor = 0x64748b;
            this.reticleGroup.visible = false;
            this.sweepMat.opacity = 0.2;
            this.setLockProgress(0);
        }

        const col = new THREE.Color(activeColor);
        this.outerMat.color.copy(col);
        this.sweepMat.color.copy(col);
        this.ticksGroup.children.forEach(c => c.material.color.copy(col));
        this.bracketsGroup.children.forEach(c => c.material.color.copy(col));
        this.crosshairGroup.children.forEach(c => c.material.color.copy(col));
    }

    triggerSpoofGlitch() {
        let count = 0;
        const interval = setInterval(() => {
            const flash = (count % 2 === 0);
            this.outerMat.opacity = flash ? 1.0 : 0.2;
            this.reticleGroup.scale.set(flash ? 1.15 : 0.95, flash ? 1.15 : 0.95, 1);
            count++;
            if (count > 7) {
                clearInterval(interval);
                this.outerMat.opacity = 0.85;
                this.reticleGroup.scale.set(1, 1, 1);
            }
        }, 90);
    }

    triggerSuccessBurst() {
        this.burstRing.position.x = this.currentPos.x;
        this.burstRing.position.y = this.currentPos.y;
        this.burstRing.visible = true;
        this.burstRing.scale.set(1.0, 1.0, 1.0);
        this.burstMat.opacity = 1.0;

        let scale = 1.0;
        const anim = () => {
            scale += 0.08;
            this.burstRing.scale.set(scale, scale, 1);
            this.burstMat.opacity = Math.max(0, 1.0 - (scale - 1.0) * 0.9);

            if (scale < 2.2) {
                requestAnimationFrame(anim);
            } else {
                this.burstRing.visible = false;
            }
        };
        anim();
    }

    handleResize() {
        if (!this.container || !this.renderer) return;
        const w = this.container.clientWidth;
        const h = this.container.clientHeight;
        if (!w || !h) return;

        this.camera.left = -w / 2;
        this.camera.right = w / 2;
        this.camera.top = h / 2;
        this.camera.bottom = -h / 2;
        this.camera.updateProjectionMatrix();

        this.renderer.setSize(w, h);
    }

    animate() {
        this.animationId = requestAnimationFrame(() => this.animate());

        const time = Date.now() * 0.002;
        const h = (this.container.clientHeight || 480);

        // 1. Quét Laser mềm mại dọc khung hình
        const sweepY = Math.sin(time * 2.2) * (h / 2 - 20);
        this.sweepLaser.position.y = sweepY;

        // 2. Chuyển động bám mục tiêu mắt mượt mà
        if (this.targetPos.detected) {
            this.currentPos.x += (this.targetPos.x - this.currentPos.x) * 0.22;
            this.currentPos.y += (this.targetPos.y - this.currentPos.y) * 0.22;
        }

        this.reticleGroup.position.x = this.currentPos.x;
        this.reticleGroup.position.y = this.currentPos.y;

        // 3. Xoay viền công nghệ nhẹ nhàng
        this.outerRing.rotation.z += 0.015;
        this.ticksGroup.rotation.z -= 0.009;

        this.renderer.render(this.scene, this.camera);
    }

    destroy() {
        if (this.animationId) cancelAnimationFrame(this.animationId);
        if (this.canvas && this.canvas.parentNode) {
            this.canvas.parentNode.removeChild(this.canvas);
        }
        if (this.renderer) this.renderer.dispose();
    }
}

window.IrisThreeHUD = IrisThreeHUD;
