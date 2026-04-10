<?php
/**
 * 預期變數：
 * $shellPage — guest_index | auth_index | guest_login | guest_register | auth_dashboard | auth_issuer
 * $shellBreadcrumbs — [['label' => string, 'href' => string|null], ...] 最後一項 href 為 null 表示目前頁
 * $shellUser — 選填 ['name' => string, 'role' => string]（auth_* 時顯示）
 */
$shellPage = $shellPage ?? 'guest_index';
$shellBreadcrumbs = $shellBreadcrumbs ?? [['label' => '首頁', 'href' => null]];
$shellUser = $shellUser ?? null;
$shellIssuer = is_array($shellUser) && (($shellUser['role'] ?? '') === 'issuer');
?>
<header class="sticky top-0 z-30 border-b border-white/10 bg-[#0b0b0b]/95 backdrop-blur">
    <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3 md:py-4">
        <a href="./index.php" class="flex min-w-0 items-center gap-3">
            <span class="h-8 w-8 shrink-0 rounded-full border border-amber-300/80"></span>
            <span class="truncate text-sm font-semibold uppercase tracking-[0.18em] text-zinc-100 md:tracking-[0.22em]">Web3 Task Aggregator</span>
        </a>

        <button type="button" id="nav-toggle" class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 p-2.5 text-zinc-200 md:hidden" aria-expanded="false" aria-controls="mobile-nav-panel">
            <span class="sr-only">開啟選單</span>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <div class="hidden items-center gap-2 md:flex md:gap-3">
            <?php if ($shellPage === 'guest_index'): ?>
                <a href="./register.php" class="rounded-full border border-white/20 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40">註冊</a>
                <a href="./login.php" class="rounded-full border border-amber-300/35 bg-amber-200/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-200/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/60">登入</a>
            <?php elseif ($shellPage === 'auth_index'): ?>
                <?php if (is_array($shellUser)): ?>
                    <span class="hidden text-sm text-zinc-400 lg:inline">
                        <span class="text-zinc-200"><?php echo htmlspecialchars($shellUser['name'] ?? ''); ?></span>
                        <span class="mx-1.5 text-zinc-600">·</span>
                        <span class="uppercase tracking-wider text-amber-200/90"><?php echo htmlspecialchars($shellUser['role'] ?? ''); ?></span>
                    </span>
                <?php endif; ?>
                <?php if ($shellIssuer): ?>
                    <a href="./issuer_portal.php" class="rounded-full border border-amber-300/35 bg-amber-200/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-200/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/60">項目方中心</a>
                <?php else: ?>
                    <a href="./dashboard.php" class="rounded-full border border-amber-300/35 bg-amber-200/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-200/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/60">進入後台</a>
                <?php endif; ?>
                <a href="./login.php?action=logout" class="rounded-full border border-transparent px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40">登出</a>
            <?php elseif ($shellPage === 'guest_login'): ?>
                <a href="./register.php" class="rounded-full border border-white/20 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40">註冊</a>
                <a href="./index.php" class="rounded-full border border-transparent px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40">返回首頁</a>
            <?php elseif ($shellPage === 'guest_register'): ?>
                <a href="./login.php" class="rounded-full border border-amber-300/35 bg-amber-200/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-200/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/60">登入</a>
                <a href="./index.php" class="rounded-full border border-transparent px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40">返回首頁</a>
            <?php elseif ($shellPage === 'auth_dashboard' || $shellPage === 'auth_issuer'): ?>
                <?php if (is_array($shellUser)): ?>
                    <span class="hidden text-sm text-zinc-400 lg:inline">
                        <span class="text-zinc-200"><?php echo htmlspecialchars($shellUser['name'] ?? ''); ?></span>
                        <span class="mx-1.5 text-zinc-600">·</span>
                        <span class="uppercase tracking-wider text-amber-200/90"><?php echo htmlspecialchars($shellUser['role'] ?? ''); ?></span>
                    </span>
                <?php endif; ?>
                <a href="./index.php" class="rounded-full border border-amber-300/35 bg-amber-200/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-200/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/60">首頁</a>
                <a href="./login.php?action=logout" class="rounded-full border border-transparent px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40">登出</a>
            <?php endif; ?>
        </div>
    </div>

    <div id="mobile-nav-panel" class="hidden border-t border-white/10 bg-[#0b0b0b]/98 md:hidden">
        <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-3">
            <?php if ($shellPage === 'auth_index' || $shellPage === 'auth_dashboard' || $shellPage === 'auth_issuer'): ?>
                <?php if (is_array($shellUser)): ?>
                    <p class="border-b border-white/10 px-1 py-2 text-sm text-zinc-400">
                        <span class="text-zinc-200"><?php echo htmlspecialchars($shellUser['name'] ?? ''); ?></span>
                        <span class="text-zinc-600"> · </span>
                        <span class="uppercase text-amber-200/90"><?php echo htmlspecialchars($shellUser['role'] ?? ''); ?></span>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($shellPage === 'guest_index'): ?>
                <a href="./register.php" class="rounded-xl px-3 py-3 text-sm font-medium text-white hover:bg-white/10">註冊</a>
                <a href="./login.php" class="rounded-xl px-3 py-3 text-sm font-medium text-amber-200 hover:bg-white/10">登入</a>
            <?php elseif ($shellPage === 'guest_login'): ?>
                <a href="./register.php" class="rounded-xl px-3 py-3 text-sm font-medium text-white hover:bg-white/10">註冊</a>
                <a href="./index.php" class="rounded-xl px-3 py-3 text-sm font-medium text-zinc-300 hover:bg-white/10">返回首頁</a>
            <?php elseif ($shellPage === 'guest_register'): ?>
                <a href="./login.php" class="rounded-xl px-3 py-3 text-sm font-medium text-amber-200 hover:bg-white/10">登入</a>
                <a href="./index.php" class="rounded-xl px-3 py-3 text-sm font-medium text-zinc-300 hover:bg-white/10">返回首頁</a>
            <?php elseif ($shellPage === 'auth_index'): ?>
                <?php if ($shellIssuer): ?>
                    <a href="./issuer_portal.php" class="rounded-xl px-3 py-3 text-sm font-medium text-amber-200 hover:bg-white/10">項目方中心</a>
                <?php else: ?>
                    <a href="./dashboard.php" class="rounded-xl px-3 py-3 text-sm font-medium text-amber-200 hover:bg-white/10">進入後台</a>
                <?php endif; ?>
                <a href="./login.php?action=logout" class="rounded-xl px-3 py-3 text-sm font-medium text-zinc-300 hover:bg-white/10">登出</a>
            <?php elseif ($shellPage === 'auth_dashboard' || $shellPage === 'auth_issuer'): ?>
                <a href="./index.php" class="rounded-xl px-3 py-3 text-sm font-medium text-amber-200 hover:bg-white/10">首頁</a>
                <a href="./login.php?action=logout" class="rounded-xl px-3 py-3 text-sm font-medium text-zinc-300 hover:bg-white/10">登出</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="border-t border-white/5 bg-black/20">
        <nav class="mx-auto max-w-6xl px-4 py-2 text-xs text-zinc-500 md:text-sm" aria-label="麵包屑">
            <ol class="flex flex-wrap items-center gap-x-1 gap-y-1">
                <?php foreach ($shellBreadcrumbs as $i => $bc): ?>
                    <?php if ($i > 0): ?>
                        <li class="text-zinc-600" aria-hidden="true">/</li>
                    <?php endif; ?>
                    <li>
                        <?php if (!empty($bc['href'])): ?>
                            <a href="<?php echo htmlspecialchars($bc['href']); ?>" class="text-zinc-400 transition hover:text-amber-200/90"><?php echo htmlspecialchars($bc['label']); ?></a>
                        <?php else: ?>
                            <span class="font-medium text-zinc-300"><?php echo htmlspecialchars($bc['label']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
</header>
