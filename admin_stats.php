<?php
session_start();
require_once './db.php';
require_once __DIR__ . '/includes/task_helpers.php';

if (!isset($_SESSION['user_id'], $_SESSION['role']) || (string) $_SESSION['role'] !== 'admin') {
    header('Location: ./login.php');
    exit;
}

$username = (string) ($_SESSION['username'] ?? 'Admin');
$tz = task_taipei_tz();
$now = task_now_taipei();

$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$rangeFilter = trim((string) ($_GET['range'] ?? '30d'));
$sortFilter = trim((string) ($_GET['sort'] ?? 'submitted_desc'));
$startDate = trim((string) ($_GET['start_date'] ?? ''));
$endDate = trim((string) ($_GET['end_date'] ?? ''));

$validRanges = ['all', '7d', '30d', '90d', 'custom'];
if (!in_array($rangeFilter, $validRanges, true)) {
    $rangeFilter = '30d';
}
$validSorts = ['submitted_desc', 'approved_desc', 'pass_desc', 'quota_desc', 'latest'];
if (!in_array($sortFilter, $validSorts, true)) {
    $sortFilter = 'submitted_desc';
}

/**
 * @param array<int, mixed> $params
 */
function bindDynamicParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    $refs = [];
    $refs[] = &$types;
    foreach ($params as $i => &$value) {
        $refs[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

/**
 * @return array{0:?DateTimeImmutable,1:?DateTimeImmutable}
 */
function statsDateRange(
    string $rangeFilter,
    string $startDate,
    string $endDate,
    DateTimeImmutable $now,
    DateTimeZone $tz
): array {
    if ($rangeFilter === 'all') {
        return [null, null];
    }

    if ($rangeFilter === 'custom') {
        if ($startDate === '' || $endDate === '') {
            return [null, null];
        }
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startDate . ' 00:00:00', $tz);
        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endDate . ' 23:59:59', $tz);
        if ($start === false || $end === false || $end < $start) {
            return [null, null];
        }
        return [$start, $end];
    }

    $days = 30;
    if ($rangeFilter === '7d') {
        $days = 7;
    } elseif ($rangeFilter === '90d') {
        $days = 90;
    }
    $start = $now->modify('-' . ($days - 1) . ' days')->setTime(0, 0, 0);
    $end = $now->setTime(23, 59, 59);

    return [$start, $end];
}

[$fromDt, $toDt] = statsDateRange($rangeFilter, $startDate, $endDate, $now, $tz);

$categories = [];
$catQuery = $conn->query('SELECT DISTINCT category FROM tasks WHERE category IS NOT NULL AND category <> "" ORDER BY category ASC');
if ($catQuery) {
    while ($row = $catQuery->fetch_assoc()) {
        $categories[] = (string) $row['category'];
    }
}

$joinConditions = ['s.task_id = t.id'];
$joinTypes = '';
$joinParams = [];

if ($fromDt !== null) {
    $joinConditions[] = 's.submitted_at >= ?';
    $joinTypes .= 's';
    $joinParams[] = $fromDt->format('Y-m-d H:i:s');
}
if ($toDt !== null) {
    $joinConditions[] = 's.submitted_at <= ?';
    $joinTypes .= 's';
    $joinParams[] = $toDt->format('Y-m-d H:i:s');
}

$where = [];
$whereTypes = '';
$whereParams = [];
if ($categoryFilter !== '') {
    $where[] = 't.category = ?';
    $whereTypes .= 's';
    $whereParams[] = $categoryFilter;
}

$sql = 'SELECT t.id, t.title, t.category, t.reward_xp, t.max_completions, t.created_at, t.task_status, '
    . 'u.username AS creator_username, '
    . 'COUNT(s.id) AS submitted_count, '
    . "SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) AS approved_count, "
    . "SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) AS pending_count "
    . 'FROM tasks t '
    . 'LEFT JOIN users u ON u.id = t.created_by '
    . 'LEFT JOIN submissions s ON ' . implode(' AND ', $joinConditions) . ' ';

if (!empty($where)) {
    $sql .= 'WHERE ' . implode(' AND ', $where) . ' ';
}

$sql .= 'GROUP BY t.id, t.title, t.category, t.reward_xp, t.max_completions, t.created_at, t.task_status, u.username '
    . 'ORDER BY t.created_at DESC';

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $allTypes = $joinTypes . $whereTypes;
    $allParams = array_merge($joinParams, $whereParams);
    if ($allTypes !== '') {
        bindDynamicParams($stmt, $allTypes, $allParams);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
}

$totalTasks = count($rows);
$totalSubmitted = 0;
$totalApproved = 0;
$totalPending = 0;

