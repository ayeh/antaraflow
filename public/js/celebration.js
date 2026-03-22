/**
 * Celebration FX — Ribbons & Fireworks
 * Pure canvas-based celebration animation. No external libraries.
 * Usage: celebrate()
 */
(function () {
    'use strict';

    let isAnimating = false;
    let canvas, ctx, animationId;
    let ribbons = [];
    let fireworks = [];
    let sparks = [];

    const COLORS = [
        '#6c5ce7', '#a855f7', '#63c8ff', '#00d2d3',
        '#ff6b6b', '#feca57', '#48dbfb', '#ff9ff3',
        '#54a0ff', '#f368e0', '#01a3a4', '#5f27cd',
        '#fff', '#ffd700'
    ];

    const isMobile = () => window.innerWidth < 768;

    function randomColor() {
        return COLORS[Math.floor(Math.random() * COLORS.length)];
    }

    function randomBetween(min, max) {
        return min + Math.random() * (max - min);
    }

    // --- Canvas Setup ---

    function createCanvas() {
        if (canvas) return;
        canvas = document.createElement('canvas');
        canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;pointer-events:none;';
        document.body.appendChild(canvas);
        ctx = canvas.getContext('2d');
        resize();
        window.addEventListener('resize', resize);
    }

    function removeCanvas() {
        if (!canvas) return;
        window.removeEventListener('resize', resize);
        canvas.remove();
        canvas = null;
        ctx = null;
    }

    function resize() {
        if (!canvas) return;
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    // --- Ribbon Particle ---

    function createRibbon() {
        const w = canvas.width;
        return {
            x: randomBetween(-50, w + 50),
            y: randomBetween(-600, -30),
            width: randomBetween(8, 14),
            height: randomBetween(20, 40),
            color: randomColor(),
            rotation: randomBetween(0, Math.PI * 2),
            rotationSpeed: randomBetween(-0.08, 0.08),
            speedX: randomBetween(-1.5, 1.5),
            speedY: randomBetween(2, 5),
            wobbleAmplitude: randomBetween(1, 3),
            wobbleFreq: randomBetween(0.02, 0.06),
            wobbleOffset: randomBetween(0, Math.PI * 2),
            opacity: 1,
            tick: 0,
        };
    }

    function updateRibbon(r) {
        r.tick++;
        r.x += r.speedX + Math.sin(r.tick * r.wobbleFreq + r.wobbleOffset) * r.wobbleAmplitude;
        r.y += r.speedY;
        r.rotation += r.rotationSpeed;
        if (r.y > canvas.height + 60) {
            r.opacity -= 0.05;
        }
        return r.opacity > 0;
    }

    function drawRibbon(r) {
        ctx.save();
        ctx.globalAlpha = r.opacity;
        ctx.translate(r.x, r.y);
        ctx.rotate(r.rotation);
        ctx.fillStyle = r.color;
        ctx.fillRect(-r.width / 2, -r.height / 2, r.width, r.height);
        ctx.restore();
    }

    // --- Firework Rocket ---

    function createFirework(delay) {
        return {
            x: randomBetween(canvas.width * 0.15, canvas.width * 0.85),
            startY: canvas.height + 10,
            targetY: randomBetween(canvas.height * 0.1, canvas.height * 0.4),
            y: canvas.height + 10,
            speed: randomBetween(8, 14),
            color: randomColor(),
            trail: [],
            exploded: false,
            delay: delay,
            tick: 0,
        };
    }

    function updateFirework(fw) {
        fw.tick++;
        if (fw.tick < fw.delay / 16) return true; // wait for delay

        if (!fw.exploded) {
            fw.y -= fw.speed;
            fw.trail.push({ x: fw.x, y: fw.y, opacity: 1 });
            if (fw.trail.length > 12) fw.trail.shift();
            fw.trail.forEach(t => { t.opacity -= 0.08; });

            if (fw.y <= fw.targetY) {
                fw.exploded = true;
                explode(fw.x, fw.y);
                return false;
            }
        }
        return true;
    }

    function drawFirework(fw) {
        if (fw.tick < fw.delay / 16) return;
        if (fw.exploded) return;

        // Trail
        fw.trail.forEach(t => {
            if (t.opacity <= 0) return;
            ctx.save();
            ctx.globalAlpha = t.opacity * 0.6;
            ctx.fillStyle = fw.color;
            ctx.beginPath();
            ctx.arc(t.x, t.y, 2, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        });

        // Rocket head
        ctx.save();
        ctx.fillStyle = fw.color;
        ctx.shadowColor = fw.color;
        ctx.shadowBlur = 10;
        ctx.beginPath();
        ctx.arc(fw.x, fw.y, 3, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    // --- Spark (Explosion Particle) ---

    function explode(x, y) {
        const sparkCount = isMobile() ? 25 : randomBetween(40, 50);
        const color = randomColor();
        for (let i = 0; i < sparkCount; i++) {
            const angle = randomBetween(0, Math.PI * 2);
            const velocity = randomBetween(2, 8);
            sparks.push({
                x: x,
                y: y,
                vx: Math.cos(angle) * velocity,
                vy: Math.sin(angle) * velocity,
                color: Math.random() > 0.3 ? color : randomColor(),
                radius: randomBetween(1.5, 3.5),
                opacity: 1,
                decay: randomBetween(0.012, 0.025),
                gravity: 0.06,
                trail: [],
            });
        }
    }

    function updateSpark(s) {
        s.trail.push({ x: s.x, y: s.y, opacity: s.opacity * 0.5 });
        if (s.trail.length > 5) s.trail.shift();
        s.trail.forEach(t => { t.opacity -= 0.15; });

        s.vx *= 0.98;
        s.vy *= 0.98;
        s.vy += s.gravity;
        s.x += s.vx;
        s.y += s.vy;
        s.opacity -= s.decay;
        return s.opacity > 0;
    }

    function drawSpark(s) {
        // Trail
        s.trail.forEach(t => {
            if (t.opacity <= 0) return;
            ctx.save();
            ctx.globalAlpha = t.opacity * 0.4;
            ctx.fillStyle = s.color;
            ctx.beginPath();
            ctx.arc(t.x, t.y, s.radius * 0.6, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        });

        // Spark
        ctx.save();
        ctx.globalAlpha = s.opacity;
        ctx.fillStyle = s.color;
        ctx.shadowColor = s.color;
        ctx.shadowBlur = 8;
        ctx.beginPath();
        ctx.arc(s.x, s.y, s.radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    // --- Animation Loop ---

    function animate() {
        if (!ctx) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Update & draw ribbons
        ribbons = ribbons.filter(r => {
            const alive = updateRibbon(r);
            if (alive) drawRibbon(r);
            return alive;
        });

        // Update & draw fireworks
        fireworks = fireworks.filter(fw => {
            const alive = updateFirework(fw);
            drawFirework(fw);
            return alive;
        });

        // Update & draw sparks
        sparks = sparks.filter(s => {
            const alive = updateSpark(s);
            if (alive) drawSpark(s);
            return alive;
        });

        // Check if all done
        if (ribbons.length === 0 && fireworks.length === 0 && sparks.length === 0) {
            cancelAnimationFrame(animationId);
            removeCanvas();
            isAnimating = false;
            return;
        }

        animationId = requestAnimationFrame(animate);
    }

    // --- Burst Wave ---

    function burstWave(delay) {
        setTimeout(() => {
            if (!canvas) return;

            // Ribbons
            const ribbonCount = isMobile() ? 20 : 47;
            for (let i = 0; i < ribbonCount; i++) {
                ribbons.push(createRibbon());
            }

            // Fireworks
            const fwCount = isMobile() ? 3 : randomBetween(6, 7);
            for (let i = 0; i < fwCount; i++) {
                fireworks.push(createFirework(i * randomBetween(100, 300)));
            }
        }, delay);
    }

    // --- Public API ---

    window.celebrate = function () {
        if (isAnimating) return; // prevent stacking
        isAnimating = true;

        ribbons = [];
        fireworks = [];
        sparks = [];

        createCanvas();

        // 3 burst waves for dramatic effect
        burstWave(0);
        burstWave(1000);
        burstWave(2200);

        animationId = requestAnimationFrame(animate);
    };
})();
