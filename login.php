<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

redirect('admin/');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attempt_login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        redirect('dashboard.php');
    }
    $error = '账号或密码错误，请确认已运行 setup.php 初始化数据库。';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登录 - <?= e(config('app_name')) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<section class="login-card">
    <div class="brand login-brand">
        <span class="brand-mark">食</span>
        <span><?= e(config('app_name')) ?></span>
    </div>
    <h1>课程设计演示入口</h1>
    <p>默认账号：<strong>admin</strong>，默认密码：<strong>admin123</strong></p>
    <?php if ($error): ?>
        <div class="alert danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form">
        <label>用户名
            <input name="username" value="admin" autocomplete="username" required>
        </label>
        <label>密码
            <input type="password" name="password" value="admin123" autocomplete="current-password" required>
        </label>
        <button class="btn primary" type="submit">登录系统</button>
    </form>
    <a class="setup-link" href="setup.php">首次运行：初始化数据库</a>
</section>
</body>
</html>