foreach ($rows as &$r) {
    $submitted = (int) ($r['submitted_count'] ?? 0);
    $approved = (int) ($r['approved_count'] ?? 0);
    $pending = (int) ($r['pending_count'] ?? 0);
    $maxRaw = $r['max_completions'] ?? null;
    $maxQuota = ($maxRaw !== null && $maxRaw !== '' && (int) $maxRaw > 0) ? (int) $maxRaw : null;
    $passRate = $submitted > 0 ? ($approved / $submitted) * 100.0 : 0.0;
    $quotaRate = $maxQuota !== null ? min(100.0, ($approved / $maxQuota) * 100.0) : null;

    $r['_submitted'] = $submitted;
    $r['_approved'] = $approved;
    $r['_pending'] = $pending;
    $r['_pass_rate'] = $passRate;
    $r['_max_quota'] = $maxQuota;
    $r['_quota_rate'] = $quotaRate;

    $totalSubmitted += $submitted;
    $totalApproved += $approved;
    $totalPending += $pending;
}
unset($r);

if ($sortFilter !== 'latest') {
    usort($rows, function (array $a, array $b) use ($sortFilter): int {
        if ($sortFilter === 'submitted_desc') {
            return (int) $b['_submitted'] <=> (int) $a['_submitted'];
        }
        if ($sortFilter === 'approved_desc') {
            return (int) $b['_approved'] <=> (int) $a['_approved'];
        }
        if ($sortFilter === 'pass_desc') {
            if ((float) $b['_pass_rate'] === (float) $a['_pass_rate']) {
                return (int) $b['_submitted'] <=> (int) $a['_submitted'];
            }
            return (float) $b['_pass_rate'] <=> (float) $a['_pass_rate'];
        }
        if ($sortFilter === 'quota_desc') {
            $qa = $a['_quota_rate'] === null ? -1.0 : (float) $a['_quota_rate'];
            $qb = $b['_quota_rate'] === null ? -1.0 : (float) $b['_quota_rate'];
            if ($qb === $qa) {
                return (int) $b['_approved'] <=> (int) $a['_approved'];
            }
            return $qb <=> $qa;
        }
        return 0;
    });
}

$overallPassRate = $totalSubmitted > 0 ? ($totalApproved / $totalSubmitted) * 100.0 : 0.0;

