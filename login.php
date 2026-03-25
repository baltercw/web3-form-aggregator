<?php
session_start();
require_once './db.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: ./login.php');
    exit;
}

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ./dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = '請輸入帳號與密碼。';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: ./dashboard.php');
            exit;
        }

        $error = '帳號或密碼錯誤。';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 | Web3 Task Aggregator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Inter", sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">
    <main class="min-h-screen flex items-center justify-center px-4 py-10">
        <section class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200 p-8">
            <div class="mb-8">
                <div class="inline-flex items-center justify-center px-4 py-2 rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white text-sm font-semibold">
                    Group 09
                </div>
                <h1 class="mt-4 text-3xl font-extrabold tracking-tight">登入平台</h1>
                <p class="mt-2 text-slate-500">管理 Web3 任務與參與進度。</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-700 text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="./login.php" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2" for="username">帳號</label>
                    <input id="username" name="username" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-400" placeholder="請輸入帳號" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2" for="password">密碼</label>
                    <input id="password" name="password" type="password" class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-400" placeholder="請輸入密碼" required>
                </div>
                <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold py-3 hover:opacity-95 transition">
                    登入
                </button>
            </form>

            <div class="mt-6 text-sm text-slate-500">
                <p>測試帳號：admin / admin123456</p>
                <p>測試帳號：member / member123456</p>
                <a class="inline-block mt-3 text-indigo-600 hover:text-indigo-700 font-medium" href="./index.php">返回首頁</a>
            </div>
        </section>
    </main>
</body>
</html>
