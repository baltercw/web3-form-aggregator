<?php
session_start();
require_once './db.php';
require_once __DIR__ . '/includes/task_helpers.php';

$tasks = [];
$taskQuery = $conn->query(
    "SELECT t.id, t.title, t.summary, t.description, t.cover_image_url, t.reward_xp, t.category, t.created_at, t.task_status, t.starts_at, t.ends_at, t.max_completions, t.form_schema, "
    . "(SELECT COUNT(*) FROM submissions s WHERE s.task_id = t.id AND s.status = 'approved') AS approved_count "
    . 'FROM tasks t ORDER BY t.created_at DESC'
);
if ($taskQuery) {
    while ($row = $taskQuery->fetch_assoc()) {
        $tasks[] = $row;
    }
}

$isLoggedIn = isset($_SESSION['user_id'], $_SESSION['role']);
$userRole = $isLoggedIn ? (string) ($_SESSION['role'] ?? '') : '';

$now = task_now_taipei();
$filterCategory = trim((string) ($_GET['category'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$sortKey = trim((string) ($_GET['sort'] ?? 'latest'));

$validStatus = ['all', 'open', 'not_started', 'ended'];
if (!in_array($statusFilter, $validStatus, true)) {
    $statusFilter = 'all';
}
$validSort = ['latest', 'xp', 'starts_soon', 'ends_soon'];
if (!in_array($sortKey, $validSort, true)) {
    $sortKey = 'latest';
}

$allCategories = [];
foreach ($tasks as $t) {
    $cat = trim((string) ($t['category'] ?? ''));
    if ($cat !== '') {
        $allCategories[$cat] = true;
    }
}
$categoriesList = array_keys($allCategories);
sort($categoriesList, SORT_NATURAL | SORT_FLAG_CASE);

$filteredTasks = [];
foreach ($tasks as $task) {
    $ac = (int) ($task['approved_count'] ?? 0);
    $gate = task_submission_gate($task, $ac, $now);

    if ($filterCategory !== '' && ($task['category'] ?? '') !== $filterCategory) {
        continue;
    }
    if ($statusFilter === 'open' && !$gate['can_submit']) {
        continue;
    }
    if ($statusFilter === 'not_started' && !in_array('not_started', $gate['block_codes'], true)) {
        continue;
    }
    if ($statusFilter === 'ended' && !in_array('ended', $gate['block_codes'], true) && !in_array('deadline', $gate['block_codes'], true)) {
        continue;
    }

    $filteredTasks[] = $task;
}

if ($sortKey !== 'latest') {
    usort($filteredTasks, function (array $a, array $b) use ($sortKey): int {
        if ($sortKey === 'xp') {
            $dx = (int) ($b['reward_xp'] ?? 0) - (int) ($a['reward_xp'] ?? 0);
            if ($dx !== 0) return $dx;
        }
        if ($sortKey === 'starts_soon') {
            $da = task_parse_db_datetime((string) ($a['starts_at'] ?? ''));
            $db = task_parse_db_datetime((string) ($b['starts_at'] ?? ''));
            $ta = $da ? $da->getTimestamp() : 0;
            $tb = $db ? $db->getTimestamp() : 0;
            return $ta <=> $tb;
        }
        if ($sortKey === 'ends_soon') {
            $da = task_parse_db_datetime((string) ($a['ends_at'] ?? ''));
            $db = task_parse_db_datetime((string) ($b['ends_at'] ?? ''));
            $ta = $da ? $da->getTimestamp() : 0;
            $tb = $db ? $db->getTimestamp() : 0;
            return $ta <=> $tb;
        }
        return 0;
    });
}
$tasks = $filteredTasks;

function formatTaskDate(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }
    return date('Y/m/d', $timestamp);
}

function taskPublicTeaser(array $task): string
{
    $s = trim((string) ($task['summary'] ?? ''));
    if ($s !== '') {
        return $s;
    }
    $d = (string) ($task['description'] ?? '');
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($d, 0, 160, '…', 'UTF-8');
    }
    return strlen($d) > 160 ? substr($d, 0, 160) . '…' : $d;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web3 Task Aggregator | Group 09</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Inter", sans-serif; }
        .fade-in-up {
            animation: fadeInUp 560ms ease-out both;
        }
        .fade-in-up-delay {
            animation: fadeInUp 720ms ease-out both;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    <?php require __DIR__ . '/includes/head_common.php'; ?>
</head>
<body class="min-h-screen bg-[#0b0b0b] text-zinc-100 antialiased selection:bg-amber-300/25 selection:text-white">
    <?php $w3faBgVariant = 'hero'; require __DIR__ . '/includes/background_decor.php'; ?>

    <?php
    $shellBreadcrumbs = [
        ['label' => '首頁', 'href' => './index.php'],
        ['label' => '任務展示', 'href' => null],
    ];
    $shellPage = $isLoggedIn ? 'auth_index' : 'guest_index';
    $shellUser = $isLoggedIn ? ['name' => (string) ($_SESSION['username'] ?? ''), 'role' => $userRole] : null;
    require __DIR__ . '/includes/site_header.php';
    ?>

    <main class="mx-auto w-full max-w-6xl px-4">
        <section class="grid gap-10 pb-14 pt-16 lg:grid-cols-[1.35fr_0.65fr] lg:items-end">
            <div class="fade-in-up">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">Group 09 · 精選任務</p>
                <h1 class="mt-4 max-w-4xl text-4xl font-semibold leading-tight tracking-tight text-white md:text-6xl">
                    Web3 任務整合平台，
                    <span class="text-zinc-300">讓任務發布、瀏覽與參與更直接。</span>
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-relaxed text-zinc-300 md:text-lg">
                    聚合任務、清楚分類、明確回饋。首頁以效率為優先，幫你快速找到下一個可執行任務。
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <?php if (!$isLoggedIn): ?>
                        <a href="./register.php" class="rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-200">免費註冊</a>
                        <a href="./login.php" class="rounded-full border border-white/20 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50">登入帳號</a>
                    <?php elseif ($userRole === 'issuer'): ?>
                        <a href="./issuer_portal.php" class="rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-200">項目方中心</a>
                    <?php elseif ($userRole === 'admin'): ?>
                        <a href="./dashboard.php" class="rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-200">管理後台</a>
                    <?php else: ?>
                        <a href="./dashboard.php" class="rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-200">前往任務中心</a>
                    <?php endif; ?>
                </div>
            </div>
            <aside class="fade-in-up-delay rounded-3xl border border-amber-200/20 bg-white/[0.04] p-6 shadow-[0_18px_50px_-35px_rgba(0,0,0,0.9)]">
                <p class="text-xs uppercase tracking-[0.2em] text-amber-300">今日一覽</p>
                <div class="mt-4 space-y-4">
                    <div class="flex items-baseline justify-between border-b border-white/10 pb-3">
                        <span class="text-sm text-zinc-300">總任務</span>
                        <span class="text-2xl font-semibold text-white"><?php echo count($tasks); ?></span>
                    </div>
                    <div class="flex items-baseline justify-between border-b border-white/10 pb-3">
                        <span class="text-sm text-zinc-300">狀態</span>
                        <span class="text-sm font-medium text-zinc-100"><?php echo empty($tasks) ? '等待上架' : '可參與'; ?></span>
                    </div>
                    <div class="flex items-baseline justify-between">
                        <span class="text-sm text-zinc-300">資料來源</span>
                        <span class="text-sm font-medium text-zinc-100">站內列表</span>
                    </div>
                </div>
            </aside>
        </section>

        <section id="task-board" class="scroll-mt-28 pb-16">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4 border-b border-white/12 pb-5">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-amber-300">任務看板</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">任務展示區</h2>
                </div>
                <span class="rounded-full border border-amber-300/25 bg-amber-300/10 px-4 py-1.5 text-xs tracking-wide text-zinc-100">
                    共 <?php echo count($tasks); ?> 則任務
                </span>
            </div>

            <div class="mb-8">
                <form method="get" action="./index.php" class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-500">分類</label>
                            <select name="category" class="rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-2 text-sm text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50">
                                <option value="" <?php echo $filterCategory === '' ? 'selected' : ''; ?>>全部分類</option>
                                <?php foreach ($categoriesList as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filterCategory === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs text-zinc-500">狀態</label>
                            <select name="status" class="rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-2 text-sm text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>全部</option>
                                <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>可參與</option>
                                <option value="not_started" <?php echo $statusFilter === 'not_started' ? 'selected' : ''; ?>>尚未開始</option>
                                <option value="ended" <?php echo $statusFilter === 'ended' ? 'selected' : ''; ?>>已結束</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs text-zinc-500">排序</label>
                            <select name="sort" class="rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-2 text-sm text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50">
                                <option value="latest" <?php echo $sortKey === 'latest' ? 'selected' : ''; ?>>最新發布</option>
                                <option value="xp" <?php echo $sortKey === 'xp' ? 'selected' : ''; ?>>XP 最高</option>
                                <option value="starts_soon" <?php echo $sortKey === 'starts_soon' ? 'selected' : ''; ?>>開始時間（近）</option>
                                <option value="ends_soon" <?php echo $sortKey === 'ends_soon' ? 'selected' : ''; ?>>截止時間（近）</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="rounded-full bg-amber-300 px-6 py-2.5 text-sm font-semibold text-black transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-200">套用</button>
                        <a href="./index.php" class="rounded-full border border-white/20 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40">清除</a>
                    </div>
                </form>
            </div>
            <?php if (empty($tasks)): ?>
                <div class="rounded-3xl border border-dashed border-amber-300/30 bg-white/[0.03] p-10 text-center">
                    <p class="text-sm text-zinc-300">目前尚無任務，請由管理員或項目方發布任務。</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $ac = (int) ($task['approved_count'] ?? 0);
                        $gate = task_submission_gate($task, $ac);
                        $maxC = $task['max_completions'] ?? null;
                        if ($maxC !== null && $maxC !== '' && (int) $maxC > 0) {
                            $quotaPublic = '名額（已核准）' . $ac . ' / ' . (int) $maxC . ' · 剩 ' . max(0, (int) $maxC - $ac);
                        } else {
                            $quotaPublic = '名額不限（已核准 ' . $ac . '）';
                        }
                        $coverUrl = trim((string) ($task['cover_image_url'] ?? ''));
                        $showCover = $coverUrl !== '' && preg_match('/^https:\/\//i', $coverUrl);
                        $web3 = task_web3_flags_from_task($task);
                        $walletTag = $web3['wallet_input'] ? '錢包連接：不需要（可能需填地址）' : '錢包連接：不需要';
                        $onchainTag = 'On-chain：' . ($web3['onchain'] ? '需要' : '不需要');
                        $kycTag = 'KYC：' . ($web3['kyc'] ? '需要' : '不需要');
                        ?>
                        <article class="group flex h-full flex-col overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] shadow-[0_15px_40px_-30px_rgba(0,0,0,0.9)] transition hover:-translate-y-0.5 hover:border-amber-300/45 hover:bg-white/[0.065]">
                            <?php if ($showCover): ?>
                                <div class="relative aspect-[21/9] w-full shrink-0 overflow-hidden bg-zinc-900/80">
                                    <img src="<?php echo htmlspecialchars($coverUrl); ?>" alt="" class="h-full w-full object-cover opacity-95 transition group-hover:opacity-100" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                                </div>
                            <?php endif; ?>
                            <div class="flex flex-1 flex-col p-6">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <p class="rounded-full border border-amber-300/35 bg-amber-300/10 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.12em] text-amber-200">
                                        <?php echo htmlspecialchars($task['category']); ?>
                                    </p>
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        <?php if (($task['task_status'] ?? '') === 'ended'): ?>
                                            <span class="rounded-full border border-zinc-500/50 bg-zinc-900/90 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-zinc-200">已結束</span>
                                        <?php endif; ?>
                                        <?php if (!$gate['can_submit']): ?>
                                            <span class="rounded-full border-2 border-rose-500/70 bg-rose-950/80 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-rose-100">不可提交</span>
                                        <?php else: ?>
                                            <span class="rounded-full border border-emerald-400/45 bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-100">開放提交</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <h3 class="mt-4 text-xl font-semibold leading-snug tracking-tight text-white">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </h3>

                                <p class="mt-2 text-sm leading-relaxed text-zinc-500">
                                    台灣時間 <?php echo htmlspecialchars(task_format_taipei_display($task['starts_at'] ?? '')); ?> ～ <?php echo htmlspecialchars(task_format_taipei_display($task['ends_at'] ?? '')); ?>
                                </p>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-xs text-zinc-300">
                                        <?php echo htmlspecialchars($quotaPublic); ?>
                                    </span>
                                    <span class="rounded-full border border-amber-300/25 bg-amber-300/10 px-3 py-1 text-xs font-semibold text-amber-200">
                                        +<?php echo (int) $task['reward_xp']; ?> XP
                                    </span>
                                </div>

                                <div class="mt-4 flex-1 text-sm leading-relaxed text-zinc-300">
                                    <?php if ($isLoggedIn): ?>
                                        <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                                    <?php else: ?>
                                        <?php echo nl2br(htmlspecialchars(taskPublicTeaser($task))); ?>
                                        <p class="mt-2 text-xs text-amber-200/80">登入後可查看完整說明並參與任務。</p>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$gate['can_submit'] && !empty($gate['block_labels'])): ?>
                                    <div class="mt-4 rounded-xl border-2 border-rose-500/55 bg-rose-950/55 px-3 py-2 text-xs font-semibold leading-snug text-rose-50">
                                        <?php echo htmlspecialchars(implode('；', $gate['block_labels'])); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-[11px] text-zinc-200">
                                        <?php echo htmlspecialchars($walletTag); ?>
                                    </span>
                                    <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-[11px] text-zinc-200">
                                        <?php echo htmlspecialchars($onchainTag); ?>
                                    </span>
                                    <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-[11px] text-zinc-200">
                                        <?php echo htmlspecialchars($kycTag); ?>
                                    </span>
                                </div>

                                <p class="mt-2 text-[11px] text-zinc-500">此任務不會要求你簽署交易、不會要求私鑰。</p>

                            <?php if ($isLoggedIn && $userRole === 'member'): ?>
                                <a href="./dashboard.php#task-<?php echo (int) $task['id']; ?>" class="mt-3 block w-full rounded-full border border-amber-300/40 bg-amber-300/10 py-2.5 text-center text-sm font-semibold text-amber-100 transition hover:bg-amber-300/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/50">前往任務中心填寫</a>
                            <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
