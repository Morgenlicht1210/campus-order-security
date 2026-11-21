<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (student()) {
    redirect('menu.php');
}

$pdo = db();
$error = '';
$checkRows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $studentNo = trim($_POST['student_no'] ?? '');
        $campus = trim($_POST['campus'] ?? '滨江校区');
        $building = trim($_POST['building'] ?? '');
        $room = trim($_POST['room'] ?? '');

        if ($name === '' || $phone === '' || $studentNo === '') {
            $error = '姓名、手机号和学号不能为空。';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO customers (name, phone, email, student_no) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $phone, $email, $studentNo]);
                $customerId = (int) $pdo->lastInsertId();

                $address = $pdo->prepare('INSERT INTO addresses (customer_id, campus, building, room, is_default) VALUES (?, ?, ?, ?, 1)');
                $address->execute([$customerId, $campus, $building, $room]);
                $pdo->commit();

                $_SESSION['student'] = [
                    'id' => $customerId,
                    'name' => $name,
                    'phone' => $phone,
                    'student_no' => $studentNo,
                ];
                redirect('menu.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = '注册失败：手机号或学号可能已经存在。';
            }
        }
    }

    if ($action === 'check') {
        $keyword = trim($_POST['keyword'] ?? '');
        $sql = "SELECT name, phone, student_no FROM customers WHERE phone = '{$keyword}' OR student_no = '{$keyword}'";
        try {
            $checkRows = $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) {
            $error = '查询失败，请检查输入内容。';
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>学生注册 - <?= e(config('app_name')) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="student-page auth-page">
<main class="auth-shell auth-shell-wide">
    <section class="auth-card auth-card-signup">
        <div class="auth-card-top">
            <div>
                <div class="brand">
                    <span class="brand-mark">食</span>
                    <span>校园订餐系统</span>
                </div>
                <span class="eyebrow">新生注册</span>
                <h1>学生注册</h1>
            </div>
            <a class="btn ghost" href="register.php">返回登录</a>
        </div>

        <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>

        <form method="post" class="form grid-form auth-grid-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="register">

            <label>姓名
                <input name="name" required placeholder="例如：张三">
            </label>

            <label>手机号
                <input name="phone" required placeholder="13800000000">
            </label>

            <label>学号
                <input name="student_no" required placeholder="202483290201">
            </label>

            <label>邮箱
                <input type="email" name="email" placeholder="name@example.com">
            </label>

            <label>校区
                <input name="campus" value="滨江校区">
            </label>

            <label>楼栋
                <input name="building" placeholder="学生公寓 3 栋">
            </label>

            <label class="full">房间
                <input name="room" placeholder="512">
            </label>

            <div class="full auth-submit-row">
                <button class="btn primary wide" type="submit">注册并进入点餐</button>
            </div>
        </form>

        <section class="aux-card">
            <div class="aux-head">
                <div>
                    <h2>查询注册信息</h2>
                    <p>保留原有查询功能，便于核对已登记学生资料。</p>
                </div>
            </div>

            <form method="post" class="form aux-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="check">
                <label>手机号或学号
                    <input name="keyword" value="<?= e($_POST['keyword'] ?? '') ?>" placeholder="输入手机号或学号">
                </label>
                <button class="btn ghost" type="submit">查询</button>
            </form>

            <?php if ($checkRows): ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>姓名</th><th>手机号</th><th>学号</th></tr></thead>
                        <tbody>
                        <?php foreach ($checkRows as $row): ?>
                            <tr>
                                <td><?= e($row['name'] ?? '') ?></td>
                                <td><?= e($row['phone'] ?? '') ?></td>
                                <td><?= e($row['student_no'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </section>
</main>
</body>
</html>