$shellBreadcrumbs = [
    ['label' => '首頁', 'href' => './index.php'],
    ['label' => '後台', 'href' => './dashboard.php'],
    ['label' => '資料統計', 'href' => null],
];
$shellPage = 'auth_dashboard';
$shellUser = ['name' => $username, 'role' => 'admin'];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>資料統計 | Web3 Task Aggregator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Inter", sans-serif; }
        .fade-in-up { animation: fadeInUp 560ms ease-out both; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <?php require __DIR__ . '/includes/head_common.php'; ?>
</head>
<body class="min-h-screen bg-[#0b0b0b] text-zinc-100 antialiased selection:bg-amber-300/25 selection:text-white">
    <?php $w3faBgVariant = 'subtle'; require __DIR__ . '/includes/background_decor.php'; ?>

    <?php require __DIR__ . '/includes/site_header.php'; ?>

    <main class="fade-in-up mx-auto w-full max-w-6xl space-y-8 px-4 py-10">
        <section class="rounded-3xl border border-white/10 bg-white/[0.04] p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">Admin / Data Statistics</p>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white">資料統計總覽</h1>
            <p class="mt-2 text-sm text-zinc-300">依提交時間統計每個任務的提交量、核准數、通過率與名額消耗率（Progress Bar）。</p>

            <form method="get" action="./admin_stats.php" class="mt-6 grid grid-cols-1 gap-3 rounded-2xl border border-white/10 bg-black/20 p-4 md:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="range" class="mb-1 block text-xs text-zinc-400">時間範圍</label>
                    <select id="range" name="range" class="w-full rounded-xl border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                        <option value="7d" <?php echo $rangeFilter === '7d' ? 'selected' : ''; ?>>近 7 天</option>
                        <option value="30d" <?php echo $rangeFilter === '30d' ? 'selected' : ''; ?>>近 30 天</option>
                        <option value="90d" <?php echo $rangeFilter === '90d' ? 'selected' : ''; ?>>近 90 天</option>
                        <option value="custom" <?php echo $rangeFilter === 'custom' ? 'selected' : ''; ?>>自訂區間</option>
                        <option value="all" <?php echo $rangeFilter === 'all' ? 'selected' : ''; ?>>全部時間</option>
                    </select>
                </div>
                <div>
                    <label for="start_date" class="mb-1 block text-xs text-zinc-400">起始日（自訂）</label>
                    <input id="start_date" name="start_date" type="date" value="<?php echo htmlspecialchars($startDate); ?>" class="w-full rounded-xl border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label for="end_date" class="mb-1 block text-xs text-zinc-400">結束日（自訂）</label>
                    <input id="end_date" name="end_date" type="date" value="<?php echo htmlspecialchars($endDate); ?>" class="w-full rounded-xl border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label for="category" class="mb-1 block text-xs text-zinc-400">分類</label>
                    <select id="category" name="category" class="w-full rounded-xl border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                        <option value="">全部分類</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="sort" class="mb-1 block text-xs text-zinc-400">排序</label>
                    <select id="sort" name="sort" class="w-full rounded-xl border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                        <option value="submitted_desc" <?php echo $sortFilter === 'submitted_desc' ? 'selected' : ''; ?>>提交數最多</option>
                        <option value="approved_desc" <?php echo $sortFilter === 'approved_desc' ? 'selected' : ''; ?>>核准數最多</option>
                        <option value="pass_desc" <?php echo $sortFilter === 'pass_desc' ? 'selected' : ''; ?>>通過率最高</option>
                        <option value="quota_desc" <?php echo $sortFilter === 'quota_desc' ? 'selected' : ''; ?>>名額消耗率最高</option>
                        <option value="latest" <?php echo $sortFilter === 'latest' ? 'selected' : ''; ?>>最新建立</option>
                    </select>
                </div>

                <div class="md:col-span-2 lg:col-span-5 flex items-center gap-2 pt-1">
                    <button type="submit" class="rounded-full bg-amber-300 px-5 py-2 text-sm font-semibold text-black transition hover:bg-amber-200">套用篩選</button>
                    <a href="./admin_stats.php" class="rounded-full border border-white/20 px-5 py-2 text-sm font-semibold text-white transition hover:bg-white/10">清除</a>
                </div>
            </form>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                <p class="text-xs uppercase tracking-[0.14em] text-zinc-500">總任務數</p>
                <p class="mt-2 text-3xl font-semibold text-white"><?php echo $totalTasks; ?></p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                <p class="text-xs uppercase tracking-[0.14em] text-zinc-500">總提交數</p>
                <p class="mt-2 text-3xl font-semibold text-white"><?php echo $totalSubmitted; ?></p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                <p class="text-xs uppercase tracking-[0.14em] text-zinc-500">總核准數</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-300"><?php echo $totalApproved; ?></p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                <p class="text-xs uppercase tracking-[0.14em] text-zinc-500">全站通過率</p>
                <p class="mt-2 text-3xl font-semibold text-amber-200"><?php echo number_format($overallPassRate, 1); ?>%</p>
            </article>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/[0.04] p-6 md:p-8">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3 border-b border-white/10 pb-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">任務統計明細</h2>
                    <p class="mt-1 text-xs text-zinc-500">完成數定義：`pending + approved`，通過率 = `approved / 完成數`</p>
                </div>
                <span class="rounded-full border border-amber-300/25 bg-amber-300/10 px-3 py-1 text-xs text-zinc-100"><?php echo count($rows); ?> Tasks</span>
            </div>

            <?php if (empty($rows)): ?>
                <div class="rounded-2xl border border-dashed border-white/20 bg-black/20 p-10 text-center text-sm text-zinc-400">
                    目前沒有符合篩選條件的統計資料。
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-2xl border border-white/10">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-white/10 bg-white/[0.04] text-xs uppercase tracking-wider text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">任務</th>
                                <th class="px-4 py-3">分類 / XP</th>
                                <th class="px-4 py-3">完成數</th>
                                <th class="px-4 py-3">核准數</th>
                                <th class="px-4 py-3">通過率</th>
                                <th class="px-4 py-3">名額消耗率</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $submitted = (int) $row['_submitted'];
                                $approved = (int) $row['_approved'];
                                $pending = (int) $row['_pending'];
                                $passRate = (float) $row['_pass_rate'];
                                $maxQuota = $row['_max_quota'];
                                $quotaRate = $row['_quota_rate'];
                                ?>
                                <tr class="align-top text-zinc-200">
                                    <td class="px-4 py-3 min-w-[15rem]">
                                        <p class="font-medium text-white"><?php echo htmlspecialchars((string) $row['title']); ?></p>
                                        <p class="mt-1 text-xs text-zinc-500">建立者：<?php echo htmlspecialchars((string) ($row['creator_username'] ?? '—')); ?></p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p><?php echo htmlspecialchars((string) $row['category']); ?></p>
                                        <p class="mt-1 text-xs text-amber-200">+<?php echo (int) $row['reward_xp']; ?> XP</p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="font-semibold text-white"><?php echo $submitted; ?></p>
                                        <p class="mt-1 text-xs text-zinc-500">pending <?php echo $pending; ?></p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="font-semibold text-emerald-300"><?php echo $approved; ?></p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="font-semibold text-amber-200"><?php echo number_format($passRate, 1); ?>%</p>
                                    </td>
                                    <td class="px-4 py-3 min-w-[14rem]">
                                        <?php if ($maxQuota === null): ?>
                                            <p class="text-xs text-zinc-400">不限名額（∞）</p>
                                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10">
                                                <div class="h-full w-1/3 bg-amber-300/40"></div>
                                            </div>
                                            <p class="mt-1 text-xs text-zinc-500">已核准 <?php echo $approved; ?></p>
                                        <?php else: ?>
                                            <?php $qRate = (float) $quotaRate; ?>
                                            <p class="text-xs text-zinc-400"><?php echo $approved; ?> / <?php echo (int) $maxQuota; ?></p>
                                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10">
                                                <div class="h-full bg-amber-300 transition-all" style="width: <?php echo number_format($qRate, 2, '.', ''); ?>%;"></div>
                                            </div>
                                            <p class="mt-1 text-xs text-zinc-500"><?php echo number_format($qRate, 1); ?>% 已消耗</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
