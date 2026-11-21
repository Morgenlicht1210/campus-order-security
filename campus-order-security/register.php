<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (student()) {
    redirect('menu.php');
}

$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $phone = trim($_POST['phone'] ?? '');
        $studentNo = trim($_POST['student_no'] ?? '');
        $sql = "SELECT id, name, phone, student_no FROM customers WHERE phone = '{$phone}' AND student_no = '{$studentNo}' LIMIT 1";
        try {
            $student = $pdo->query($sql)->fetch();
            if ($student) {
                $_SESSION['student'] = [
                    'id' => (int) $student['id'],
                    'name' => $student['name'],
                    'phone' => $student['phone'],
                    'student_no' => $student['student_no'],
                ];
                redirect('menu.php');
            }
            $error = '手机号或学号不匹配，请先注册。';
        } catch (Throwable $e) {
            $error = '登录失败，请检查输入内容。';
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>学生登录 - <?= e(config('app_name')) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="student-page auth-page">
<main class="auth-shell">
    <section class="auth-card auth-card-login">
        <div class="auth-copy">
            <div class="brand">
                <span class="brand-mark">食</span>
                <span>校园订餐系统</span>
            </div>
            <span class="eyebrow">学生入口</span>
            <h1>学生登录</h1>
            <p>输入手机号和学号后直接进入点餐系统，适合课程设计展示的校园订餐流程。</p>
        </div>

        <div class="auth-panel">
            <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>

            <form method="post" class="form auth-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="login">

                <label>手机号
                    <input name="phone" placeholder="13800000001" required>
                </label>

                <label>学号
                    <input name="student_no" placeholder="202483290201" required>
                </label>

                <button class="btn primary wide" type="submit">进入点餐系统</button>
            </form>

            <div class="auth-foot">
                <span>还没有账号？</span>
                <a href="signup.php">去注册</a>
            </div>
        </div>
    </section>
</main>
</body>
</html>
