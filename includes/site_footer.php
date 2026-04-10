<?php
$message = $message ?? '';
$error = $error ?? '';
$registeredNotice = $registeredNotice ?? false;
$toastSuccess = $toastSuccess ?? '';
$toastError = $toastError ?? '';

$__toastText = '';
$__toastVariant = '';
if (!empty($toastSuccess)) {
    $__toastText = (string) $toastSuccess;
    $__toastVariant = 'success';
} elseif (!empty($toastError)) {
    $__toastText = (string) $toastError;
    $__toastVariant = 'error';
} elseif (!empty($message)) {
    $__toastText = (string) $message;
    $__toastVariant = 'success';
} elseif (!empty($error)) {
    $__toastText = (string) $error;
    $__toastVariant = 'error';
} elseif (!empty($registeredNotice)) {
    $__toastText = '註冊成功，請登入。';
    $__toastVariant = 'success';
}
?>
<footer class="border-t border-white/10 py-8 text-center text-xs leading-relaxed text-zinc-500">
    <p>若您為<strong class="text-zinc-400">項目方</strong>欲發布任務，請來信或聯絡管理員開通帳號（邀請制，不開放自註冊）。</p>
    <p class="mt-2 text-zinc-600">Group 09 · Web3 Task Aggregator</p>
</footer>

<div id="toast-host" class="pointer-events-none fixed left-1/2 top-4 z-[70] w-[min(100%,24rem)] -translate-x-1/2 px-4" aria-live="polite"></div>

<script>
(function () {
    var btn = document.getElementById('nav-toggle');
    var panel = document.getElementById('mobile-nav-panel');
    if (btn && panel) {
        btn.addEventListener('click', function () {
            panel.classList.toggle('hidden');
            btn.setAttribute('aria-expanded', panel.classList.contains('hidden') ? 'false' : 'true');
        });
    }

    var text = <?php echo json_encode($__toastText, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    var variant = <?php echo json_encode($__toastVariant, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    if (!text) return;

    var host = document.getElementById('toast-host');
    if (!host) return;

    var bar = document.createElement('div');
    bar.className = 'pointer-events-auto toast-enter flex items-start gap-3 rounded-2xl border px-4 py-3 shadow-2xl ' +
        (variant === 'success'
            ? 'border-emerald-400/40 bg-emerald-950/95 text-emerald-100'
            : 'border-rose-400/40 bg-rose-950/95 text-rose-100');
    bar.setAttribute('role', 'status');

    var msg = document.createElement('p');
    msg.className = 'min-w-0 flex-1 text-sm font-medium leading-snug';
    msg.textContent = text;
    bar.appendChild(msg);

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'shrink-0 rounded-lg border border-white/20 px-2 py-1 text-xs font-semibold text-white/90 hover:bg-white/10';
    close.textContent = '關閉';
    var t = setTimeout(function () {
        if (bar.parentNode) bar.remove();
    }, 4500);
    close.addEventListener('click', function () {
        clearTimeout(t);
        bar.remove();
    });
    bar.appendChild(close);

    host.appendChild(bar);
})();
</script>
