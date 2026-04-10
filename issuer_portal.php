<?php
session_start();
require_once './db.php';
require_once __DIR__ . '/includes/task_helpers.php';
require_once __DIR__ . '/includes/form_schema_helpers.php';

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'issuer') {
    header('Location: ./login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Guest';
$message = '';
$error = '';

function issuerBuildFormSchemaFromPost(): string
{
    $keys = $_POST['field_key'] ?? [];
    $labels = $_POST['field_label'] ?? [];
    $types = $_POST['field_type'] ?? [];
    $requiredFlags = $_POST['field_required'] ?? [];
    if (!is_array($keys)) {
        $keys = [];
    }
    if (!is_array($labels)) {
        $labels = [];
    }
    if (!is_array($types)) {
        $types = [];
    }
    if (!is_array($requiredFlags)) {
        $requiredFlags = [];
    }
    $out = [];
    $n = max(count($keys), count($labels), count($types), count($requiredFlags));
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
        $allowed = ['text', 'url', 'textarea', 'email', 'checkbox'];
        if (!in_array($type, $allowed, true)) {
            $type = 'text';
        }
        $req = isset($requiredFlags[$i]) && (string) $requiredFlags[$i] === '1';
        $out[] = ['key' => $key, 'label' => $label, 'type' => $type, 'required' => $req];
    }

    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rewardXp = (int) ($_POST['reward_xp'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $taskStatus = (($_POST['task_status'] ?? '') === 'ended') ? 'ended' : 'published';
        $startsAt = task_parse_datetime_local_input((string) ($_POST['starts_at'] ?? ''));
        $endsAt = task_parse_datetime_local_input((string) ($_POST['ends_at'] ?? ''));
        $maxRaw = trim((string) ($_POST['max_completions'] ?? ''));
        $maxCompletions = ($maxRaw === '') ? null : max(1, (int) $maxRaw);
        $coverUrl = trim((string) ($_POST['cover_image_url'] ?? ''));
        $coverBind = $coverUrl === '' ? null : $coverUrl;
        $schemaJson = issuerBuildFormSchemaFromPost();

        if ($coverUrl !== '' && !filter_var($coverUrl, FILTER_VALIDATE_URL)) {
            $error = '封面圖網址格式不正確（請使用 http/https 完整網址）。';
        } elseif ($title === '' || $summary === '' || $description === '' || $category === '' || $rewardXp < 0) {
            $error = '請填寫完整任務資訊（含公開摘要）。';
        } elseif ($startsAt === null || $endsAt === null) {
            $error = '請填寫開始與截止時間（台灣時間）。';
        } else {
            $dStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startsAt, task_taipei_tz());
            $dEnd = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endsAt, task_taipei_tz());
            if ($dEnd <= $dStart) {
                $error = '截止時間必須晚於開始時間。';
            } else {
                if ($maxCompletions === null) {
                    $stmt = $conn->prepare('INSERT INTO tasks (title, summary, description, cover_image_url, reward_xp, category, task_status, starts_at, ends_at, max_completions, form_schema, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)');
                    $stmt->bind_param('ssssisssssi', $title, $summary, $description, $coverBind, $rewardXp, $category, $taskStatus, $startsAt, $endsAt, $schemaJson, $userId);
                } else {
                    $stmt = $conn->prepare('INSERT INTO tasks (title, summary, description, cover_image_url, reward_xp, category, task_status, starts_at, ends_at, max_completions, form_schema, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('ssssisssssisi', $title, $summary, $description, $coverBind, $rewardXp, $category, $taskStatus, $startsAt, $endsAt, $maxCompletions, $schemaJson, $userId);
                }
                if ($stmt->execute()) {
                    $message = '任務已發布。';
                } else {
                    $error = '發布失敗，請稍後再試。';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'update_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rewardXp = (int) ($_POST['reward_xp'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $taskStatus = (($_POST['task_status'] ?? '') === 'ended') ? 'ended' : 'published';
        $startsAt = task_parse_datetime_local_input((string) ($_POST['starts_at'] ?? ''));
        $endsAt = task_parse_datetime_local_input((string) ($_POST['ends_at'] ?? ''));
        $maxRaw = trim((string) ($_POST['max_completions'] ?? ''));
        $maxCompletions = ($maxRaw === '') ? null : max(1, (int) $maxRaw);
        $coverUrl = trim((string) ($_POST['cover_image_url'] ?? ''));
        $coverBind = $coverUrl === '' ? null : $coverUrl;
        $schemaJson = issuerBuildFormSchemaFromPost();

        if ($coverUrl !== '' && !filter_var($coverUrl, FILTER_VALIDATE_URL)) {
            $error = '封面圖網址格式不正確（請使用 http/https 完整網址）。';
        } elseif ($taskId <= 0 || $title === '' || $summary === '' || $description === '' || $category === '' || $rewardXp < 0) {
            $error = '請填寫完整任務資訊。';
        } elseif ($startsAt === null || $endsAt === null) {
            $error = '請填寫開始與截止時間（台灣時間）。';
        } else {
            $dStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startsAt, task_taipei_tz());
            $dEnd = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endsAt, task_taipei_tz());
            if ($dEnd <= $dStart) {
                $error = '截止時間必須晚於開始時間。';
            } else {
                if ($maxCompletions === null) {
                    $stmt = $conn->prepare('UPDATE tasks SET title = ?, summary = ?, description = ?, cover_image_url = ?, reward_xp = ?, category = ?, task_status = ?, starts_at = ?, ends_at = ?, max_completions = NULL, form_schema = ? WHERE id = ? AND created_by = ?');
                    $stmt->bind_param('ssssisssssii', $title, $summary, $description, $coverBind, $rewardXp, $category, $taskStatus, $startsAt, $endsAt, $schemaJson, $taskId, $userId);
                } else {
                    $stmt = $conn->prepare('UPDATE tasks SET title = ?, summary = ?, description = ?, cover_image_url = ?, reward_xp = ?, category = ?, task_status = ?, starts_at = ?, ends_at = ?, max_completions = ?, form_schema = ? WHERE id = ? AND created_by = ?');
                    $stmt->bind_param('ssssisssssisii', $title, $summary, $description, $coverBind, $rewardXp, $category, $taskStatus, $startsAt, $endsAt, $maxCompletions, $schemaJson, $taskId, $userId);
                }
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $message = '任務已更新。';
                    } else {
                        $chk = $conn->prepare('SELECT id FROM tasks WHERE id = ? AND created_by = ? LIMIT 1');
                        $chk->bind_param('ii', $taskId, $userId);
                        $chk->execute();
                        $chk->store_result();
                        if ($chk->num_rows === 0) {
                            $error = '找不到任務或無權限編輯。';
                        } else {
                            $message = '任務已更新（內容與先前相同）。';
                        }
                        $chk->close();
                    }
                } else {
                    $error = '更新失敗，請稍後再試。';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'review_submission') {
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        $reviewNote = trim((string) ($_POST['review_note'] ?? ''));
        $defaultReject = '與要求不符，請修正後重新提交。';
        if ($decision === 'reject' && $reviewNote === '') {
            $reviewNote = $defaultReject;
        }
        if ($submissionId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
            $error = '審核資料不正確。';
        } else {
            $chk = $conn->prepare('SELECT s.id, s.user_id, s.task_id, s.status, t.title, t.created_by FROM submissions s INNER JOIN tasks t ON t.id = s.task_id WHERE s.id = ? LIMIT 1');
            $chk->bind_param('i', $submissionId);
            $chk->execute();
            $cr = $chk->get_result();
            $row = $cr ? $cr->fetch_assoc() : null;
            $chk->close();
            if (!$row || ($row['status'] ?? '') !== 'pending') {
                $error = '找不到待審核的提交，或狀態已變更。';
            } elseif ((int) ($row['created_by'] ?? 0) !== $userId) {
                $error = '無權審核此任務的提交。';
            } elseif ($decision === 'approve') {
                $stmt = $conn->prepare("UPDATE submissions SET status = 'approved', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE id = ? AND status = 'pending'");
                $stmt->bind_param('ii', $userId, $submissionId);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = '已核准該筆提交。';
                } else {
                    $error = '核准失敗。';
                }
                $stmt->close();
            } else {
                $title = (string) ($row['title'] ?? '');
                $targetUserId = (int) $row['user_id'];
                $taskIdForNotice = (int) $row['task_id'];
                $noticeBody = '任務「' . $title . '」審核未通過。原因：' . $reviewNote;
                $conn->begin_transaction();
                try {
                    $ins = $conn->prepare('INSERT INTO member_notices (user_id, task_id, message) VALUES (?, ?, ?)');
                    $ins->bind_param('iis', $targetUserId, $taskIdForNotice, $noticeBody);
                    if (!$ins->execute()) {
                        throw new RuntimeException('notice');
                    }
                    $ins->close();
                    $del = $conn->prepare('DELETE FROM submissions WHERE id = ? AND status = ?');
                    $stPending = 'pending';
                    $del->bind_param('is', $submissionId, $stPending);
                    if (!$del->execute() || $del->affected_rows < 1) {
                        throw new RuntimeException('delete');
                    }
                    $del->close();
                    $conn->commit();
                    $message = '已駁回並通知會員。';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = '駁回處理失敗，請稍後再試。';
                }
            }
        }
    }

    if ($action === 'delete_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            $stmt = $conn->prepare('DELETE FROM tasks WHERE id = ? AND created_by = ?');
            $stmt->bind_param('ii', $taskId, $userId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = '任務已刪除。';
            } else {
                $error = '刪除失敗或無權限。';
            }
            $stmt->close();
        }
    }
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editTask = null;
if ($editId > 0) {
    $est = $conn->prepare('SELECT id, title, summary, description, cover_image_url, reward_xp, category, task_status, starts_at, ends_at, max_completions, form_schema, created_at FROM tasks WHERE id = ? AND created_by = ? LIMIT 1');
    $est->bind_param('ii', $editId, $userId);
    $est->execute();
    $eres = $est->get_result();
    if ($eres && ($row = $eres->fetch_assoc())) {
        $editTask = $row;
    }
    $est->close();
}

$submissionsTaskId = isset($_GET['submissions']) ? (int) $_GET['submissions'] : 0;
$submissionRows = [];
$submissionTaskTitle = '';
if ($submissionsTaskId > 0) {
    $own = $conn->prepare('SELECT title FROM tasks WHERE id = ? AND created_by = ? LIMIT 1');
    $own->bind_param('ii', $submissionsTaskId, $userId);
    $own->execute();
    $ort = $own->get_result();
    if ($ort && ($row = $ort->fetch_assoc())) {
        $submissionTaskTitle = $row['title'];
        $own->close();
        $q = $conn->prepare('SELECT s.id AS submission_id, s.status, s.submitted_at, s.response_json, u.username FROM submissions s JOIN users u ON u.id = s.user_id WHERE s.task_id = ? ORDER BY s.submitted_at DESC');
        $q->bind_param('i', $submissionsTaskId);
        $q->execute();
        $qr = $q->get_result();
        if ($qr) {
            while ($r = $qr->fetch_assoc()) {
                $submissionRows[] = $r;
            }
        }
        $q->close();
    } else {
        $own->close();
    }
}

$myTasks = [];
$mt = $conn->prepare('SELECT id, title, summary, reward_xp, category, task_status, starts_at, ends_at, max_completions, created_at FROM tasks WHERE created_by = ? ORDER BY created_at DESC');
$mt->bind_param('i', $userId);
$mt->execute();
$mtr = $mt->get_result();
if ($mtr) {
    while ($row = $mtr->fetch_assoc()) {
        $myTasks[] = $row;
    }
}
$mt->close();

$schemaFields = [];
if ($editTask && !empty($editTask['form_schema'])) {
    $decoded = json_decode((string) $editTask['form_schema'], true);
    if (is_array($decoded)) {
        $schemaFields = $decoded;
    }
}

$issuerTaskFormStarts = $editTask ? task_datetime_local_value($editTask['starts_at'] ?? '') : task_now_taipei()->format('Y-m-d\TH:i');
$issuerTaskFormEnds = $editTask ? task_datetime_local_value($editTask['ends_at'] ?? '') : task_now_taipei()->modify('+60 days')->format('Y-m-d\TH:i');
$issuerTaskFormStatus = ($editTask && (($editTask['task_status'] ?? '') === 'ended')) ? 'ended' : 'published';
$issuerTaskFormMax = ($editTask && $editTask['max_completions'] !== null && $editTask['max_completions'] !== '') ? (string) (int) $editTask['max_completions'] : '';
$issuerTaskFormCover = $editTask ? trim((string) ($editTask['cover_image_url'] ?? '')) : '';

function formatTaskDate(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }
    return date('Y/m/d', $timestamp);
}

$shellBreadcrumbs = [
    ['label' => '首頁', 'href' => './index.php'],
];
if ($submissionsTaskId > 0 && $submissionTaskTitle !== '') {
    $shellBreadcrumbs[] = ['label' => '項目方中心', 'href' => './issuer_portal.php'];
    $shellBreadcrumbs[] = ['label' => '提交紀錄', 'href' => null];
} elseif ($editId > 0 && $editTask) {
    $shellBreadcrumbs[] = ['label' => '項目方中心', 'href' => './issuer_portal.php'];
    $shellBreadcrumbs[] = ['label' => '編輯任務', 'href' => null];
} else {
    $shellBreadcrumbs[] = ['label' => '項目方中心', 'href' => null];
}
$shellPage = 'auth_issuer';
$shellUser = ['name' => $username, 'role' => 'issuer'];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>項目方中心 | Web3 Task Aggregator</title>
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
        <?php if ($submissionsTaskId > 0 && $submissionTaskTitle !== ''): ?>
            <section class="rounded-3xl border border-white/10 bg-white/[0.04] p-8 shadow-[0_18px_50px_-35px_rgba(0,0,0,0.9)]">
                <a href="./issuer_portal.php" class="text-sm font-medium text-amber-200 hover:text-amber-100">← 返回任務列表</a>
                <h1 class="mt-4 text-2xl font-semibold text-white">提交紀錄</h1>
                <p class="mt-1 text-sm text-zinc-300"><?php echo htmlspecialchars($submissionTaskTitle); ?></p>
                <?php if (empty($submissionRows)): ?>
                    <p class="mt-6 text-sm text-zinc-400">尚無提交紀錄。</p>
                <?php else: ?>
                    <div class="mt-6 overflow-x-auto rounded-2xl border border-white/10">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-white/10 bg-white/[0.04] text-xs uppercase tracking-wider text-zinc-400">
                                <tr>
                                    <th class="px-4 py-3">會員</th>
                                    <th class="px-4 py-3">狀態</th>
                                    <th class="px-4 py-3">提交時間</th>
                                    <th class="px-4 py-3">自訂欄位內容</th>
                                    <th class="px-4 py-3 text-right">審核</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                <?php foreach ($submissionRows as $sr): ?>
                                    <?php
                                    $srStatus = (string) ($sr['status'] ?? '');
                                    $isPending = ($srStatus === 'pending');
                                    ?>
                                    <tr class="text-zinc-200">
                                        <td class="px-4 py-3 font-medium text-white"><?php echo htmlspecialchars($sr['username']); ?></td>
                                        <td class="px-4 py-3">
                                            <?php if ($srStatus === 'approved'): ?>
                                                <span class="rounded-full border border-emerald-400/40 bg-emerald-500/15 px-2 py-0.5 text-xs font-semibold text-emerald-200">已核准</span>
                                            <?php elseif ($isPending): ?>
                                                <span class="rounded-full border border-amber-400/40 bg-amber-500/15 px-2 py-0.5 text-xs font-semibold text-amber-200">待審核</span>
                                            <?php else: ?>
                                                <span class="text-zinc-500"><?php echo htmlspecialchars($srStatus); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-400"><?php echo htmlspecialchars(task_format_taipei_display($sr['submitted_at'] ?? '')); ?></td>
                                        <td class="px-4 py-3 text-zinc-300">
                                            <?php
                                            $rj = $sr['response_json'] ?? null;
                                            if ($rj === null || $rj === '') {
                                                echo '—';
                                            } else {
                                                $obj = is_string($rj) ? json_decode($rj, true) : $rj;
                                                if (is_array($obj)) {
                                                    foreach ($obj as $k => $v) {
                                                        echo '<div><span class="text-zinc-500">' . htmlspecialchars((string) $k) . ':</span> ' . htmlspecialchars((string) $v) . '</div>';
                                                    }
                                                } else {
                                                    echo htmlspecialchars((string) $rj);
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 py-3 text-right align-top">
                                            <?php if ($isPending): ?>
                                                <div class="flex flex-col items-end gap-2">
                                                    <form method="post" action="./issuer_portal.php?submissions=<?php echo (int) $submissionsTaskId; ?>" class="inline">
                                                        <input type="hidden" name="action" value="review_submission">
                                                        <input type="hidden" name="submission_id" value="<?php echo (int) $sr['submission_id']; ?>">
                                                        <input type="hidden" name="decision" value="approve">
                                                        <button type="submit" class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-semibold text-black hover:bg-emerald-300">核准</button>
                                                    </form>
                                                    <form method="post" action="./issuer_portal.php?submissions=<?php echo (int) $submissionsTaskId; ?>" class="flex w-full max-w-xs flex-col gap-2 sm:max-w-none sm:items-end">
                                                        <input type="hidden" name="action" value="review_submission">
                                                        <input type="hidden" name="submission_id" value="<?php echo (int) $sr['submission_id']; ?>">
                                                        <input type="hidden" name="decision" value="reject">
                                                        <label class="sr-only" for="irn<?php echo (int) $sr['submission_id']; ?>">駁回原因</label>
                                                        <input id="irn<?php echo (int) $sr['submission_id']; ?>" name="review_note" type="text" class="min-w-0 w-full rounded-lg border border-white/15 bg-white/[0.06] px-2 py-2 text-xs text-white placeholder:text-zinc-500 sm:w-48" placeholder="駁回原因">
                                                        <button type="submit" class="shrink-0 self-stretch rounded-full border border-rose-400/50 bg-rose-500/15 px-3 py-1.5 text-xs font-semibold text-rose-200 sm:self-end">駁回</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-zinc-600">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="rounded-3xl border border-white/10 bg-white/[0.04] p-8 shadow-[0_18px_50px_-35px_rgba(0,0,0,0.9)]">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">項目方 · <?php echo $editTask ? '編輯任務' : '新增任務'; ?></p>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white"><?php echo $editTask ? '編輯任務' : '發布任務（活動）'; ?></h1>
                <p class="mt-2 text-sm text-zinc-300">公開摘要會顯示在首頁給訪客；完整說明與自訂欄位在會員登入後於後台填寫。</p>

                <form method="post" action="./issuer_portal.php<?php echo $editTask ? '?edit=' . (int) $editTask['id'] : ''; ?>" class="mt-6 space-y-4" id="issuer-task-form">
                    <input type="hidden" name="action" value="<?php echo $editTask ? 'update_task' : 'create_task'; ?>">
                    <?php if ($editTask): ?>
                        <input type="hidden" name="task_id" value="<?php echo (int) $editTask['id']; ?>">
                    <?php endif; ?>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-300" for="title">任務標題</label>
                        <input id="title" name="title" type="text" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo $editTask ? htmlspecialchars($editTask['title']) : ''; ?>">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-300" for="summary">公開摘要（訪客可見）</label>
                        <textarea id="summary" name="summary" rows="2" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required placeholder="簡短說明任務重點，勿放敏感資訊"><?php echo $editTask ? htmlspecialchars($editTask['summary']) : ''; ?></textarea>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-300" for="description">完整說明（登入後可見）</label>
                        <textarea id="description" name="description" rows="4" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required><?php echo $editTask ? htmlspecialchars($editTask['description']) : ''; ?></textarea>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-300" for="cover_image_url">封面圖網址（選填）</label>
                        <input id="cover_image_url" name="cover_image_url" type="url" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" placeholder="https://…" value="<?php echo htmlspecialchars($issuerTaskFormCover); ?>">
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-300" for="reward_xp">獎勵 XP</label>
                            <input id="reward_xp" name="reward_xp" type="number" min="0" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo $editTask ? (int) $editTask['reward_xp'] : '0'; ?>">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-300" for="category">分類</label>
                            <input id="category" name="category" type="text" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo $editTask ? htmlspecialchars($editTask['category']) : ''; ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-300" for="starts_at">開始時間（台灣時間）</label>
                            <input id="starts_at" name="starts_at" type="datetime-local" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo htmlspecialchars($issuerTaskFormStarts); ?>">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-300" for="ends_at">截止時間（台灣時間）</label>
                            <input id="ends_at" name="ends_at" type="datetime-local" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo htmlspecialchars($issuerTaskFormEnds); ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-300" for="task_status">任務狀態</label>
                            <select id="task_status" name="task_status" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50">
                                <option value="published" <?php echo $issuerTaskFormStatus === 'published' ? 'selected' : ''; ?>>已發布</option>
                                <option value="ended" <?php echo $issuerTaskFormStatus === 'ended' ? 'selected' : ''; ?>>已結束（不可提交）</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-300" for="max_completions">核准名額上限（選填）</label>
                            <input id="max_completions" name="max_completions" type="number" min="1" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" placeholder="留空＝不限名額" value="<?php echo htmlspecialchars($issuerTaskFormMax); ?>">
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                        <p class="text-sm font-medium text-white">會員完成任務時要填的欄位</p>
                        <p class="mt-1 text-xs text-zinc-500">類型含單行、多行、網址、Email、勾選；可設定必填／選填。欄位代碼僅能使用英文、數字、底線。</p>
                        <div id="field-rows" class="mt-4 space-y-3">
                            <?php if (!empty($schemaFields)): ?>
                                <?php foreach ($schemaFields as $idx => $f): ?>
                                    <?php
                                    $ft = (string) ($f['type'] ?? 'text');
                                    $fr = !isset($f['required']) || $f['required'] === true || $f['required'] === 1 || $f['required'] === '1';
                                    ?>
                                    <div class="field-row grid grid-cols-1 gap-2 rounded-xl border border-white/10 bg-black/20 p-3 lg:grid-cols-12 lg:items-end">
                                        <div class="lg:col-span-2">
                                            <label class="text-xs text-zinc-400">欄位代碼</label>
                                            <input name="field_key[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" value="<?php echo htmlspecialchars($f['key'] ?? ''); ?>" placeholder="wallet">
                                        </div>
                                        <div class="lg:col-span-4">
                                            <label class="text-xs text-zinc-400">顯示名稱</label>
                                            <input name="field_label[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" value="<?php echo htmlspecialchars($f['label'] ?? ''); ?>" placeholder="錢包地址">
                                        </div>
                                        <div class="lg:col-span-3">
                                            <label class="text-xs text-zinc-400">類型</label>
                                            <select name="field_type[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                                                <option value="text" <?php echo $ft === 'text' ? 'selected' : ''; ?>>單行文字</option>
                                                <option value="textarea" <?php echo $ft === 'textarea' ? 'selected' : ''; ?>>多行文字</option>
                                                <option value="url" <?php echo $ft === 'url' ? 'selected' : ''; ?>>網址</option>
                                                <option value="email" <?php echo $ft === 'email' ? 'selected' : ''; ?>>Email</option>
                                                <option value="checkbox" <?php echo $ft === 'checkbox' ? 'selected' : ''; ?>>勾選</option>
                                            </select>
                                        </div>
                                        <div class="lg:col-span-2">
                                            <label class="text-xs text-zinc-400">驗證</label>
                                            <select name="field_required[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                                                <option value="1" <?php echo $fr ? 'selected' : ''; ?>>必填</option>
                                                <option value="0" <?php echo $fr ? '' : 'selected'; ?>>選填</option>
                                            </select>
                                        </div>
                                        <div class="lg:col-span-1 flex lg:justify-end">
                                            <button type="button" class="remove-row mt-4 rounded-lg border border-white/20 px-2 py-2 text-xs text-zinc-300 hover:bg-white/10">刪</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" id="add-field-row" class="mt-3 rounded-full border border-amber-300/35 bg-amber-200/10 px-4 py-2 text-sm font-medium text-white hover:bg-amber-200/20">新增欄位</button>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black transition hover:bg-amber-200"><?php echo $editTask ? '儲存變更' : '發布任務'; ?></button>
                        <?php if ($editTask): ?>
                            <a href="./issuer_portal.php" class="rounded-full border border-white/20 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">取消編輯</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section>
                <div class="mb-6 border-b border-white/12 pb-5">
                    <p class="text-xs uppercase tracking-[0.2em] text-amber-300">我的任務</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">我發布的任務</h2>
                </div>
                <?php if (empty($myTasks)): ?>
                    <div class="rounded-3xl border border-dashed border-amber-300/30 bg-white/[0.03] p-10 text-center text-sm text-zinc-300">尚無任務，請使用上方表單發布。</div>
                <?php else: ?>
                    <div class="overflow-x-auto rounded-2xl border border-white/10">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-white/10 bg-white/[0.04] text-xs uppercase tracking-wider text-zinc-400">
                                <tr>
                                    <th class="px-4 py-3">標題</th>
                                    <th class="px-4 py-3">分類</th>
                                    <th class="px-4 py-3">XP</th>
                                    <th class="px-4 py-3">建立</th>
                                    <th class="px-4 py-3 text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                <?php foreach ($myTasks as $t): ?>
                                    <tr class="text-zinc-200">
                                        <td class="px-4 py-3 font-medium text-white"><?php echo htmlspecialchars($t['title']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($t['category']); ?></td>
                                        <td class="px-4 py-3"><?php echo (int) $t['reward_xp']; ?></td>
                                        <td class="px-4 py-3 text-zinc-500"><?php echo htmlspecialchars(formatTaskDate($t['created_at'])); ?></td>
                                        <td class="px-4 py-3 text-right">
                                            <a class="text-amber-200 hover:text-amber-100" href="./issuer_portal.php?submissions=<?php echo (int) $t['id']; ?>">提交紀錄</a>
                                            <span class="text-zinc-600"> · </span>
                                            <a class="text-amber-200 hover:text-amber-100" href="./issuer_portal.php?edit=<?php echo (int) $t['id']; ?>">編輯</a>
                                            <span class="text-zinc-600"> · </span>
                                            <form method="post" action="./issuer_portal.php" class="inline" onsubmit="return confirm('確定刪除此任務？');">
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
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>

    <template id="field-row-template">
        <div class="field-row grid grid-cols-1 gap-2 rounded-xl border border-white/10 bg-black/20 p-3 lg:grid-cols-12 lg:items-end">
            <div class="lg:col-span-2">
                <label class="text-xs text-zinc-400">欄位代碼</label>
                <input name="field_key[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" placeholder="wallet">
            </div>
            <div class="lg:col-span-4">
                <label class="text-xs text-zinc-400">顯示名稱</label>
                <input name="field_label[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" placeholder="錢包地址">
            </div>
            <div class="lg:col-span-3">
                <label class="text-xs text-zinc-400">類型</label>
                <select name="field_type[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                    <option value="text">單行文字</option>
                    <option value="textarea">多行文字</option>
                    <option value="url">網址</option>
                    <option value="email">Email</option>
                    <option value="checkbox">勾選</option>
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="text-xs text-zinc-400">驗證</label>
                <select name="field_required[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white">
                    <option value="1" selected>必填</option>
                    <option value="0">選填</option>
                </select>
            </div>
            <div class="lg:col-span-1 flex lg:justify-end">
                <button type="button" class="remove-row mt-4 rounded-lg border border-white/20 px-2 py-2 text-xs text-zinc-300 hover:bg-white/10">刪</button>
            </div>
        </div>
    </template>

    <script>
        (function () {
            var container = document.getElementById('field-rows');
            var tpl = document.getElementById('field-row-template');
            var addBtn = document.getElementById('add-field-row');
            if (!container || !tpl || !addBtn) return;

            function bindRemove(row) {
                var btn = row.querySelector('.remove-row');
                if (btn) btn.addEventListener('click', function () { row.remove(); });
            }

            container.querySelectorAll('.field-row').forEach(bindRemove);

            addBtn.addEventListener('click', function () {
                var node = tpl.content.cloneNode(true);
                var row = node.querySelector('.field-row');
                container.appendChild(row);
                bindRemove(row);
            });
        })();
    </script>
</body>
</html>
