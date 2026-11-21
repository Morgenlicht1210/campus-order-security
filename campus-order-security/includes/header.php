<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$scriptPolicy = !empty($allowUnsafeInlineScript) ? "script-src 'self' 'unsafe-inline';" : "script-src 'self';";
header("Content-Security-Policy: default-src 'self'; {$scriptPolicy} style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-src 'self'; base-uri 'self'; form-action 'self'");
$appName = config('app_name');
$nav = [
    'dashboard.php' => '系统首页',
    'dishes.php' => '菜品管理',
    'customers.php' => '客户管理',
    'orders.php' => '订单管理',
    'feedback.php' => '留言反馈',
];
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $appName) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="dashboard.php">
        <span class="brand-mark">食</span>
        <span><?= e($appName) ?></span>
    </a>
    <nav class="nav">
        <?php foreach ($nav as $href => $label): ?>
            <a class="<?= current_path() === $href ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="userbar">
        <span><?= e(user()['display_name'] ?? '') ?></span>
        <a href="logout.php">退出</a>
    </div>
</header>
<main class="shell">
