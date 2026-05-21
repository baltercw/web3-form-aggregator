<?php
/**
 * 全站背景裝飾。使用前可設定 $w3faBgVariant = 'hero' | 'subtle'（預設 subtle）。
 */
$w3faBgVariant = $w3faBgVariant ?? 'subtle';
if (!in_array($w3faBgVariant, ['hero', 'subtle'], true)) {
    $w3faBgVariant = 'subtle';
}
$w3faBgIsHero = $w3faBgVariant === 'hero';
?>
<style>
    .w3fa-bg-root {
        --w3fa-bg-fade: 1;
        --w3fa-blob-a: rgba(251, 191, 36, 0.14);
        --w3fa-blob-b: rgba(251, 191, 36, 0.08);
        --w3fa-blob-c: rgba(255, 255, 255, 0.05);
        --w3fa-grid-opacity: 0.18;
        --w3fa-vignette-mid: rgba(11, 11, 11, 0.35);
        --w3fa-vignette-end: rgba(11, 11, 11, 0.92);
    }
    .w3fa-bg-root--hero {
        --w3fa-blob-a: rgba(251, 191, 36, 0.18);
        --w3fa-blob-b: rgba(251, 191, 36, 0.11);
        --w3fa-blob-c: rgba(255, 255, 255, 0.07);
        --w3fa-grid-opacity: 0.18;
        --w3fa-vignette-mid: rgba(11, 11, 11, 0.42);
        --w3fa-vignette-end: rgba(11, 11, 11, 0.95);
    }
    .w3fa-bg-root--subtle {
        --w3fa-blob-a: rgba(251, 191, 36, 0.09);
        --w3fa-blob-b: rgba(251, 191, 36, 0.05);
        --w3fa-blob-c: rgba(255, 255, 255, 0.03);
        --w3fa-grid-opacity: 0.16;
        --w3fa-vignette-mid: rgba(11, 11, 11, 0.28);
        --w3fa-vignette-end: rgba(11, 11, 11, 0.88);
    }
    .w3fa-bg-blob {
        position: absolute;
        border-radius: 9999px;
        filter: blur(72px);
        will-change: transform;
    }
    .w3fa-bg-blob-a {
        width: min(52vw, 520px);
        height: min(52vw, 520px);
        left: -8%;
        top: -18%;
        background: var(--w3fa-blob-a);
    }
    .w3fa-bg-blob-b {
        width: min(44vw, 440px);
        height: min(44vw, 440px);
        right: -6%;
        top: 8%;
        background: var(--w3fa-blob-b);
    }
    .w3fa-bg-blob-c {
        width: min(38vw, 380px);
        height: min(38vw, 380px);
        left: 38%;
        bottom: 12%;
        background: var(--w3fa-blob-c);
    }
    .w3fa-bg-grid {
        opacity: var(--w3fa-grid-opacity);
        background-image:
            linear-gradient(to right, rgba(255, 255, 255, 0.055) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(255, 255, 255, 0.055) 1px, transparent 1px);
        background-size: 60px 60px;
    }
    .w3fa-bg-canvas-wrap {
        opacity: calc(0.42 * var(--w3fa-bg-fade));
        transition: opacity 420ms ease-out;
    }
    .w3fa-bg-root--subtle .w3fa-bg-canvas-wrap {
        display: none;
    }
    @media (prefers-reduced-motion: no-preference) {
        .w3fa-bg-root--hero .w3fa-bg-blob-a {
            animation: w3faBlobA 22s ease-in-out infinite alternate;
        }
        .w3fa-bg-root--hero .w3fa-bg-blob-b {
            animation: w3faBlobB 26s ease-in-out infinite alternate;
        }
        .w3fa-bg-root--hero .w3fa-bg-blob-c {
            animation: w3faBlobC 20s ease-in-out infinite alternate;
        }
        .w3fa-bg-root--subtle .w3fa-bg-blob-a {
            animation: w3faBlobSubtleA 32s ease-in-out infinite alternate;
        }
        .w3fa-bg-root--subtle .w3fa-bg-blob-b {
            animation: w3faBlobSubtleB 36s ease-in-out infinite alternate;
        }
    }
    @keyframes w3faBlobA {
        from { transform: translate(0, 0) scale(1); }
        to { transform: translate(6%, 8%) scale(1.08); }
    }
    @keyframes w3faBlobB {
        from { transform: translate(0, 0) scale(1); }
        to { transform: translate(-5%, 6%) scale(1.06); }
    }
    @keyframes w3faBlobC {
        from { transform: translate(0, 0) scale(1); }
        to { transform: translate(4%, -6%) scale(1.05); }
    }
    @keyframes w3faBlobSubtleA {
        from { transform: translate(0, 0) scale(1); }
        to { transform: translate(3%, 4%) scale(1.04); }
    }
    @keyframes w3faBlobSubtleB {
        from { transform: translate(0, 0) scale(1); }
        to { transform: translate(-3%, 3%) scale(1.03); }
    }
</style>
<div
    id="w3fa-bg-root"
    aria-hidden="true"
    class="pointer-events-none fixed inset-0 -z-10 w3fa-bg-root <?php echo $w3faBgIsHero ? 'w3fa-bg-root--hero' : 'w3fa-bg-root--subtle'; ?>"
    data-w3fa-bg-variant="<?php echo htmlspecialchars($w3faBgVariant); ?>"
>
    <div class="absolute inset-0 bg-[#0b0b0b]"></div>
    <div class="absolute inset-0 bg-[radial-gradient(1000px_circle_at_14%_-15%,rgba(251,191,36,0.20),transparent_58%),radial-gradient(900px_circle_at_86%_0%,rgba(255,255,255,0.05),transparent_62%)]"></div>
    <div class="w3fa-bg-blob w3fa-bg-blob-a"></div>
    <div class="w3fa-bg-blob w3fa-bg-blob-b"></div>
    <?php if ($w3faBgIsHero): ?>
        <div class="w3fa-bg-blob w3fa-bg-blob-c"></div>
    <?php endif; ?>
    <div class="absolute inset-0 w3fa-bg-grid"></div>
    <?php if ($w3faBgIsHero): ?>
        <div class="w3fa-bg-canvas-wrap absolute inset-0">
            <canvas id="w3fa-bg-canvas" class="h-full w-full"></canvas>
        </div>
    <?php endif; ?>
    <div
        class="absolute inset-0"
        style="background: linear-gradient(180deg, transparent 0%, var(--w3fa-vignette-mid) 52%, var(--w3fa-vignette-end) 100%);"
    ></div>
</div>
<?php if ($w3faBgIsHero): ?>
    <script src="./assets/js/background-ambient.js" defer></script>
<?php endif; ?>
