<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

$message = '';
$error = '';

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inString = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($char === "'" && $prev !== '\\') {
            $inString = !$inString;
        }

        if ($char === ';' && !$inString) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }
    return $statements;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cfg = config('db');
        $server = db(false);
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        foreach (split_sql_statements($sql) as $statement) {
            $server->exec($statement);
        }

        $pdo = db(true);
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, demo_password = ? WHERE username = ?');
        $stmt->execute([$hash, 'admin123', 'admin']);
        $message = '数据库初始化完成，可以使用 admin / admin123 登录。';
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (strpos($error, 'authentication method unknown') !== false || strpos($error, 'Access denied') !== false) {
            $error .= '。当前项目配置为 127.0.0.1:3307，root/root。请确认 phpStudy MySQL8 已启动，且 root 密码为 root。';
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>初始化数据库</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<section class="login-card">
    <div class="brand login-brand">
        <span class="brand-mark">食</span>
        <span>初始化数据库</span>
    </div>
    <p>当前配置：<?= e(config('db.user')) ?>@<?= e(config('db.host')) ?>:<?= e(config('db.port')) ?> / <?= e(config('db.name')) ?></p>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form">
        <button class="btn primary" type="submit">创建数据库并导入示例数据</button>
    </form>
    <a class="setup-link" href="admin/">返回管理员登录</a>
</section>
</body>
</html>
