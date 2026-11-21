<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (user()) {
    redirect('../dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attempt_login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        redirect('../dashboard.php');
    }
    $error = '账号或密码错误。';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理员登录 - <?= e(config('app_name')) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="login-page">
<section class="login-card">
    <div class="brand login-brand">
        <span class="brand-mark">食</span>
        <span>管理员后台</span>
    </div>
    <h1>管理员登录</h1>
    <?php if ($error): ?>
        <div class="alert danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form">
        <label>用户名
            <input name="username" autocomplete="username" required>
        </label>
        <label>密码
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="btn primary" type="submit">进入后台</button>
    </form>
    <a class="setup-link" href="../register.php">返回学生注册</a>
</section>
</body>
</html>
