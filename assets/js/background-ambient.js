(function () {
    'use strict';

    var root = document.getElementById('w3fa-bg-root');
    var canvas = document.getElementById('w3fa-bg-canvas');
    if (!root || !canvas || root.getAttribute('data-w3fa-bg-variant') !== 'hero') {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var MOBILE_MAX = 767;
    var ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }

    var particles = [];
    var rafId = 0;
    var running = true;
    var linkDist = 0;
    var resizeTimer = 0;
    var scrollRaf = 0;

    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function isMobileViewport() {
        return window.innerWidth <= MOBILE_MAX;
    }

    function shouldRun() {
        return running && !document.hidden && !prefersReducedMotion() && !isMobileViewport();
    }

    function particleCount(w, h) {
        var base = Math.floor((w * h) / 28000);
        return Math.max(28, Math.min(52, base));
    }

    function initParticles() {
        var w = canvas.width;
        var h = canvas.height;
        var n = particleCount(w, h);
        linkDist = Math.min(140, Math.max(90, Math.sqrt(w * h) * 0.11));
        particles = [];
        for (var i = 0; i < n; i++) {
            particles.push({
                x: Math.random() * w,
                y: Math.random() * h,
                vx: (Math.random() - 0.5) * 0.22,
                vy: (Math.random() - 0.5) * 0.22,
            });
        }
    }

    function resizeCanvas() {
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        var w = window.innerWidth;
        var h = window.innerHeight;
        canvas.width = Math.floor(w * dpr);
        canvas.height = Math.floor(h * dpr);
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        initParticles();
    }

    function updateScrollFade() {
        var vh = window.innerHeight || 1;
        var y = window.scrollY || 0;
        var t = Math.min(1, y / (vh * 0.9));
        root.style.setProperty('--w3fa-bg-fade', String(1 - t * 0.55));
    }

    function tick() {
        if (!shouldRun()) {
            rafId = 0;
            return;
        }

        var w = window.innerWidth;
        var h = window.innerHeight;
        ctx.clearRect(0, 0, w, h);

        var i;
        var j;
        var p;
        var q;
        var dx;
        var dy;
        var dist;

        for (i = 0; i < particles.length; i++) {
            p = particles[i];
            p.x += p.vx;
            p.y += p.vy;
            if (p.x < 0 || p.x > w) {
                p.vx *= -1;
                p.x = Math.max(0, Math.min(w, p.x));
            }
            if (p.y < 0 || p.y > h) {
                p.vy *= -1;
                p.y = Math.max(0, Math.min(h, p.y));
            }
        }

        for (i = 0; i < particles.length; i++) {
            p = particles[i];
            for (j = i + 1; j < particles.length; j++) {
                q = particles[j];
                dx = p.x - q.x;
                dy = p.y - q.y;
                dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < linkDist) {
                    ctx.strokeStyle = 'rgba(251, 191, 36, ' + (0.06 * (1 - dist / linkDist)) + ')';
                    ctx.lineWidth = 0.6;
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                    ctx.lineTo(q.x, q.y);
                    ctx.stroke();
                }
            }
        }

        for (i = 0; i < particles.length; i++) {
            p = particles[i];
            ctx.fillStyle = 'rgba(212, 212, 216, 0.22)';
            ctx.beginPath();
            ctx.arc(p.x, p.y, 1.1, 0, Math.PI * 2);
            ctx.fill();
        }

        rafId = window.requestAnimationFrame(tick);
    }

    function start() {
        if (!shouldRun()) {
            stop();
            return;
        }
        if (!rafId) {
            rafId = window.requestAnimationFrame(tick);
        }
    }

    function stop() {
        if (rafId) {
            window.cancelAnimationFrame(rafId);
            rafId = 0;
        }
        ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
    }

    function onResize() {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(function () {
            if (isMobileViewport()) {
                stop();
                return;
            }
            resizeCanvas();
            start();
        }, 120);
    }

    function onVisibility() {
        running = !document.hidden;
        if (running) {
            start();
        } else {
            stop();
        }
    }

    function onScroll() {
        if (scrollRaf) {
            return;
        }
        scrollRaf = window.requestAnimationFrame(function () {
            scrollRaf = 0;
            updateScrollFade();
        });
    }

    function boot() {
        if (prefersReducedMotion() || isMobileViewport()) {
            return;
        }
        resizeCanvas();
        updateScrollFade();
        start();
    }

    window.addEventListener('resize', onResize, { passive: true });
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('visibilitychange', onVisibility);

    var motionMq = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (typeof motionMq.addEventListener === 'function') {
        motionMq.addEventListener('change', function () {
            if (prefersReducedMotion()) {
                stop();
            } else {
                boot();
            }
        });
    }

    boot();
})();
