<?php
session_start();
require_once './db.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ./login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Guest';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($role === 'admin' && $action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rewardXp = (int) ($_POST['reward_xp'] ?? 0);
        $category = trim($_POST['category'] ?? '');

        if ($title === '' || $description === '' || $category === '' || $rewardXp < 0) {
            $error = '請填寫完整且正確的任務資訊。';
        } else {
            $stmt = $conn->prepare('INSERT INTO tasks (title, description, reward_xp, category) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssis', $title, $description, $rewardXp, $category);
            if ($stmt->execute()) {
                $message = '新任務已成功發布。';
            } else {
                $error = '任務發布失敗，請稍後再試。';
            }
            $stmt->close();
        }
    }

    if ($role === 'member' && $action === 'complete_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            $stmt = $conn->prepare("INSERT INTO submissions (user_id, task_id, status) VALUES (?, ?, 'completed')");
            $stmt->bind_param('ii', $userId, $taskId);
            if ($stmt->execute()) {
                $message = '任務已完成並記錄成功。';
            } else {
                $error = '此任務可能已完成，或提交失敗。';
            }
            $stmt->close();
        } else {
            $error = '任務資料不正確。';
        }
    }
}

$tasks = [];
$taskResult = $conn->query('SELECT id, title, description, reward_xp, category, created_at FROM tasks ORDER BY created_at DESC');
if ($taskResult) {
    while ($row = $taskResult->fetch_assoc()) {
        $tasks[] = $row;
    }
}

$completedTaskIds = [];
if ($role === 'member') {
    $completedStmt = $conn->prepare("SELECT task_id FROM submissions WHERE user_id = ? AND status = 'completed'");
    $completedStmt->bind_param('i', $userId);
    $completedStmt->execute();
    $completedResult = $completedStmt->get_result();
    while ($row = $completedResult->fetch_assoc()) {
        $completedTaskIds[] = (int) $row['task_id'];
    }
    $completedStmt->close();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>後台 | Web3 Task Aggregator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Inter", sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">
    <header class="border-b border-slate-200 bg-slate-50/90 backdrop-blur">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="./index.php" class="flex items-center gap-3">
                <span class="h-9 w-9 rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600"></span>
                <span class="font-bold">Web3 Task Aggregator</span>
            </a>
            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-500">Hi, <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
                <a href="./login.php?action=logout" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-100 transition">登出</a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-10 space-y-8">
        <?php if ($message !== ''): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-700 text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <section class="bg-white rounded-3xl shadow-lg border border-slate-200 p-8">
                <h1 class="text-2xl font-bold tracking-tight">發布新任務</h1>
                <p class="mt-2 text-slate-500">建立任務後，會員可在後台直接完成並記錄提交。</p>

                <form method="post" action="./dashboard.php" class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="action" value="create_task">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-2" for="title">任務標題</label>
                        <input id="title" name="title" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-400" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-2" for="description">任務描述</label>
                        <textarea id="description" name="description" rows="4" class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-400" required></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2" for="reward_xp">獎勵 XP</label>
                        <input id="reward_xp" name="reward_xp" type="number" min="0" class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-400" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2" for="category">分類</label>
                        <input id="category" name="category" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-400" required>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold px-6 py-3 hover:opacity-95 transition">
                            發布任務
                        </button>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <section>
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight">可參與任務</h1>
                    <p class="mt-2 text-slate-500">點擊按鈕即自動完成並寫入資料庫。</p>
                </div>

                <?php if (empty($tasks)): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-md p-8 text-slate-500">
                        目前尚無任務，請稍後再試。
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php foreach ($tasks as $task): ?>
                            <?php $completed = in_array((int) $task['id'], $completedTaskIds, true); ?>
                            <article class="bg-white rounded-3xl border border-slate-200 shadow-md p-6">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs rounded-full px-3 py-1 bg-slate-100 text-slate-600 font-semibold"><?php echo htmlspecialchars($task['category']); ?></span>
                                    <span class="text-xs text-slate-400"><?php echo htmlspecialchars($task['created_at']); ?></span>
                                </div>
                                <h2 class="mt-4 text-lg font-bold"><?php echo htmlspecialchars($task['title']); ?></h2>
                                <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                <div class="mt-4 flex items-center justify-between">
                                    <span class="inline-flex rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white text-sm font-semibold px-3 py-2">
                                        +<?php echo (int) $task['reward_xp']; ?> XP
                                    </span>
                                    <?php if ($completed): ?>
                                        <span class="text-sm text-emerald-600 font-semibold">已完成</span>
                                    <?php else: ?>
                                        <form method="post" action="./dashboard.php">
                                            <input type="hidden" name="action" value="complete_task">
                                            <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                                            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-100 transition">
                                                自動完成
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
