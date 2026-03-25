<?php
session_start();
require_once './db.php';

$tasks = [];
$taskQuery = $conn->query('SELECT id, title, description, reward_xp, category, created_at FROM tasks ORDER BY created_at DESC');
if ($taskQuery) {
    while ($row = $taskQuery->fetch_assoc()) {
        $tasks[] = $row;
    }
}

$isLoggedIn = isset($_SESSION['user_id'], $_SESSION['role']);
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
    </style>
</head>
<body class="bg-slate-50 text-slate-800">
    <header class="sticky top-0 z-10 backdrop-blur bg-slate-50/85 border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="./index.php" class="flex items-center gap-3">
                <span class="h-10 w-10 rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600"></span>
                <span class="text-lg font-bold tracking-tight">Web3 Task Aggregator</span>
            </a>
            <?php if ($isLoggedIn): ?>
                <div class="flex items-center gap-3">
                    <a href="./dashboard.php" class="rounded-2xl bg-white border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-100 transition">進入後台</a>
                    <a href="./login.php?action=logout" class="rounded-2xl px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">登出</a>
                </div>
            <?php else: ?>
                <a href="./login.php" class="rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-4 py-2 text-sm font-semibold hover:opacity-95 transition">登入</a>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <section class="max-w-6xl mx-auto px-4 pt-16 pb-12">
            <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-8 md:p-12">
                <p class="inline-flex rounded-full bg-indigo-50 text-indigo-700 px-4 py-2 text-xs font-semibold tracking-wide">WEB3 MODERN LIGHT</p>
                <h1 class="mt-5 text-4xl md:text-5xl font-extrabold tracking-tight">
                    聚合任務，一鍵參與，
                    <span class="bg-gradient-to-r from-indigo-500 to-purple-600 bg-clip-text text-transparent">快速累積 XP</span>
                </h1>
                <p class="mt-5 text-slate-600 text-lg max-w-3xl">
                    Group 09 Web3 任務平台，提供任務發布、參與與完成紀錄，讓團隊管理流程更直覺。
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="./dashboard.php" class="rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-5 py-3 font-semibold hover:opacity-95 transition">立即開始</a>
                    <a href="./login.php" class="rounded-2xl bg-white border border-slate-200 px-5 py-3 font-semibold hover:bg-slate-100 transition">登入帳號</a>
                </div>
            </div>
        </section>

        <section class="max-w-6xl mx-auto px-4 pb-16">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold tracking-tight">任務展示區</h2>
                <span class="text-sm text-slate-500">共 <?php echo count($tasks); ?> 筆任務</span>
            </div>
            <?php if (empty($tasks)): ?>
                <div class="bg-white rounded-3xl shadow-md border border-slate-200 p-8 text-slate-500">
                    目前尚無任務，請由管理員登入後台新增任務。
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($tasks as $task): ?>
                        <article class="bg-white rounded-3xl shadow-md border border-slate-200 p-6">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-600">
                                    <?php echo htmlspecialchars($task['category']); ?>
                                </p>
                                <p class="text-xs text-slate-400">
                                    <?php echo htmlspecialchars($task['created_at']); ?>
                                </p>
                            </div>
                            <h3 class="mt-4 text-lg font-bold"><?php echo htmlspecialchars($task['title']); ?></h3>
                            <p class="mt-2 text-slate-600 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                            <div class="mt-5 inline-flex rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-4 py-2 text-sm font-semibold">
                                +<?php echo (int) $task['reward_xp']; ?> XP
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
