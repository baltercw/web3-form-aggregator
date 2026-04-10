<?php
session_start();
require_once './db.php';

define('REGISTER_PASSWORD_MIN_LEN', 8);

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    $lr = $_SESSION['role'];
    header('Location: ' . ($lr === 'issuer' ? './issuer_portal.php' : './dashboard.php'));
    exit;
}

$error = '';
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameValue = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($usernameValue === '' || $password === '') {
        $error = '請填寫帳號與密碼。';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $usernameValue)) {
        $error = '帳號須為 3～50 個字元，僅可使用英文、數字與底線。';
    } elseif (strlen($password) < REGISTER_PASSWORD_MIN_LEN) {
        $error = '密碼長度至少 ' . REGISTER_PASSWORD_MIN_LEN . ' 個字元。';
    } elseif ($password !== $passwordConfirm) {
        $error = '兩次輸入的密碼不一致。';
    } else {
        $stmt = $conn->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $usernameValue);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $error = '此帳號已被使用，請換一個帳號。';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) {
                $error = '註冊失敗，請稍後再試。';
            } else {
                $role = 'member';
                $insert = $conn->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
                $insert->bind_param('sss', $usernameValue, $hash, $role);
                if ($insert->execute()) {
                    $insert->close();
                    header('Location: ./login.php?registered=1');
                    exit;
                }
                $error = '註冊失敗，請稍後再試。';
                $insert->close();
            }
        }
    }
}

$suppressErrorToast = true;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 | Web3 Task Aggregator</title>
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
    $shellPage = 'guest_register';
    $shellBreadcrumbs = [
        ['label' => '首頁', 'href' => './index.php'],
        ['label' => '註冊', 'href' => null],
    ];
    $shellUser = null;
    require __DIR__ . '/includes/site_header.php';
    ?>

    <main class="mx-auto flex min-h-[calc(100vh-4.5rem)] w-full max-w-6xl items-center justify-center px-4 py-10">
        <section class="fade-in-up w-full max-w-md rounded-3xl border border-white/10 bg-white/[0.04] p-8 shadow-[0_18px_50px_-35px_rgba(0,0,0,0.9)]">
            <div class="mb-8">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">Group 09 · 註冊</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">建立帳號</h1>
                <p class="mt-2 text-sm text-zinc-300">註冊後預設為<strong class="text-zinc-200">會員</strong>，可參與任務。</p>
            </div>

            <div id="register-form-alert" class="mb-4 <?php echo $error === '' ? 'hidden' : ''; ?> rounded-2xl border border-rose-400/35 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" role="alert" tabindex="-1"><?php echo $error !== '' ? htmlspecialchars($error) : ''; ?></div>

            <form id="register-form" method="post" action="./register.php" class="space-y-4" novalidate>
                <div>
                    <label class="mb-2 block text-sm font-medium text-zinc-300" for="username">帳號</label>
                    <input id="username" name="username" type="text" autocomplete="username" maxlength="50"
                        class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50"
                        placeholder="3～50 字元，英文、數字、底線"
                        value="<?php echo htmlspecialchars($usernameValue); ?>"
                        required>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-zinc-300" for="password">密碼</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="<?php echo (int) REGISTER_PASSWORD_MIN_LEN; ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50"
                        placeholder="至少 <?php echo (int) REGISTER_PASSWORD_MIN_LEN; ?> 個字元"
                        required>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-zinc-300" for="password_confirm">確認密碼</label>
                    <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="<?php echo (int) REGISTER_PASSWORD_MIN_LEN; ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/[0.06] px-4 py-3 text-white placeholder:text-zinc-500 focus:border-amber-300/40 focus:outline-none focus:ring-2 focus:ring-amber-300/50"
                        placeholder="再次輸入密碼"
                        required>
                </div>
                <button type="submit" id="register-submit" class="w-full rounded-full bg-amber-300 py-3 text-sm font-semibold text-black transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-200 disabled:cursor-not-allowed disabled:opacity-60">
                    註冊
                </button>
            </form>

            <p class="mt-6 text-sm text-zinc-400">
                已有帳號？
                <a class="font-medium text-amber-200 transition hover:text-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/60" href="./login.php">前往登入</a>
            </p>
        </section>
    </main>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>

    <script>
        (function () {
            var MIN = <?php echo (int) REGISTER_PASSWORD_MIN_LEN; ?>;
            var form = document.getElementById('register-form');
            var usernameInput = document.getElementById('username');
            var alertEl = document.getElementById('register-form-alert');
            var submitBtn = document.getElementById('register-submit');

            function showFormError(msg) {
                alertEl.textContent = msg;
                alertEl.classList.remove('hidden');
                alertEl.focus();
            }

            function hideFormError() {
                alertEl.classList.add('hidden');
                alertEl.textContent = '';
            }

            <?php if ($error !== ''): ?>
            alertEl.focus();
            <?php endif; ?>

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                hideFormError();

                var username = (usernameInput.value || '').trim();
                var password = document.getElementById('password').value || '';
                var passwordConfirm = document.getElementById('password_confirm').value || '';

                if (!username) {
                    showFormError('請輸入帳號。');
                    return;
                }
                if (password.length < MIN) {
                    showFormError('密碼長度至少 ' + MIN + ' 個字元。');
                    return;
                }
                if (password !== passwordConfirm) {
                    showFormError('兩次輸入的密碼不一致。');
                    return;
                }

                submitBtn.disabled = true;
                var fd = new FormData();
                fd.append('username', username);

                fetch('./api/check_username.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.ok) {
                            showFormError('無法檢查帳號，請稍後再試。');
                            submitBtn.disabled = false;
                            return;
                        }
                        if (!data.available) {
                            showFormError('此帳號已被使用，請換一個帳號。');
                            submitBtn.disabled = false;
                            return;
                        }
                        form.submit();
                    })
                    .catch(function () {
                        showFormError('網路錯誤，請稍後再試。');
                        submitBtn.disabled = false;
                    });
            });
        })();
    </script>
</body>
</html>
