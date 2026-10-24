/**
 * IRIS THREE.JS HOLOGRAPHIC BIOMETRIC HUD
 * Giao diện quét sinh trắc học 3D Không gian tương lai tích hợp Three.js
 * Cung cấp:
 * 1. Vòng xoay Hologram đa tầng (Concentric Biometric Rings)
 * 2. Bán cầu giác mạc 3D Wireframe Cornea Dome
 * 3. Chùm tia quét Laser quét dọc phát sáng (Scanning Laser Sweep)
 * 4. Mạng hạt dữ liệu sinh trắc (Biometric Particle Constellation)
 * 5. Khung ngắm bắt mục tiêu giảm chấn động mượt mà (Damped Target Reticle)
 * 6. Âm thanh Synthesizer không phụ thuộc file ngoài (Web Audio API)
 * 7. Nhận diện trạng thái: SEARCHING -> LOCKING -> SPOOF ALERT / SUCCESS
 */

class IrisThreeHUD {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.querySelector(container) : container;
        if (!this.container) {
            console.error('[IrisThreeHUD] Container không tồn tại:', container);
            return;
        }

        this.options = Object.assign({
            primaryColor: 0x00e5ff,   // Cyan Neon tương lai
            lockColor: 0x10b981,      // Emerald Green khi khóa mục tiêu
            spoofColor: 0xef4444,     // Crimson Red khi phát hiện giả mạo
            successColor: 0x22c55e,   // Brilliant Green khi khớp thành công
            particleCount: 70,
            soundEnabled: true
        }, options);

        this.state = 'IDLE'; // IDLE | TRACKING | SPOOF | SUCCESS
        this.targetPos = { x: 0, y: 0, radius: 45, detected: false };
        this.currentPos = { x: 0, y: 0, radius: 45 };
        this.animationId = null;
        this.audioCtx = null;
        this.lastSoundTime = 0;

        this.initThree();
        this.initAudio();
        this.buildBiometricMeshes();
        this.handleResize();
        this.animate();

