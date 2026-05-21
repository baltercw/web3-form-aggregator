<?php
session_start();
require_once './db.php';
require_once __DIR__ . '/includes/task_helpers.php';
require_once __DIR__ . '/includes/form_schema_helpers.php';

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

    if ($role === 'admin' && $action === 'create_task') {
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
        $schemaJson = dashboardBuildFormSchemaFromPost();

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
                    $stmt->bind_param('ssssissssisi', $title, $summary, $description, $coverBind, $rewardXp, $category, $taskStatus, $startsAt, $endsAt, $maxCompletions, $schemaJson, $userId);
                }
                if ($stmt->execute()) {
                    $message = '新任務已成功發布。';
                } else {
                    $error = '任務發布失敗，請稍後再試。';
                }
                $stmt->close();
            }
        }
    }

    if ($role === 'admin' && $action === 'update_task') {
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
        $schemaJson = dashboardBuildFormSchemaFromPost();

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
                    $stmt = $conn->prepare('UPDATE tasks SET title = ?, summary = ?, description = ?, cover_image_url = ?, reward_xp = ?, category = ?, task_status = ?, starts_at = ?, ends_at = ?, max_completions = NULL, form_schema = ? WHERE id = ?');
                    $stmt->bind_param('ssssisssssi', $title, $summary, $description, $coverBind, $rewardXp, $category, $taskStatus, $startsAt, $endsAt, $schemaJson, $taskId);
                } else {
                    $stmt = $conn->prepare('UPDATE tasks SET title = ?, summary = ?, description = ?, cover_image_url = ?, reward_xp = ?, category = ?, task_status = ?, starts_at = ?, ends_at = ?, max_completions = ?, form_schema = ? WHERE id = ?');
                    $stmt->bind_param('ssssissssisi', $title, $summary, $description, $coverBind, $rewardXp, $category, $taskStatus, $startsAt, $endsAt, $maxCompletions, $schemaJson, $taskId);
                }
                if ($stmt->execute()) {
                    $message = '任務已更新。';
                } else {
                    $error = '更新失敗，請稍後再試。';
                }
                $stmt->close();
            }
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

    if ($role === 'member' && $action === 'dismiss_notice') {
        $noticeId = (int) ($_POST['notice_id'] ?? 0);
        if ($noticeId > 0) {
            $stmt = $conn->prepare('DELETE FROM member_notices WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $noticeId, $userId);
            $stmt->execute();
            $stmt->close();
            $message = '已關閉通知。';
        }
    }

    if ($role === 'admin' && $action === 'review_submission') {
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
            $chk = $conn->prepare('SELECT s.id, s.user_id, s.task_id, s.status, t.title FROM submissions s INNER JOIN tasks t ON t.id = s.task_id WHERE s.id = ? LIMIT 1');
            $chk->bind_param('i', $submissionId);
            $chk->execute();
            $cr = $chk->get_result();
            $row = $cr ? $cr->fetch_assoc() : null;
            $chk->close();
            if (!$row || ($row['status'] ?? '') !== 'pending') {
                $error = '找不到待審核的提交，或狀態已變更。';
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
                    $message = '已駁回並通知會員；對方可重新提交（表單將嘗試帶入先前填寫）。';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = '駁回處理失敗，請稍後再試。';
                }
            }
        }
    }

    if ($role === 'member' && $action === 'complete_task') {
        $completeTaskId = (int) ($_POST['task_id'] ?? 0);
        $completeErr = '';

        if ($completeTaskId <= 0) {
            $completeErr = '任務資料不正確。';
        } else {
            $tstmt = $conn->prepare(
                'SELECT t.id, t.form_schema, t.task_status, t.starts_at, t.ends_at, t.max_completions, '
                . '(SELECT COUNT(*) FROM submissions s WHERE s.task_id = t.id AND s.status = ?) AS approved_count '
                . 'FROM tasks t WHERE t.id = ? LIMIT 1'
            );
            $stAp = 'approved';
            $tstmt->bind_param('si', $stAp, $completeTaskId);
            $tstmt->execute();
            $tres = $tstmt->get_result();
            $taskRow = $tres ? $tres->fetch_assoc() : null;
            $tstmt->close();

            if (!$taskRow) {
                $completeErr = '找不到任務。';
            } else {
                $existStmt = $conn->prepare('SELECT status FROM submissions WHERE user_id = ? AND task_id = ? LIMIT 1');
                $existStmt->bind_param('ii', $userId, $completeTaskId);
                $existStmt->execute();
                $exr = $existStmt->get_result();
                $existRow = $exr ? $exr->fetch_assoc() : null;
                $existStmt->close();

                if ($existRow) {
                    if (($existRow['status'] ?? '') === 'pending') {
                        $completeErr = '此任務已有提交正在審核中，請稍候。';
                    } elseif (($existRow['status'] ?? '') === 'approved') {
                        $completeErr = '此任務您已通過審核。';
                    } else {
                        $completeErr = '無法提交，請重新整理頁面。';
                    }
                } else {
                    $approvedCount = (int) ($taskRow['approved_count'] ?? 0);
                    $gate = task_submission_gate($taskRow, $approvedCount);
                    if (!$gate['can_submit']) {
                        $completeErr = '目前無法提交：' . implode('；', $gate['block_labels']);
                    } else {
                        $schemaRaw = [];
                        if (!empty($taskRow['form_schema'])) {
                            $dec = json_decode((string) $taskRow['form_schema'], true);
                            if (is_array($dec)) {
                                $schemaRaw = $dec;
                            }
                        }
                        $schema = form_schema_normalize_list($schemaRaw);
                        $responsePost = $_POST['response'] ?? [];
                        if (!is_array($responsePost)) {
                            $responsePost = [];
                        }
                        [$respOk, $respErr, $responses] = form_schema_collect_responses($schema, $responsePost);
                        if (!$respOk) {
                            $completeErr = $respErr;
                        } else {
                            $jsonStr = count($responses) > 0 ? json_encode($responses, JSON_UNESCAPED_UNICODE) : null;

                            if ($jsonStr !== null) {
                                $stmt = $conn->prepare("INSERT INTO submissions (user_id, task_id, status, response_json) VALUES (?, ?, 'pending', ?)");
                                $stmt->bind_param('iis', $userId, $completeTaskId, $jsonStr);
                            } else {
                                $stmt = $conn->prepare("INSERT INTO submissions (user_id, task_id, status) VALUES (?, ?, 'pending')");
                                $stmt->bind_param('ii', $userId, $completeTaskId);
                            }

                            if ($stmt->execute()) {
                                $message = '已提交，等待項目方／管理員審核；通過後即視為完成。';
                            } else {
                                $completeErr = '提交失敗（可能重複提交），請重新整理後再試。';
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }

        if ($completeErr !== '') {
            if ($completeTaskId > 0) {
                $_SESSION['member_task_flash_error'] = $completeErr;
                header('Location: ./dashboard.php#task-' . $completeTaskId);
                exit;
            }
            $error = $completeErr;
        }
    }
}

$memberTaskFlashError = '';
if ($role === 'member' && !empty($_SESSION['member_task_flash_error'])) {
    $memberTaskFlashError = (string) $_SESSION['member_task_flash_error'];
    unset($_SESSION['member_task_flash_error']);
}

$adminEditId = ($role === 'admin' && isset($_GET['edit'])) ? (int) $_GET['edit'] : 0;
$adminEditTask = null;
$adminSchemaFields = [];
if ($adminEditId > 0) {
    $ed = $conn->prepare('SELECT id, title, summary, description, cover_image_url, reward_xp, category, task_status, starts_at, ends_at, max_completions, form_schema FROM tasks WHERE id = ? LIMIT 1');
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

$adminTaskFormStarts = $adminEditTask ? task_datetime_local_value($adminEditTask['starts_at'] ?? '') : task_now_taipei()->format('Y-m-d\TH:i');
$adminTaskFormEnds = $adminEditTask ? task_datetime_local_value($adminEditTask['ends_at'] ?? '') : task_now_taipei()->modify('+60 days')->format('Y-m-d\TH:i');
$adminTaskFormStatus = ($adminEditTask && (($adminEditTask['task_status'] ?? '') === 'ended')) ? 'ended' : 'published';
$adminTaskFormMax = ($adminEditTask && $adminEditTask['max_completions'] !== null && $adminEditTask['max_completions'] !== '') ? (string) (int) $adminEditTask['max_completions'] : '';
$adminTaskFormCover = $adminEditTask ? trim((string) ($adminEditTask['cover_image_url'] ?? '')) : '';

$tasks = [];
$sql = 'SELECT t.id, t.title, t.summary, t.description, t.cover_image_url, t.reward_xp, t.category, t.task_status, t.starts_at, t.ends_at, t.max_completions, t.form_schema, t.created_at, t.created_by, '
    . '(SELECT COUNT(*) FROM submissions s WHERE s.task_id = t.id AND s.status = \'approved\') AS approved_count, '
    . 'u.username AS creator_username '
    . 'FROM tasks t LEFT JOIN users u ON u.id = t.created_by ORDER BY t.created_at DESC';
$taskResult = $conn->query($sql);
if ($taskResult) {
    while ($row = $taskResult->fetch_assoc()) {
        $tasks[] = $row;
    }
}

$allUsers = [];
$pendingReviews = [];
if ($role === 'admin') {
    $ur = $conn->query('SELECT id, username, role FROM users ORDER BY id ASC');
    if ($ur) {
        while ($row = $ur->fetch_assoc()) {
            $allUsers[] = $row;
        }
    }
    $pr = $conn->query(
        'SELECT s.id AS submission_id, s.submitted_at, s.response_json, t.id AS task_id, t.title AS task_title, u.username AS member_username '
        . 'FROM submissions s INNER JOIN tasks t ON t.id = s.task_id INNER JOIN users u ON u.id = s.user_id '
        . "WHERE s.status = 'pending' ORDER BY s.submitted_at ASC"
    );
    if ($pr) {
        while ($row = $pr->fetch_assoc()) {
            $pendingReviews[] = $row;
        }
    }
}

$completedTaskIds = [];
$memberSubmissionByTask = [];
$memberNotices = [];
$memberTasksModalJson = '{}';
if ($role === 'member') {
    $ms = $conn->prepare('SELECT task_id, status FROM submissions WHERE user_id = ?');
    $ms->bind_param('i', $userId);
    $ms->execute();
    $msr = $ms->get_result();
    if ($msr) {
        while ($row = $msr->fetch_assoc()) {
            $tid = (int) $row['task_id'];
            $memberSubmissionByTask[$tid] = (string) $row['status'];
            if (($row['status'] ?? '') === 'approved') {
                $completedTaskIds[] = $tid;
            }
        }
    }
    $ms->close();

    $mn = $conn->prepare('SELECT id, task_id, message, created_at FROM member_notices WHERE user_id = ? ORDER BY created_at DESC');
    $mn->bind_param('i', $userId);
    $mn->execute();
    $mnr = $mn->get_result();
    if ($mnr) {
        while ($row = $mnr->fetch_assoc()) {
            $memberNotices[] = $row;
        }
    }
    $mn->close();

    $mp = [];
    foreach ($tasks as $t) {
        $tid = (int) $t['id'];
        $sch = [];
        if (!empty($t['form_schema'])) {
            $d = json_decode((string) $t['form_schema'], true);
            if (is_array($d)) {
                $sch = form_schema_normalize_list($d);
            }
        }
        $maxC = $t['max_completions'] ?? null;
        $approvedCount = (int) ($t['approved_count'] ?? 0);
        if ($maxC !== null && $maxC !== '' && (int) $maxC > 0) {
            $ql = '核准 ' . $approvedCount . ' / ' . (int) $maxC . '（剩 ' . max(0, (int) $maxC - $approvedCount) . ' 名額）';
        } else {
            $ql = '核准名額不限（目前已核准 ' . $approvedCount . '）';
        }
        $mp[(string) $tid] = [
            'id' => $tid,
            'title' => $t['title'],
            'summary' => $t['summary'],
            'description' => $t['description'],
            'reward_xp' => (int) $t['reward_xp'],
            'cover' => trim((string) ($t['cover_image_url'] ?? '')),
            'timeLine' => task_format_taipei_display($t['starts_at'] ?? '') . ' ～ ' . task_format_taipei_display($t['ends_at'] ?? ''),
            'quotaLine' => $ql,
            'schema' => $sch,
            'web3' => task_web3_flags_from_schema($sch),
        ];
    }
    $memberTasksModalJson = json_encode($mp, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
}

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
if ($role === 'admin' && $adminEditTask) {
    $shellBreadcrumbs[] = ['label' => '管理後台', 'href' => './dashboard.php'];
    $shellBreadcrumbs[] = ['label' => '編輯任務', 'href' => null];
} else {
    $shellBreadcrumbs[] = ['label' => $role === 'member' ? '任務中心' : '管理後台', 'href' => null];
}
$shellPage = 'auth_dashboard';
$shellUser = ['name' => $username, 'role' => $role];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role === 'member' ? '任務中心' : '管理後台'; ?> | Web3 Task Aggregator</title>
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
        <?php $suppressErrorToast = false; ?>
        <?php if ($role === 'admin'): ?>
            <section class="rounded-3xl border border-white/10 bg-white/[0.04] p-8 shadow-[0_18px_50px_-35px_rgba(0,0,0,0.9)]">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">管理後台 · <?php echo $adminEditTask ? '編輯任務' : '發布任務'; ?></p>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white"><?php echo $adminEditTask ? '編輯任務' : '發布新任務'; ?></h1>
                <p class="mt-2 text-sm text-zinc-300">請填公開摘要（首頁訪客可見）與完整說明；可自訂會員提交欄位。</p>

                <form method="post" action="./dashboard.php<?php echo $adminEditTask ? '?edit=' . (int) $adminEditTask['id'] : ''; ?>" class="mt-6 space-y-6" id="admin-task-form">
                    <input type="hidden" name="action" value="<?php echo $adminEditTask ? 'update_task' : 'create_task'; ?>">
                    <?php if ($adminEditTask): ?>
                        <input type="hidden" name="task_id" value="<?php echo (int) $adminEditTask['id']; ?>">
                    <?php endif; ?>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">基本資料</p>
                            <p class="mt-1 text-sm text-zinc-400">首頁訪客看到的公開摘要 + 登入後可見的完整說明。</p>
                        </div>

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

                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-300" for="cover_image_url">封面圖網址（選填）</label>
                            <input id="cover_image_url" name="cover_image_url" type="url" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" placeholder="https://…（會員填寫視窗頂部顯示）" value="<?php echo htmlspecialchars($adminTaskFormCover); ?>">
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
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">時間與名額</p>
                            <p class="mt-1 text-sm text-zinc-400">設定開始/截止與核准名額上限，決定會員是否可提交。</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-zinc-300" for="starts_at">開始時間（台灣時間）</label>
                                <input id="starts_at" name="starts_at" type="datetime-local" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo htmlspecialchars($adminTaskFormStarts); ?>">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-zinc-300" for="ends_at">截止時間（台灣時間）</label>
                                <input id="ends_at" name="ends_at" type="datetime-local" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" required value="<?php echo htmlspecialchars($adminTaskFormEnds); ?>">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-zinc-300" for="task_status">任務狀態</label>
                                <select id="task_status" name="task_status" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50">
                                    <option value="published" <?php echo $adminTaskFormStatus === 'published' ? 'selected' : ''; ?>>已發布（首頁顯示，依時間與名額開放提交）</option>
                                    <option value="ended" <?php echo $adminTaskFormStatus === 'ended' ? 'selected' : ''; ?>>已結束（首頁仍顯示，不可提交）</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-zinc-300" for="max_completions">核准名額上限（選填）</label>
                                <input id="max_completions" name="max_completions" type="number" min="1" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" placeholder="留空＝不限名額" value="<?php echo htmlspecialchars($adminTaskFormMax); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">表單欄位設定</p>
                        <p class="mt-2 text-sm text-zinc-400">會員完成任務時要填的欄位（可設定必填／選填）。</p>
                        <div id="admin-field-rows" class="mt-4 space-y-3">
                            <?php foreach ($adminSchemaFields as $f): ?>
                                <?php
                                $ft = (string) ($f['type'] ?? 'text');
                                $fr = !isset($f['required']) || $f['required'] === true || $f['required'] === 1 || $f['required'] === '1';
                                ?>
                                <div class="field-row grid grid-cols-1 gap-2 rounded-xl border border-white/10 bg-black/20 p-3 lg:grid-cols-12 lg:items-end">
                                    <div class="lg:col-span-2">
                                        <label class="text-xs text-zinc-400">欄位代碼</label>
                                        <input name="field_key[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" value="<?php echo htmlspecialchars($f['key'] ?? ''); ?>">
                                    </div>
                                    <div class="lg:col-span-4">
                                        <label class="text-xs text-zinc-400">顯示名稱</label>
                                        <input name="field_label[]" class="mt-1 w-full rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white" value="<?php echo htmlspecialchars($f['label'] ?? ''); ?>">
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

                <div id="admin-confirm-modal" class="fixed inset-0 z-[95] hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="admin-confirm-title">
                    <div id="admin-confirm-backdrop" class="absolute inset-0 bg-black/70 backdrop-blur-md"></div>
                    <div class="pointer-events-none absolute inset-0 flex items-end justify-center p-0 md:items-center md:p-4">
                        <div class="pointer-events-auto w-full max-w-xl rounded-t-3xl border border-white/15 bg-[#101010] shadow-2xl md:rounded-3xl">
                            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-white/10 px-4 py-4 md:px-6">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium uppercase tracking-wider text-amber-300/90">總覽</p>
                                    <h3 id="admin-confirm-title" class="mt-1 text-lg font-semibold text-white"></h3>
                                </div>
                                <button type="button" id="admin-confirm-cancel" class="rounded-xl border border-white/15 bg-white/5 px-3 py-2 text-sm font-medium text-zinc-200 hover:bg-white/10">取消</button>
                            </div>
                            <div class="px-4 py-4 md:px-6">
                                <div id="admin-confirm-summary" class="space-y-2 text-sm text-zinc-300"></div>
                                <p class="mt-3 text-[11px] text-zinc-500">確認後將送出表單並更新任務資訊。</p>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-white/10 px-4 py-3 md:px-6">
                                <button type="button" id="admin-confirm-proceed" class="rounded-full bg-amber-300 px-5 py-2 text-sm font-semibold text-black transition hover:bg-amber-200">確認送出</button>
                            </div>
                        </div>
                    </div>
                </div>
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

            <section class="rounded-3xl border border-amber-300/25 bg-amber-300/[0.06] p-8">
                <h2 class="text-xl font-semibold text-white">待審核提交（全站）</h2>
                <p class="mt-1 text-sm text-zinc-300">核准後會員端顯示為「已通過」；駁回將刪除該筆提交並發送站內通知。</p>
                <?php if (empty($pendingReviews)): ?>
                    <p class="mt-4 text-sm text-zinc-500">目前沒有待審核項目。</p>
                <?php else: ?>
                    <div class="mt-4 space-y-6">
                        <?php foreach ($pendingReviews as $pr): ?>
                            <div class="rounded-2xl border border-white/10 bg-black/25 p-4">
                                <p class="text-sm text-zinc-300">
                                    <span class="font-medium text-white"><?php echo htmlspecialchars($pr['task_title'] ?? ''); ?></span>
                                    <span class="text-zinc-600"> · </span>
                                    會員 <?php echo htmlspecialchars($pr['member_username'] ?? ''); ?>
                                    <span class="text-zinc-600"> · </span>
                                    <?php echo htmlspecialchars(task_format_taipei_display($pr['submitted_at'] ?? '')); ?>
                                </p>
                                <div class="mt-2 text-sm text-zinc-400">
                                    <?php
                                    $rj = $pr['response_json'] ?? '';
                                    if ($rj === '') {
                                        echo '（無自訂欄位資料）';
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
                                </div>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <form method="post" action="./dashboard.php" class="inline">
                                        <input type="hidden" name="action" value="review_submission">
                                        <input type="hidden" name="submission_id" value="<?php echo (int) $pr['submission_id']; ?>">
                                        <input type="hidden" name="decision" value="approve">
                                        <button type="submit" class="rounded-full bg-emerald-400/90 px-4 py-2 text-sm font-semibold text-black hover:bg-emerald-300">核准</button>
                                    </form>
                                    <form method="post" action="./dashboard.php" class="flex w-full max-w-md flex-col gap-2 sm:max-w-none sm:flex-row sm:flex-wrap sm:items-end">
                                        <input type="hidden" name="action" value="review_submission">
                                        <input type="hidden" name="submission_id" value="<?php echo (int) $pr['submission_id']; ?>">
                                        <input type="hidden" name="decision" value="reject">
                                        <label class="sr-only" for="rn<?php echo (int) $pr['submission_id']; ?>">駁回原因</label>
                                        <input id="rn<?php echo (int) $pr['submission_id']; ?>" name="review_note" type="text" class="min-w-0 w-full rounded-xl border border-white/15 bg-white/[0.06] px-3 py-2 text-sm text-white placeholder:text-zinc-500 sm:min-w-[12rem] sm:w-auto sm:flex-1" placeholder="駁回原因（預設：與要求不符…）">
                                        <button type="submit" class="shrink-0 rounded-full border border-rose-400/50 bg-rose-500/15 px-4 py-2 text-sm font-semibold text-rose-200 hover:bg-rose-500/25">駁回</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
            <?php
            $memberInlineError = '';
            if ($role === 'member') {
                $memberInlineError = $memberTaskFlashError !== '' ? $memberTaskFlashError : $error;
            }
            $suppressErrorToast = ($role === 'member' && $memberInlineError !== '');
            ?>
            <section>
                <div class="mb-8 border-b border-white/12 pb-5">
                    <p class="text-xs uppercase tracking-[0.2em] text-amber-300">會員 · 任務</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">可參與任務</h1>
                    <p class="mt-2 text-sm text-zinc-300">提交後需經項目方或管理員審核，通過後即視為完成。駁回時可重新提交，表單會嘗試帶入您上次填寫的內容（僅存於此裝置瀏覽器）。</p>
                </div>

                <?php if ($memberInlineError !== ''): ?>
                    <div id="member-task-error-alert" class="mb-6 rounded-2xl border border-rose-400/40 bg-rose-950/50 px-4 py-3 text-sm font-medium text-rose-100" role="alert" tabindex="-1">
                        <?php echo htmlspecialchars($memberInlineError); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($memberNotices)): ?>
                    <div class="mb-6 space-y-3">
                        <?php foreach ($memberNotices as $mn): ?>
                            <div class="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-rose-400/40 bg-rose-950/50 p-4">
                                <p class="min-w-0 flex-1 text-sm leading-relaxed text-rose-100"><?php echo htmlspecialchars($mn['message']); ?></p>
                                <form method="post" action="./dashboard.php" class="shrink-0">
                                    <input type="hidden" name="action" value="dismiss_notice">
                                    <input type="hidden" name="notice_id" value="<?php echo (int) $mn['id']; ?>">
                                    <button type="submit" class="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-medium text-white hover:bg-white/10">知道了</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($tasks)): ?>
                    <div class="rounded-3xl border border-dashed border-amber-300/30 bg-white/[0.03] p-10 text-center text-sm text-zinc-300">目前尚無任務，請稍後再試。</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($tasks as $task): ?>
                            <?php
                            $tid = (int) $task['id'];
                            $approved = in_array($tid, $completedTaskIds, true);
                            $subSt = $memberSubmissionByTask[$tid] ?? null;
                            $pending = ($subSt === 'pending');
                            $approvedCount = (int) ($task['approved_count'] ?? 0);
                            $gate = task_submission_gate($task, $approvedCount);
                            $schema = [];
                            if (!empty($task['form_schema'])) {
                                $dec = json_decode((string) $task['form_schema'], true);
                                if (is_array($dec)) {
                                    $schema = $dec;
                                }
                            }
                            $maxC = $task['max_completions'] ?? null;
                            if ($maxC !== null && $maxC !== '' && (int) $maxC > 0) {
                                $quotaLine = '核准 ' . $approvedCount . ' / ' . (int) $maxC . '（剩 ' . max(0, (int) $maxC - $approvedCount) . ' 名額）';
                            } else {
                                $quotaLine = '核准名額不限（目前已核准 ' . $approvedCount . '）';
                            }
                            $timeLine = '開放提交（台灣時間）' . task_format_taipei_display($task['starts_at'] ?? '') . ' ～ ' . task_format_taipei_display($task['ends_at'] ?? '');

                            $web3 = task_web3_flags_from_schema($schema);
                            $walletTag = $web3['wallet_input'] ? '錢包連接：不需要（可能需填地址）' : '錢包連接：不需要';
                            $onchainTag = 'On-chain：' . ($web3['onchain'] ? '需要' : '不需要');
                            $kycTag = 'KYC：' . ($web3['kyc'] ? '需要' : '不需要');
                            ?>
                            <article id="task-<?php echo $tid; ?>" class="group scroll-mt-28 flex h-full flex-col rounded-3xl border border-white/10 bg-white/[0.04] p-6 shadow-[0_15px_40px_-30px_rgba(0,0,0,0.9)] transition hover:-translate-y-0.5 hover:border-amber-300/45 hover:bg-white/[0.065]">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="rounded-full border border-amber-300/35 bg-amber-300/10 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.12em] text-amber-200"><?php echo htmlspecialchars($task['category']); ?></span>
                                    <?php if (($task['task_status'] ?? '') === 'ended'): ?>
                                        <span class="rounded-full border border-zinc-500/50 bg-zinc-800/80 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-200">已結束</span>
                                    <?php else: ?>
                                        <?php if ($gate['can_submit']): ?>
                                            <span class="rounded-full border border-emerald-400/45 bg-emerald-500/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-100">開放提交</span>
                                        <?php else: ?>
                                            <span class="rounded-full border-2 border-rose-500/70 bg-rose-950/70 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-rose-100">不可提交</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <h2 class="mt-5 text-xl font-semibold leading-snug tracking-tight text-white"><?php echo htmlspecialchars($task['title']); ?></h2>
                                <p class="mt-2 text-xs leading-relaxed text-zinc-500"><?php echo htmlspecialchars($timeLine); ?></p>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-xs text-zinc-300"><?php echo htmlspecialchars($quotaLine); ?></span>
                                    <span class="rounded-full border border-amber-300/25 bg-amber-300/10 px-3 py-1 text-xs font-semibold text-amber-200">+<?php echo (int) $task['reward_xp']; ?> XP</span>
                                </div>

                                <?php if (!$gate['can_submit'] && !$pending && !$approved): ?>
                                    <div class="mt-3 rounded-xl border border-rose-500/45 bg-rose-950/60 px-3 py-2 text-xs font-semibold text-rose-100">
                                        目前不可提交<?php if (!empty($gate['block_labels'])): ?>：<?php echo htmlspecialchars(implode('；', $gate['block_labels'])); ?><?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <p class="mt-3 text-sm leading-relaxed text-zinc-300"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-[11px] text-zinc-200"><?php echo htmlspecialchars($walletTag); ?></span>
                                    <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-[11px] text-zinc-200"><?php echo htmlspecialchars($onchainTag); ?></span>
                                    <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-[11px] text-zinc-200"><?php echo htmlspecialchars($kycTag); ?></span>
                                </div>
                                <p class="mt-2 text-[11px] text-zinc-500">此任務不會要求你簽署交易、不會要求私鑰。</p>

                                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                                    <div class="w-full space-y-3">
                                        <?php if ($approved): ?>
                                            <span class="text-sm font-semibold text-emerald-300">已通過審核</span>
                                            <span class="hidden w3fa-clear-task-ls" data-task-id="<?php echo $tid; ?>"></span>
                                        <?php elseif ($pending): ?>
                                            <div class="rounded-xl border border-amber-400/40 bg-amber-950/50 px-3 py-2 text-center text-sm font-semibold text-amber-100">審核中，請稍候</div>
                                        <?php elseif (!$gate['can_submit']): ?>
                                            <div class="rounded-xl border-2 border-rose-500/60 bg-rose-950/70 px-3 py-3 text-center text-sm font-bold text-rose-100">目前無法提交任務</div>
                                        <?php else: ?>
                                            <button type="button" class="w3fa-open-task-modal w-full rounded-full border border-amber-300/45 bg-amber-300/15 px-4 py-3 text-sm font-semibold text-amber-100 transition hover:bg-amber-300/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/50" data-task-id="<?php echo $tid; ?>">
                                                開啟填寫視窗
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div id="w3fa-task-modal" class="fixed inset-0 z-[85] hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="w3fa-modal-title">
                <div id="w3fa-task-modal-backdrop" class="absolute inset-0 bg-black/75 backdrop-blur-md"></div>
                <div class="pointer-events-none absolute inset-0 flex items-end justify-center p-0 md:items-center md:p-4">
                    <div id="w3fa-task-modal-panel" class="w3fa-modal-panel pointer-events-auto flex max-h-[min(100dvh,920px)] w-full max-w-2xl flex-col rounded-t-3xl border border-white/15 bg-[#101010] shadow-[0_-20px_60px_rgba(0,0,0,0.85)] md:max-h-[min(92vh,880px)] md:rounded-3xl md:shadow-2xl">
                        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-white/10 px-4 py-4 md:px-6">
                            <div class="min-w-0">
                                <p class="text-xs font-medium uppercase tracking-wider text-amber-300/90">提交審核</p>
                                <h2 id="w3fa-modal-title" class="mt-1 text-lg font-semibold leading-snug text-white md:text-xl"></h2>
                            </div>
                            <button type="button" id="w3fa-task-modal-close" class="shrink-0 rounded-xl border border-white/15 bg-white/5 px-3 py-2 text-sm font-medium text-zinc-200 hover:bg-white/10">關閉</button>
                        </div>
                        <div id="w3fa-modal-scroll" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-4 pt-3 md:px-6 md:pb-6 md:pt-4">
                            <div class="mb-6 rounded-2xl border border-white/10 bg-white/[0.02] p-4 md:p-5">
                                <p class="text-xs font-medium uppercase tracking-wider text-amber-300/90">任務介紹</p>

                                <div id="w3fa-modal-cover-wrap" class="mt-3 hidden overflow-hidden rounded-2xl border border-white/10 bg-black/30">
                                    <img id="w3fa-modal-cover" src="" alt="" class="max-h-56 w-full object-cover md:max-h-72">
                                </div>
                                <p id="w3fa-modal-summary" class="mt-3 text-sm leading-relaxed text-zinc-300"></p>
                                <div id="w3fa-modal-meta" class="mt-3 space-y-1 text-xs text-zinc-500"></div>
                                <div id="w3fa-modal-web3-tags" class="mt-3 flex flex-wrap gap-2"></div>
                                <p id="w3fa-modal-web3-note" class="mt-2 text-[11px] text-zinc-500">此任務不會要求你簽署交易、不會要求私鑰。</p>
                                <div id="w3fa-modal-description" class="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-zinc-200"></div>
                            </div>

                            <div id="w3fa-draft-notice" class="mt-4 hidden rounded-xl border border-sky-400/35 bg-sky-950/50 px-3 py-2.5 text-sm text-sky-100" role="status">
                                <span class="block pr-8">已載入此裝置上未送出的草稿，可繼續編輯後送出。</span>
                                <button type="button" id="w3fa-draft-dismiss" class="mt-2 text-xs font-semibold text-sky-200 underline decoration-sky-400/60 underline-offset-2 hover:text-white">知道了</button>
                            </div>
                            <form id="w3fa-modal-form" method="post" action="./dashboard.php" class="space-y-4">
                                <input type="hidden" name="action" value="complete_task">
                                <input type="hidden" name="task_id" id="w3fa-modal-task-id" value="">

                                <div class="border-t border-white/10 pt-5">
                                    <p class="text-xs font-medium uppercase tracking-wider text-amber-300/90">填寫欄位</p>
                                </div>

                                <div id="w3fa-modal-fields" class="space-y-4"></div>
                                <button type="submit" class="w-full rounded-full bg-amber-300 py-3 text-sm font-semibold text-black transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-200">送出審核</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>

    <template id="admin-field-template">
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

            var form = document.getElementById('admin-task-form');
            var modal = document.getElementById('admin-confirm-modal');
            var cancelBtn = document.getElementById('admin-confirm-cancel');
            var proceedBtn = document.getElementById('admin-confirm-proceed');
            var summaryEl = document.getElementById('admin-confirm-summary');
            if (!form || !modal || !cancelBtn || !proceedBtn || !summaryEl) return;

            function showModal(lines) {
                summaryEl.innerHTML = '';
                lines.forEach(function (t) {
                    var div = document.createElement('div');
                    div.textContent = t;
                    summaryEl.appendChild(div);
                });
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
            }

            function hideModal() {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
            }

            form.addEventListener('submit', function (e) {
                // 避免使用者在「確認送出」後又被攔截
                if (form.dataset.confirmed === '1') return;
                e.preventDefault();
                var title = document.getElementById('title') ? document.getElementById('title').value.trim() : '';
                var rewardXp = document.getElementById('reward_xp') ? document.getElementById('reward_xp').value.trim() : '';
                var startsAt = document.getElementById('starts_at') ? document.getElementById('starts_at').value.trim() : '';
                var endsAt = document.getElementById('ends_at') ? document.getElementById('ends_at').value.trim() : '';
                var maxCompletions = document.getElementById('max_completions') ? document.getElementById('max_completions').value.trim() : '';
                var statusVal = document.getElementById('task_status') ? document.getElementById('task_status').value : '';
                var statusText = statusVal === 'ended' ? '已結束（不可提交）' : '已發布（可依時間與名額開放提交）';
                var maxText = maxCompletions === '' ? '不限名額' : ('上限 ' + maxCompletions);
                var startsText = startsAt ? startsAt.replace('T', ' ') : '';
                var endsText = endsAt ? endsAt.replace('T', ' ') : '';

                var lines = [
                    '任務標題：' + (title || '—'),
                    '獎勵 XP：' + (rewardXp || '0'),
                    '時間：' + (startsText || '—') + ' ～ ' + (endsText || '—'),
                    '名額：' + maxText,
                    '狀態：' + statusText
                ];
                showModal(lines);
            });

            cancelBtn.addEventListener('click', function () {
                hideModal();
            });

            proceedBtn.addEventListener('click', function () {
                form.dataset.confirmed = '1';
                hideModal();
                form.submit();
            });

            var backdrop = document.getElementById('admin-confirm-backdrop');
            if (backdrop) backdrop.addEventListener('click', function () { hideModal(); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    hideModal();
                }
            });
        })();
    </script>
    <?php endif; ?>
    <?php if ($role === 'member'): ?>
    <script>
        (function () {
            var KEY = 'w3fa_task_response_v1';
            var TASKS = <?php echo $memberTasksModalJson; ?>;
            var modal = document.getElementById('w3fa-task-modal');
            var backdrop = document.getElementById('w3fa-task-modal-backdrop');
            var panel = document.getElementById('w3fa-task-modal-panel');
            var modalScroll = document.getElementById('w3fa-modal-scroll');
            var fieldsRoot = document.getElementById('w3fa-modal-fields');
            var form = document.getElementById('w3fa-modal-form');
            var taskIdInput = document.getElementById('w3fa-modal-task-id');
            var titleEl = document.getElementById('w3fa-modal-title');
            var summaryEl = document.getElementById('w3fa-modal-summary');
            var metaEl = document.getElementById('w3fa-modal-meta');
            var web3TagsEl = document.getElementById('w3fa-modal-web3-tags');
            var web3NoteEl = document.getElementById('w3fa-modal-web3-note');
            var descEl = document.getElementById('w3fa-modal-description');
            var coverWrap = document.getElementById('w3fa-modal-cover-wrap');
            var coverImg = document.getElementById('w3fa-modal-cover');
            var closeBtn = document.getElementById('w3fa-task-modal-close');
            var draftNotice = document.getElementById('w3fa-draft-notice');
            var draftDismiss = document.getElementById('w3fa-draft-dismiss');
            var draftTimer = null;
            var openTid = null;
            var lastFocusEl = null;
            var trapHandler = null;

            function readAll() {
                try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; }
            }
            function writeAll(o) {
                try { localStorage.setItem(KEY, JSON.stringify(o)); } catch (e) {}
            }
            function collectDraftData() {
                var data = {};
                if (!fieldsRoot) return data;
                fieldsRoot.querySelectorAll('[name^="response["]').forEach(function (inp) {
                    var m = inp.name.match(/^response\[(.+)\]$/);
                    if (!m) return;
                    if (inp.type === 'checkbox') {
                        data[m[1]] = inp.checked ? '1' : '';
                    } else {
                        data[m[1]] = inp.value;
                    }
                });
                return data;
            }
            function saveDraft() {
                if (!openTid) return;
                var all = readAll();
                all[String(openTid)] = collectDraftData();
                writeAll(all);
            }
            function scheduleDraft() {
                clearTimeout(draftTimer);
                draftTimer = setTimeout(saveDraft, 350);
            }
            function applyDraft(tid) {
                var all = readAll();
                var d = all[String(tid)];
                if (!d || typeof d !== 'object') return false;
                var keys = Object.keys(d);
                if (keys.length === 0) return false;
                keys.forEach(function (k) {
                    var el = fieldsRoot.querySelector('[name="response[' + k + ']"]');
                    if (!el) return;
                    if (el.type === 'checkbox') {
                        el.checked = d[k] === '1' || d[k] === 1 || d[k] === true;
                    } else {
                        el.value = d[k];
                    }
                });
                return true;
            }
            function getModalFocusables() {
                if (!modal || modal.classList.contains('hidden')) return [];
                var sel = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled])';
                return Array.prototype.slice.call(modal.querySelectorAll(sel)).filter(function (el) {
                    return el.offsetParent !== null || (el.getClientRects && el.getClientRects().length > 0);
                });
            }
            function buildFields(task) {
                fieldsRoot.innerHTML = '';
                var schema = task.schema || [];
                if (schema.length === 0) {
                    var p = document.createElement('p');
                    p.className = 'text-sm text-zinc-500';
                    p.textContent = '此任務無額外欄位，請直接送出審核。';
                    fieldsRoot.appendChild(p);
                    return;
                }
                schema.forEach(function (field) {
                    var k = field.key;
                    if (!k) return;
                    var req = !!field.required;
                    var wrap = document.createElement('div');
                    var lid = 'w3fa-f-' + task.id + '-' + k;
                    var label = document.createElement('label');
                    label.className = 'mb-1.5 block text-sm font-medium text-zinc-300';
                    label.setAttribute('for', lid);
                    label.textContent = field.label || k;
                    wrap.appendChild(label);
                    var input;
                    if (field.type === 'textarea') {
                        input = document.createElement('textarea');
                        input.rows = 4;
                        input.className = 'w-full rounded-xl border border-white/15 bg-white/[0.06] px-3 py-2.5 text-sm text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50';
                    } else if (field.type === 'checkbox') {
                        var row = document.createElement('div');
                        row.className = 'flex items-start gap-3 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-3';
                        input = document.createElement('input');
                        input.type = 'checkbox';
                        input.value = '1';
                        input.className = 'mt-1 h-4 w-4 shrink-0 rounded border-white/30 bg-black/40 text-amber-300 focus:ring-amber-300/50';
                        if (req) input.required = true;
                        var span = document.createElement('span');
                        span.className = 'text-sm text-zinc-300';
                        span.textContent = field.label || k;
                        label.remove();
                        row.appendChild(input);
                        row.appendChild(span);
                        wrap.appendChild(row);
                        var helper = document.createElement('p');
                        helper.className = 'mt-2 text-xs text-zinc-500';
                        helper.textContent = '勾選代表同意活動條款';
                        wrap.appendChild(helper);
                        input.id = lid;
                        input.name = 'response[' + k + ']';
                        fieldsRoot.appendChild(wrap);
                        return;
                    } else {
                        input = document.createElement('input');
                        input.type = field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'text';
                        input.className = 'w-full rounded-xl border border-white/15 bg-white/[0.06] px-3 py-2.5 text-sm text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50';
                    }
                    input.id = lid;
                    input.name = 'response[' + k + ']';
                    if (req && field.type !== 'checkbox') input.required = true;
                    wrap.appendChild(input);
                    fieldsRoot.appendChild(wrap);
                });
            }
            function openModal(tid) {
                var task = TASKS[String(tid)];
                if (!task) return;
                openTid = tid;
                taskIdInput.value = String(tid);
                titleEl.textContent = task.title || '';
                summaryEl.textContent = task.summary || '';
                metaEl.innerHTML = '';
                var m1 = document.createElement('p');
                m1.textContent = '開放時間（台灣）：' + (task.timeLine || '');
                var m2 = document.createElement('p');
                m2.className = 'text-amber-200/90';
                m2.textContent = task.quotaLine || '';
                var m3 = document.createElement('p');
                m3.className = 'text-zinc-400';
                m3.textContent = '獎勵 +' + (task.reward_xp || 0) + ' XP';
                metaEl.appendChild(m1);
                metaEl.appendChild(m2);
                metaEl.appendChild(m3);

                if (web3TagsEl) {
                    web3TagsEl.innerHTML = '';
                    var w = task.web3 || {};
                    var walletTag = w.wallet_input ? '錢包連接：不需要（可能需填地址）' : '錢包連接：不需要';
                    var onchainTag = 'On-chain：' + (w.onchain ? '需要' : '不需要');
                    var kycTag = 'KYC：' + (w.kyc ? '需要' : '不需要');

                    function chip(txt) {
                        var s = document.createElement('span');
                        s.className = 'rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-[11px] text-zinc-200';
                        s.textContent = txt;
                        return s;
                    }
                    web3TagsEl.appendChild(chip(walletTag));
                    web3TagsEl.appendChild(chip(onchainTag));
                    web3TagsEl.appendChild(chip(kycTag));
                }
                if (web3NoteEl) {
                    web3NoteEl.textContent = '此任務不會要求你簽署交易、不會要求私鑰。';
                }

                descEl.textContent = task.description || '';
                if (task.cover && /^https?:\/\//i.test(task.cover)) {
                    coverImg.src = task.cover;
                    coverImg.alt = task.title || '封面';
                    coverWrap.classList.remove('hidden');
                    coverImg.onerror = function () { coverWrap.classList.add('hidden'); };
                } else {
                    coverWrap.classList.add('hidden');
                    coverImg.removeAttribute('src');
                }
                buildFields(task);
                var hadDraft = applyDraft(tid);
                if (draftNotice) {
                    if (hadDraft) draftNotice.classList.remove('hidden');
                    else draftNotice.classList.add('hidden');
                }
                lastFocusEl = document.activeElement;
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('w3fa-modal-open');
                trapHandler = function (e) {
                    if (e.key !== 'Tab') return;
                    var list = getModalFocusables();
                    if (list.length === 0) return;
                    var first = list[0];
                    var last = list[list.length - 1];
                    if (e.shiftKey) {
                        if (document.activeElement === first) {
                            e.preventDefault();
                            last.focus();
                        }
                    } else {
                        if (document.activeElement === last) {
                            e.preventDefault();
                            first.focus();
                        }
                    }
                };
                modal.addEventListener('keydown', trapHandler);
                setTimeout(function () {
                    var f = fieldsRoot.querySelector('input, textarea, button');
                    if (f) f.focus();
                }, 100);
            }
            function closeModal() {
                saveDraft();
                if (trapHandler && modal) modal.removeEventListener('keydown', trapHandler);
                trapHandler = null;
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('w3fa-modal-open');
                openTid = null;
                if (lastFocusEl && typeof lastFocusEl.focus === 'function') {
                    try { lastFocusEl.focus(); } catch (err) {}
                }
                lastFocusEl = null;
            }
            document.querySelectorAll('.w3fa-clear-task-ls[data-task-id]').forEach(function (el) {
                var tid = el.getAttribute('data-task-id');
                if (!tid) return;
                var all = readAll();
                delete all[tid];
                writeAll(all);
            });
            document.querySelectorAll('.w3fa-open-task-modal[data-task-id]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var tid = btn.getAttribute('data-task-id');
                    if (tid) openModal(tid);
                });
            });
            if (backdrop) backdrop.addEventListener('click', function () { closeModal(); });
            if (closeBtn) closeBtn.addEventListener('click', function () { closeModal(); });
            if (panel) panel.addEventListener('click', function (e) { e.stopPropagation(); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
            if (fieldsRoot) {
                fieldsRoot.addEventListener('input', scheduleDraft);
                fieldsRoot.addEventListener('change', scheduleDraft);
            }
            if (form) {
                form.addEventListener('submit', function () {
                    saveDraft();
                });
            }
            if (draftDismiss && draftNotice) {
                draftDismiss.addEventListener('click', function () {
                    draftNotice.classList.add('hidden');
                });
            }
            if (location.hash && /^#task-\d+$/.test(location.hash)) {
                requestAnimationFrame(function () {
                    var card = document.querySelector(location.hash);
                    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    var errEl = document.getElementById('member-task-error-alert');
                    if (errEl) errEl.focus();
                });
            }
        })();
    </script>
    <?php endif; ?>
</body>
</html>
