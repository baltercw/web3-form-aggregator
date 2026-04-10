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

if ($role === 'issuer') {
    header('Location: ./issuer_portal.php');
    exit;
}

$message = '';
$error = '';

function dashboardBuildFormSchemaFromPost(): string
{
    $keys = $_POST['field_key'] ?? [];
    $labels = $_POST['field_label'] ?? [];
    $types = $_POST['field_type'] ?? [];
    if (!is_array($keys)) {
        $keys = [];
    }
    if (!is_array($labels)) {
        $labels = [];
    }
    if (!is_array($types)) {
        $types = [];
    }
    $out = [];
    $n = max(count($keys), count($labels), count($types));
    for ($i = 0; $i < $n; $i++) {
        $key = isset($keys[$i]) ? trim((string) $keys[$i]) : '';
        $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
        $key = preg_replace('/_+/', '_', $key);
        $key = trim($key, '_');
        if ($key === '') {
            continue;
        }
        $label = isset($labels[$i]) ? trim((string) $labels[$i]) : '';
        if ($label === '') {
            $label = $key;
        }
        $type = isset($types[$i]) ? trim((string) $types[$i]) : 'text';
        if ($type !== 'url') {
            $type = 'text';
        }
        $out[] = ['key' => $key, 'label' => $label, 'type' => $type];
    }

    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($role === 'admin' && $action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rewardXp = (int) ($_POST['reward_xp'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $schemaJson = dashboardBuildFormSchemaFromPost();

        if ($title === '' || $summary === '' || $description === '' || $category === '' || $rewardXp < 0) {
            $error = '請填寫完整任務資訊（含公開摘要）。';
        } else {
            $stmt = $conn->prepare('INSERT INTO tasks (title, summary, description, reward_xp, category, form_schema, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssisssi', $title, $summary, $description, $rewardXp, $category, $schemaJson, $userId);
            if ($stmt->execute()) {
                $message = '新任務已成功發布。';
            } else {
                $error = '任務發布失敗，請稍後再試。';
            }
            $stmt->close();
        }
    }

    if ($role === 'admin' && $action === 'update_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rewardXp = (int) ($_POST['reward_xp'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $schemaJson = dashboardBuildFormSchemaFromPost();

        if ($taskId <= 0 || $title === '' || $summary === '' || $description === '' || $category === '' || $rewardXp < 0) {
            $error = '請填寫完整任務資訊。';
        } else {
            $stmt = $conn->prepare('UPDATE tasks SET title = ?, summary = ?, description = ?, reward_xp = ?, category = ?, form_schema = ? WHERE id = ?');
            $stmt->bind_param('ssisssi', $title, $summary, $description, $rewardXp, $category, $schemaJson, $taskId);
            if ($stmt->execute()) {
                $message = '任務已更新。';
            } else {
                $error = '更新失敗，請稍後再試。';
            }
            $stmt->close();
        }
    }

    if ($role === 'admin' && $action === 'delete_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            $stmt = $conn->prepare('DELETE FROM tasks WHERE id = ?');
            $stmt->bind_param('i', $taskId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = '任務已刪除。';
            } else {
                $error = '刪除失敗。';
            }
            $stmt->close();
        }
    }

    if ($role === 'admin' && $action === 'set_user_role') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newRole = trim($_POST['new_role'] ?? '');
        if ($targetId > 0 && in_array($newRole, ['admin', 'member', 'issuer'], true)) {
            $stmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->bind_param('si', $newRole, $targetId);
            if ($stmt->execute()) {
                $message = '使用者角色已更新。';
            } else {
                $error = '角色更新失敗。';
            }
            $stmt->close();
        } else {
            $error = '角色資料不正確。';
        }
    }

    if ($role === 'member' && $action === 'complete_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        if ($taskId <= 0) {
            $error = '任務資料不正確。';
        } else {
            $tstmt = $conn->prepare('SELECT id, form_schema FROM tasks WHERE id = ? LIMIT 1');
            $tstmt->bind_param('i', $taskId);
            $tstmt->execute();
            $tres = $tstmt->get_result();
            $taskRow = $tres ? $tres->fetch_assoc() : null;
            $tstmt->close();

            if (!$taskRow) {
                $error = '找不到任務。';
            } else {
                $schema = [];
                if (!empty($taskRow['form_schema'])) {
                    $dec = json_decode((string) $taskRow['form_schema'], true);
                    if (is_array($dec)) {
                        $schema = $dec;
                    }
                }

                $responses = [];
                $respError = false;
                foreach ($schema as $field) {
                    $k = isset($field['key']) ? (string) $field['key'] : '';
                    if ($k === '') {
                        continue;
                    }
                    $val = trim((string) ($_POST['response'][$k] ?? ''));
                    if ($val === '') {
                        $respError = true;
                        $error = '請填寫所有自訂欄位：' . ($field['label'] ?? $k);
                        break;
                    }
                    $responses[$k] = $val;
                }

                if (!$respError) {
                    $jsonStr = count($responses) > 0 ? json_encode($responses, JSON_UNESCAPED_UNICODE) : null;

                    if ($jsonStr !== null) {
                        $stmt = $conn->prepare("INSERT INTO submissions (user_id, task_id, status, response_json) VALUES (?, ?, 'completed', ?)");
                        $stmt->bind_param('iis', $userId, $taskId, $jsonStr);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO submissions (user_id, task_id, status) VALUES (?, ?, 'completed')");
                        $stmt->bind_param('ii', $userId, $taskId);
                    }

                    if ($stmt->execute()) {
                        $message = '任務已完成並記錄成功。';
                    } else {
                        $error = '此任務可能已完成，或提交失敗。';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$adminEditId = ($role === 'admin' && isset($_GET['edit'])) ? (int) $_GET['edit'] : 0;
$adminEditTask = null;
$adminSchemaFields = [];
if ($adminEditId > 0) {
    $ed = $conn->prepare('SELECT id, title, summary, description, reward_xp, category, form_schema FROM tasks WHERE id = ? LIMIT 1');
    $ed->bind_param('i', $adminEditId);
    $ed->execute();
    $edr = $ed->get_result();
    if ($edr && ($row = $edr->fetch_assoc())) {
        $adminEditTask = $row;
        if (!empty($row['form_schema'])) {
            $d = json_decode((string) $row['form_schema'], true);
            if (is_array($d)) {
                $adminSchemaFields = $d;
            }
        }
    }
    $ed->close();
}

$tasks = [];
$sql = 'SELECT t.id, t.title, t.summary, t.description, t.reward_xp, t.category, t.form_schema, t.created_at, t.created_by, u.username AS creator_username '
    . 'FROM tasks t LEFT JOIN users u ON u.id = t.created_by ORDER BY t.created_at DESC';
$taskResult = $conn->query($sql);
if ($taskResult) {
    while ($row = $taskResult->fetch_assoc()) {
        $tasks[] = $row;
    }
}

$allUsers = [];
if ($role === 'admin') {
    $ur = $conn->query('SELECT id, username, role FROM users ORDER BY id ASC');
    if ($ur) {
        while ($row = $ur->fetch_assoc()) {
            $allUsers[] = $row;
        }
    }
}

$completedTaskIds = [];
if ($role === 'member') {
    $completedStmt = $conn->prepare("SELECT task_id FROM submissions WHERE user_id = ? AND status = 'completed'");
    $completedStmt->bind_param('i', $userId);
    $completedStmt->execute();
    $completedResult = $completedStmt->get_result();
    if ($completedResult) {
        while ($row = $completedResult->fetch_assoc()) {
            $completedTaskIds[] = (int) $row['task_id'];
        }
    }
    $completedStmt->close();
}

function formatTaskDate(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }
    return date('M d, Y', $timestamp);
}

$shellBreadcrumbs = [
    ['label' => '首頁', 'href' => './index.php'],
];
if ($role === 'admin' && $adminEditTask) {
    $shellBreadcrumbs[] = ['label' => '後台', 'href' => './dashboard.php'];
    $shellBreadcrumbs[] = ['label' => '編輯任務', 'href' => null];
} else {
    $shellBreadcrumbs[] = ['label' => '後台', 'href' => null];
}
$shellPage = 'auth_dashboard';
$shellUser = ['name' => $username, 'role' => $role];
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
        .fade-in-up { animation: fadeInUp 560ms ease-out both; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <?php require __DIR__ . '/includes/head_common.php'; ?>
</head>
<body class="min-h-screen bg-[#0b0b0b] text-zinc-100 antialiased selection:bg-amber-300/25 selection:text-white">
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute inset-0 bg-[radial-gradient(1000px_circle_at_14%_-15%,rgba(251,191,36,0.20),transparent_58%),radial-gradient(900px_circle_at_86%_0%,rgba(255,255,255,0.05),transparent_62%)]"></div>
        <div class="absolute inset-0 opacity-25 [background-image:linear-gradient(to_right,rgba(255,255,255,0.055)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.055)_1px,transparent_1px)] [background-size:60px_60px]"></div>
    </div>

    <?php require __DIR__ . '/includes/site_header.php'; ?>

    <main class="fade-in-up mx-auto w-full max-w-6xl space-y-8 px-4 py-10">
        <?php if ($role === 'admin'): ?>
            <section class="rounded-3xl border border-white/10 bg-white/[0.04] p-8 shadow-[0_18px_50px_-35px_rgba(0,0,0,0.9)]">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">Admin / <?php echo $adminEditTask ? 'Edit Task' : 'Create Task'; ?></p>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white"><?php echo $adminEditTask ? '編輯任務' : '發布新任務'; ?></h1>
                <p class="mt-2 text-sm text-zinc-300">請填公開摘要（首頁訪客可見）與完整說明；可自訂會員提交欄位。</p>

                <form method="post" action="./dashboard.php<?php echo $adminEditTask ? '?edit=' . (int) $adminEditTask['id'] : ''; ?>" class="mt-6 space-y-4" id="admin-task-form">
                    <input type="hidden" name="action" value="<?php echo $adminEditTask ? 'update_task' : 'create_task'; ?>">
                    <?php if ($adminEditTask): ?>
                        <input type="hidden" name="task_id" value="<?php echo (int) $adminEditTask['id']; ?>">
                    <?php endif; ?>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-300" for="title">任務標題</label>
                        <input id="title" name="title" type="text" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo $adminEditTask ? htmlspecialchars($adminEditTask['title']) : ''; ?>">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-300" for="summary">公開摘要</label>
                        <textarea id="summary" name="summary" rows="2" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required><?php echo $adminEditTask ? htmlspecialchars($adminEditTask['summary']) : ''; ?></textarea>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-300" for="description">完整說明</label>
                        <textarea id="description" name="description" rows="4" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required><?php echo $adminEditTask ? htmlspecialchars($adminEditTask['description']) : ''; ?></textarea>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-300" for="reward_xp">獎勵 XP</label>
                            <input id="reward_xp" name="reward_xp" type="number" min="0" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo $adminEditTask ? (int) $adminEditTask['reward_xp'] : '0'; ?>">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-300" for="category">分類</label>
                            <input id="category" name="category" type="text" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo $adminEditTask ? htmlspecialchars($adminEditTask['category']) : ''; ?>">
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                        <p class="text-sm font-medium text-white">會員完成任務時要填的欄位</p>
                        <div id="admin-field-rows" class="mt-4 space-y-3">
                            <?php foreach ($adminSchemaFields as $f): ?>
                                <div class="field-row grid grid-cols-1 gap-2 rounded-xl border border-white/10 bg-black/20 p-3 md:grid-cols-12 md:items-end">
                                    <div class="md:col-span-3">
                                        <label class="text-xs text-zinc-400">欄位代碼</label>
                                        <input name="field_key[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" value="<?php echo htmlspecialchars($f['key'] ?? ''); ?>">
                                    </div>
                                    <div class="md:col-span-5">
                                        <label class="text-xs text-zinc-400">顯示名稱</label>
                                        <input name="field_label[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" value="<?php echo htmlspecialchars($f['label'] ?? ''); ?>">
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="text-xs text-zinc-400">類型</label>
                                        <select name="field_type[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                                            <option value="text" <?php echo (($f['type'] ?? '') === 'url') ? '' : 'selected'; ?>>文字</option>
                                            <option value="url" <?php echo (($f['type'] ?? '') === 'url') ? 'selected' : ''; ?>>網址</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-1 flex md:justify-end">
                                        <button type="button" class="remove-row mt-4 rounded-lg border border-white/20 px-2 py-2 text-xs text-zinc-300 hover:bg-white/10">刪</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="admin-add-field" class="mt-3 rounded-full border border-amber-300/35 bg-amber-200/10 px-4 py-2 text-sm font-medium text-white hover:bg-amber-200/20">新增欄位</button>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black transition hover:bg-amber-200"><?php echo $adminEditTask ? '儲存' : '發布任務'; ?></button>
                        <?php if ($adminEditTask): ?>
                            <a href="./dashboard.php" class="rounded-full border border-white/20 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">取消</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-white/10 bg-white/[0.04] p-8">
                <h2 class="text-xl font-semibold text-white">使用者與角色</h2>
                <p class="mt-1 text-sm text-zinc-400">將帳號設為 issuer 後，對方登入會進入項目方中心。</p>
                <div class="mt-4 overflow-x-auto rounded-2xl border border-white/10">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-white/10 bg-white/[0.04] text-xs uppercase tracking-wider text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">帳號</th>
                                <th class="px-4 py-3">角色</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            <?php foreach ($allUsers as $u): ?>
                                <tr class="text-zinc-200">
                                    <td class="px-4 py-3"><?php echo (int) $u['id']; ?></td>
                                    <td class="px-4 py-3 font-medium text-white"><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($u['role']); ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="post" action="./dashboard.php" class="inline-flex flex-wrap items-center gap-2 justify-end">
                                            <input type="hidden" name="action" value="set_user_role">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                            <select name="new_role" class="rounded-lg border border-white/15 bg-white/[0.06] px-2 py-1.5 text-sm text-white">
                                                <option value="member" <?php echo $u['role'] === 'member' ? 'selected' : ''; ?>>member</option>
                                                <option value="issuer" <?php echo $u['role'] === 'issuer' ? 'selected' : ''; ?>>issuer</option>
                                                <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                                            </select>
                                            <button type="submit" class="rounded-full border border-amber-300/35 bg-amber-200/10 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-200/20">更新</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-3xl border border-white/10 bg-white/[0.04] p-8">
                <h2 class="text-xl font-semibold text-white">全部任務</h2>
                <p class="mt-1 text-sm text-zinc-400">可編輯或刪除任一任務。</p>
                <?php if (empty($tasks)): ?>
                    <p class="mt-4 text-sm text-zinc-500">尚無任務。</p>
                <?php else: ?>
                    <div class="mt-4 overflow-x-auto rounded-2xl border border-white/10">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-white/10 bg-white/[0.04] text-xs uppercase tracking-wider text-zinc-400">
                                <tr>
                                    <th class="px-4 py-3">標題</th>
                                    <th class="px-4 py-3">建立者</th>
                                    <th class="px-4 py-3">分類</th>
                                    <th class="px-4 py-3 text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                <?php foreach ($tasks as $t): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-white"><?php echo htmlspecialchars($t['title']); ?></td>
                                        <td class="px-4 py-3 text-zinc-400"><?php echo htmlspecialchars($t['creator_username'] ?? '—'); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($t['category']); ?></td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="./dashboard.php?edit=<?php echo (int) $t['id']; ?>" class="text-amber-200 hover:text-amber-100">編輯</a>
                                            <span class="text-zinc-600"> · </span>
                                            <form method="post" action="./dashboard.php" class="inline" onsubmit="return confirm('確定刪除？');">
                                                <input type="hidden" name="action" value="delete_task">
                                                <input type="hidden" name="task_id" value="<?php echo (int) $t['id']; ?>">
                                                <button type="submit" class="text-rose-300 hover:text-rose-200">刪除</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section>
                <div class="mb-8 border-b border-white/12 pb-5">
                    <p class="text-xs uppercase tracking-[0.2em] text-amber-300">Member / Tasks</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">可參與任務</h1>
                    <p class="mt-2 text-sm text-zinc-300">登入後可查看完整說明；若項目方設有欄位，提交時請填寫。</p>
                </div>

                <?php if (empty($tasks)): ?>
                    <div class="rounded-3xl border border-dashed border-amber-300/30 bg-white/[0.03] p-10 text-center text-sm text-zinc-300">目前尚無任務，請稍後再試。</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($tasks as $task): ?>
                            <?php
                            $completed = in_array((int) $task['id'], $completedTaskIds, true);
                            $schema = [];
                            if (!empty($task['form_schema'])) {
                                $dec = json_decode((string) $task['form_schema'], true);
                                if (is_array($dec)) {
                                    $schema = $dec;
                                }
                            }
                            ?>
                            <article class="group flex h-full flex-col rounded-3xl border border-white/10 bg-white/[0.04] p-6 shadow-[0_15px_40px_-30px_rgba(0,0,0,0.9)] transition hover:-translate-y-0.5 hover:border-amber-300/45 hover:bg-white/[0.065]">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="rounded-full border border-amber-300/35 bg-amber-300/10 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.12em] text-amber-200"><?php echo htmlspecialchars($task['category']); ?></span>
                                    <span class="text-xs text-zinc-500"><?php echo htmlspecialchars(formatTaskDate($task['created_at'])); ?></span>
                                </div>
                                <h2 class="mt-5 text-xl font-semibold leading-snug tracking-tight text-white"><?php echo htmlspecialchars($task['title']); ?></h2>
                                <p class="mt-3 flex-1 text-sm leading-relaxed text-zinc-300"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                                    <span class="text-base font-semibold text-amber-200">+<?php echo (int) $task['reward_xp']; ?> XP</span>
                                    <?php if ($completed): ?>
                                        <span class="text-sm font-semibold text-emerald-300">已完成</span>
                                    <?php else: ?>
                                        <form method="post" action="./dashboard.php" class="w-full space-y-3">
                                            <input type="hidden" name="action" value="complete_task">
                                            <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                                            <?php foreach ($schema as $field): ?>
                                                <?php
                                                $fk = isset($field['key']) ? (string) $field['key'] : '';
                                                if ($fk === '') {
                                                    continue;
                                                }
                                                $fl = htmlspecialchars($field['label'] ?? $fk);
                                                $ft = (($field['type'] ?? '') === 'url') ? 'url' : 'text';
                                                ?>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-zinc-400" for="r<?php echo (int) $task['id']; ?>_<?php echo htmlspecialchars($fk); ?>"><?php echo $fl; ?></label>
                                                    <input id="r<?php echo (int) $task['id']; ?>_<?php echo htmlspecialchars($fk); ?>" name="response[<?php echo htmlspecialchars($fk); ?>]" type="<?php echo $ft; ?>" class="w-full rounded-xl border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" <?php echo count($schema) > 0 ? 'required' : ''; ?>>
                                                </div>
                                            <?php endforeach; ?>
                                            <button type="submit" class="w-full rounded-full border border-white/20 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40">
                                                <?php echo count($schema) > 0 ? '提交並完成' : '標記完成'; ?>
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

    <?php require __DIR__ . '/includes/site_footer.php'; ?>

    <template id="admin-field-template">
        <div class="field-row grid grid-cols-1 gap-2 rounded-xl border border-white/10 bg-black/20 p-3 md:grid-cols-12 md:items-end">
            <div class="md:col-span-3">
                <label class="text-xs text-zinc-400">欄位代碼</label>
                <input name="field_key[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" placeholder="wallet">
            </div>
            <div class="md:col-span-5">
                <label class="text-xs text-zinc-400">顯示名稱</label>
                <input name="field_label[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" placeholder="錢包地址">
            </div>
            <div class="md:col-span-3">
                <label class="text-xs text-zinc-400">類型</label>
                <select name="field_type[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                    <option value="text">文字</option>
                    <option value="url">網址</option>
                </select>
            </div>
            <div class="md:col-span-1 flex md:justify-end">
                <button type="button" class="remove-row mt-4 rounded-lg border border-white/20 px-2 py-2 text-xs text-zinc-300 hover:bg-white/10">刪</button>
            </div>
        </div>
    </template>

    <?php if ($role === 'admin'): ?>
    <script>
        (function () {
            var c = document.getElementById('admin-field-rows');
            var tpl = document.getElementById('admin-field-template');
            var add = document.getElementById('admin-add-field');
            if (!c || !tpl || !add) return;
            function bind(row) {
                var b = row.querySelector('.remove-row');
                if (b) b.addEventListener('click', function () { row.remove(); });
            }
            c.querySelectorAll('.field-row').forEach(bind);
            add.addEventListener('click', function () {
                var node = tpl.content.cloneNode(true);
                var row = node.querySelector('.field-row');
                c.appendChild(row);
                bind(row);
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
