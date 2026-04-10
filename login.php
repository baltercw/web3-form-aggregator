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
    $lr = $_SESSION['role'];
    header('Location: ' . ($lr === 'issuer' ? './issuer_portal.php' : './dashboard.php'));
    exit;
}

$error = '';
$registeredNotice = isset($_GET['registered']);

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

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $dest = $user['role'] === 'issuer' ? './issuer_portal.php' : './dashboard.php';
            header('Location: ' . $dest);
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
        .fade-in-up {
            animation: fadeInUp 560ms ease-out both;
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
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute inset-0 bg-[radial-gradient(1000px_circle_at_14%_-15%,rgba(251,191,36,0.20),transparent_58%),radial-gradient(900px_circle_at_86%_0%,rgba(255,255,255,0.05),transparent_62%)]"></div>
        <div class="absolute inset-0 opacity-25 [background-image:linear-gradient(to_right,rgba(255,255,255,0.055)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.055)_1px,transparent_1px)] [background-size:60px_60px]"></div>
    </div>

    <?php
    $shellPage = 'guest_login';
    $shellBreadcrumbs = [
        ['label' => '首頁', 'href' => './index.php'],
        ['label' => '登入', 'href' => null],
    ];
    $shellUser = null;
    require __DIR__ . '/includes/site_header.php';
    ?>

    <main class="mx-auto flex min-h-[calc(100vh-4.5rem)] w-full max-w-6xl items-center justify-center px-4 py-10">
        <section class="fade-in-up w-full max-w-md rounded-3xl border border-white/10 bg-white/[0.04] p-8 shadow-[0_18px_50px_-35px_rgba(0,0,0,0.9)]">
            <div class="mb-8">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">Group 09 / Sign in</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">登入平台</h1>
                <p class="mt-2 text-sm text-zinc-300">管理 Web3 任務與參與進度。</p>
            </div>

            <form method="post" action="./login.php" class="space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-zinc-300" for="username">帳號</label>
                    <input id="username" name="username" type="text" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" placeholder="請輸入帳號" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-zinc-300" for="password">密碼</label>
                    <input id="password" name="password" type="password" class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50" placeholder="請輸入密碼" required>
                </div>
                <button type="submit" class="w-full rounded-full bg-amber-300 py-3 text-sm font-semibold text-black transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-200">
                    登入
                </button>
            </form>

            <div class="mt-6 space-y-1 text-sm text-zinc-400">
                <p>測試帳號：admin / admin123456</p>
                <p>測試帳號：member / member123456</p>
                <p class="pt-2">
                    尚無帳號？
                    <a class="font-medium text-amber-200 transition hover:text-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/60" href="./register.php">前往註冊</a>
                </p>
                <a class="mt-1 inline-block font-medium text-zinc-300 transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40" href="./index.php">返回首頁</a>
            </div>
        </section>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