        window.addEventListener('resize', () => this.handleResize());
    }

    initThree() {
        // Tạo Three.js Canvas
        this.canvas = document.createElement('canvas');
        this.canvas.className = 'iris-three-canvas';
        this.canvas.style.position = 'absolute';
        this.canvas.style.top = '0';
        this.canvas.style.left = '0';
        this.canvas.style.width = '100%';
        this.canvas.style.height = '100%';
        this.canvas.style.pointerEvents = 'none';
        this.canvas.style.zIndex = '10';

        // Đảm bảo container có position relative
        const compStyle = window.getComputedStyle(this.container);
        if (compStyle.position === 'static') {
            this.container.style.position = 'relative';
        }
        this.container.appendChild(this.canvas);

        const width = this.container.clientWidth || 640;
        const height = this.container.clientHeight || 480;

        // Renderer
        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            alpha: true,
            antialias: true
        });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

        // Camera Orthographic để khớp pixel hoàn hảo với video
        this.camera = new THREE.OrthographicCamera(
            -width / 2, width / 2,
            height / 2, -height / 2,
            1, 1000
        );
        this.camera.position.z = 500;

        // Scene
        this.scene = new THREE.Scene();

        // Biometric HUD Group
        this.hudGroup = new THREE.Group();
        this.scene.add(this.hudGroup);
    }

    initAudio() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                this.audioCtx = new AudioContext();
            }
        } catch (e) {
            this.audioCtx = null;
        }
    }

    playSound(type) {
        if (!this.options.soundEnabled || !this.audioCtx) return;
        const now = Date.now();
        if (now - this.lastSoundTime < 300) return; // Tránh dồn âm thanh quá dày
        this.lastSoundTime = now;

        if (this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }

        const ctx = this.audioCtx;
        const t = ctx.currentTime;

        if (type === 'lock') {
            // Âm chirp khóa mục tiêu mống mắt (800Hz -> 1400Hz)
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(800, t);
            osc.frequency.exponentialRampToValueAtTime(1400, t + 0.08);

            gain.gain.setValueAtTime(0.08, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.09);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(t);
            osc.stop(t + 0.09);

        } else if (type === 'spoof') {
            // Âm báo động giả mạo (Square wave buzzer)
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, t);
            osc.frequency.setValueAtTime(180, t + 0.1);
            osc.frequency.setValueAtTime(150, t + 0.2);

            gain.gain.setValueAtTime(0.15, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.35);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(t);
            osc.stop(t + 0.35);

        } else if (type === 'success') {
            // Hợp âm thành công du dương (523Hz + 659Hz + 784Hz - C Major)
            [523.25, 659.25, 783.99].forEach((freq, idx) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, t + idx * 0.04);

                gain.gain.setValueAtTime(0.1, t + idx * 0.04);
                gain.gain.exponentialRampToValueAtTime(0.001, t + idx * 0.04 + 0.4);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(t + idx * 0.04);
                osc.stop(t + idx * 0.04 + 0.4);
            });
        }
    }

    buildBiometricMeshes() {
        // 1. Vòng tròn ngoài (Outer Hologram Ring)
        const outerGeo = new THREE.RingGeometry(58, 62, 64);
        this.outerMat = new THREE.MeshBasicMaterial({
            color: this.options.primaryColor,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.8
        });
        this.outerRing = new THREE.Mesh(outerGeo, this.outerMat);
        this.hudGroup.add(this.outerRing);

        // 2. Vạch khấc chia độ (Ticks Ring)
        const tickGroup = new THREE.Group();
        const tickGeo = new THREE.PlaneGeometry(1.5, 6);
        const tickMat = new THREE.MeshBasicMaterial({
            color: this.options.primaryColor,
            transparent: true,
            opacity: 0.6
        });
        for (let i = 0; i < 24; i++) {
            const tick = new THREE.Mesh(tickGeo, tickMat);
            const angle = (i / 24) * Math.PI * 2;
            tick.position.x = Math.cos(angle) * 70;
            tick.position.y = Math.sin(angle) * 70;
            tick.rotation.z = angle - Math.PI / 2;
            tickGroup.add(tick);
        }
        this.ticksRing = tickGroup;
        this.hudGroup.add(this.ticksRing);

        // 3. Vòng tròn trong (Inner Reticle Ring)
        const innerGeo = new THREE.RingGeometry(38, 40, 48);
        this.innerMat = new THREE.MeshBasicMaterial({
            color: this.options.primaryColor,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.9
        });
        this.innerRing = new THREE.Mesh(innerGeo, this.innerMat);
        this.hudGroup.add(this.innerRing);

        // 4. Bán cầu giác mạc 3D Wireframe (Corneal Dome)
        const domeGeo = new THREE.SphereGeometry(32, 16, 12, 0, Math.PI * 2, 0, Math.PI * 0.55);
        this.domeMat = new THREE.MeshBasicMaterial({
            color: this.options.primaryColor,
            wireframe: true,
            transparent: true,
            opacity: 0.55
        });
        this.corneaDome = new THREE.Mesh(domeGeo, this.domeMat);
        this.corneaDome.rotation.x = Math.PI / 2;
        this.hudGroup.add(this.corneaDome);

        // 5. Tia quét Laser quét dọc (Scanning Laser Beam)
        const laserGeo = new THREE.PlaneGeometry(120, 2);
        this.laserMat = new THREE.MeshBasicMaterial({
            color: this.options.primaryColor,
            transparent: true,
            opacity: 0.85
        });
        this.laserBeam = new THREE.Mesh(laserGeo, this.laserMat);
        this.hudGroup.add(this.laserBeam);

        // 6. Khung ngắm góc 4 góc (Cyber Target Brackets)
        this.bracketsGroup = new THREE.Group();
        const bLineGeo = new THREE.BufferGeometry();
        const bMat = new THREE.LineBasicMaterial({
            color: this.options.primaryColor,
            transparent: true,
            opacity: 0.85,
            linewidth: 2
        });

        const corners = [
            [-1, 1],  // Top Left
            [1, 1],   // Top Right
            [-1, -1], // Bottom Left
            [1, -1]   // Bottom Right
        ];

        corners.forEach(([sx, sy]) => {
            const pts = [
                new THREE.Vector3(sx * 45, sy * 30, 0),
                new THREE.Vector3(sx * 45, sy * 45, 0),
                new THREE.Vector3(sx * 30, sy * 45, 0)
            ];
            const g = new THREE.BufferGeometry().setFromPoints(pts);
            const line = new THREE.Line(g, bMat);
            this.bracketsGroup.add(line);
        });
        this.hudGroup.add(this.bracketsGroup);

        // 7. Mây hạt dữ liệu sinh trắc (Biometric Particle Constellation)
        const pCount = this.options.particleCount;
        const pGeo = new THREE.BufferGeometry();
        const pPositions = new Float32Array(pCount * 3);
        const pAngles = [];
        const pRadii = [];

        for (let i = 0; i < pCount; i++) {
            const angle = Math.random() * Math.PI * 2;
            const r = 25 + Math.random() * 45;
            pAngles.push(angle);
            pRadii.push(r);
            pPositions[i * 3] = Math.cos(angle) * r;
            pPositions[i * 3 + 1] = Math.sin(angle) * r;
            pPositions[i * 3 + 2] = (Math.random() - 0.5) * 20;
        }

        pGeo.setAttribute('position', new THREE.BufferAttribute(pPositions, 3));
        this.particleAngles = pAngles;
        this.particleRadii = pRadii;

        this.particleMat = new THREE.PointsMaterial({
            color: this.options.primaryColor,
            size: 2.5,
            transparent: true,
            opacity: 0.8
        });
        this.particles = new THREE.Points(pGeo, this.particleMat);
        this.hudGroup.add(this.particles);

        // 8. Sóng nổ xung kích thành công (Success Shockwave Ring)
        const burstGeo = new THREE.RingGeometry(20, 24, 48);
        this.burstMat = new THREE.MeshBasicMaterial({
            color: this.options.successColor,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0
        });
        this.burstRing = new THREE.Mesh(burstGeo, this.burstMat);
        this.burstRing.visible = false;
        this.hudGroup.add(this.burstRing);
    }

    setTarget(x, y, radius = 45, detected = true) {
        // Quy đổi tọa độ video (top-left: 0, 0) sang hệ tọa độ Three.js (center: 0, 0, Y ngược)
        const w = this.container.clientWidth || 640;
        const h = this.container.clientHeight || 480;

        if (detected) {
            this.targetPos.x = x - w / 2;
            this.targetPos.y = (h / 2) - y;
            this.targetPos.radius = Math.max(30, Math.min(90, radius));
            this.targetPos.detected = true;
            if (this.state === 'IDLE') {
                this.setState('TRACKING');
            }
        } else {
            this.targetPos.detected = false;
            if (this.state === 'TRACKING') {
                this.setState('IDLE');
            }
        }
    }

    setState(newState, detailMessage = '') {
        if (this.state === newState && newState !== 'SPOOF') return;
        this.state = newState;

        let activeColor = this.options.primaryColor;

        if (newState === 'TRACKING') {
            activeColor = this.options.lockColor;
            this.playSound('lock');
        } else if (newState === 'SPOOF') {
            activeColor = this.options.spoofColor;
            this.playSound('spoof');
            this.triggerSpoofGlitch();
        } else if (newState === 'SUCCESS') {
            activeColor = this.options.successColor;
            this.playSound('success');
            this.triggerSuccessBurst();
        } else {
            activeColor = this.options.primaryColor;
        }

        // Cập nhật màu các thành phần Three.js
        const col = new THREE.Color(activeColor);
        this.outerMat.color.copy(col);
        this.innerMat.color.copy(col);
        this.domeMat.color.copy(col);
        this.laserMat.color.copy(col);
        this.particleMat.color.copy(col);
        this.ticksRing.children.forEach(c => c.material.color.copy(col));
        this.bracketsGroup.children.forEach(c => c.material.color.copy(col));
    }

    triggerSpoofGlitch() {
        // Hiệu ứng giật nháy cảnh báo đỏ khi phát hiện ảnh giả / màn hình
        let count = 0;
        const glitchInterval = setInterval(() => {
            const flash = (count % 2 === 0);
            this.outerMat.opacity = flash ? 1.0 : 0.2;
            this.corneaDome.scale.set(flash ? 1.3 : 0.9, flash ? 1.3 : 0.9, flash ? 1.3 : 0.9);
            count++;
            if (count > 8) {
                clearInterval(glitchInterval);
                this.outerMat.opacity = 0.8;
                this.corneaDome.scale.set(1, 1, 1);
            }
        }, 80);
    }

    triggerSuccessBurst() {
        this.burstRing.visible = true;
        this.burstRing.scale.set(0.5, 0.5, 0.5);
        this.burstMat.opacity = 1.0;

        let burstProgress = 0.5;
        const anim = () => {
            burstProgress += 0.08;
            this.burstRing.scale.set(burstProgress, burstProgress, burstProgress);
            this.burstMat.opacity = Math.max(0, 1.0 - (burstProgress - 0.5) * 0.7);

            if (burstProgress < 2.2) {
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

        // Giảm chấn chuyển động (Smooth Damping Interpolation)
        const lerpFactor = this.targetPos.detected ? 0.18 : 0.06;
        const targetX = this.targetPos.detected ? this.targetPos.x : 0;
        const targetY = this.targetPos.detected ? this.targetPos.y : 0;

        this.currentPos.x += (targetX - this.currentPos.x) * lerpFactor;
        this.currentPos.y += (targetY - this.currentPos.y) * lerpFactor;

        this.hudGroup.position.x = this.currentPos.x;
        this.hudGroup.position.y = this.currentPos.y;

        // Xoay các vòng Hologram với vận tốc khác nhau
        const speedMult = (this.state === 'TRACKING') ? 2.2 : (this.state === 'SPOOF' ? 4.0 : 1.0);
        this.outerRing.rotation.z += 0.012 * speedMult;
        this.ticksRing.rotation.z -= 0.008 * speedMult;
        this.innerRing.rotation.z += 0.018 * speedMult;

        // Xoay 3D Bán cầu giác mạc Wireframe theo góc nhìn
        this.corneaDome.rotation.z += 0.015 * speedMult;
        this.corneaDome.rotation.y = Math.sin(time) * 0.35;
        this.corneaDome.rotation.x = Math.PI / 2 + Math.cos(time * 0.8) * 0.25;

        // Tia quét Laser quét dọc
        const scanRange = 40;
        this.laserBeam.position.y = Math.sin(time * 3 * speedMult) * scanRange;
        this.laserMat.opacity = 0.5 + Math.abs(Math.sin(time * 3 * speedMult)) * 0.45;

        // Cập nhật vị trí các hạt sinh trắc
        const posAttr = this.particles.geometry.attributes.position;
        const positions = posAttr.array;
        for (let i = 0; i < this.options.particleCount; i++) {
            this.particleAngles[i] += 0.015 * (i % 2 === 0 ? 1 : -1) * speedMult;
            const r = this.particleRadii[i] + Math.sin(time * 2 + i) * 3;
            positions[i * 3] = Math.cos(this.particleAngles[i]) * r;
            positions[i * 3 + 1] = Math.sin(this.particleAngles[i]) * r;
        }
        posAttr.needsUpdate = true;

        this.renderer.render(this.scene, this.camera);
    }

    destroy() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
        if (this.canvas && this.canvas.parentNode) {
            this.canvas.parentNode.removeChild(this.canvas);
        }
        if (this.renderer) {
            this.renderer.dispose();
        }
    }
}

window.IrisThreeHUD = IrisThreeHUD;
