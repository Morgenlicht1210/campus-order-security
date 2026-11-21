<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_student();

$pdo = db();
$student = student();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $quantities = $_POST['qty'] ?? [];
    $remark = trim($_POST['remark'] ?? '');
    $selected = [];

    foreach ($quantities as $dishId => $quantity) {
        $dishId = (int) $dishId;
        $quantity = (int) $quantity;
        if ($dishId > 0 && $quantity > 0) {
            $selected[$dishId] = min($quantity, 20);
        }
    }

    if (!$selected) {
        $error = '请至少选择一个菜品。';
    } else {
        try {
            $addressStmt = $pdo->prepare('SELECT id FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, id LIMIT 1');
            $addressStmt->execute([$student['id']]);
            $addressId = (int) $addressStmt->fetchColumn();

            if (!$addressId) {
                throw new RuntimeException('请先完善配送地址。');
            }

            $ids = implode(',', array_map('intval', array_keys($selected)));
            $dishRows = $pdo->query("SELECT id, name, price, stock FROM dishes WHERE status = 'on' AND id IN ({$ids})")->fetchAll();
            if (!$dishRows) {
                throw new RuntimeException('选中的菜品不可下单。');
            }

            $pdo->beginTransaction();
            $total = 0;
            foreach ($dishRows as $dish) {
                $total += (float) $dish['price'] * $selected[(int) $dish['id']];
            }

            $orderNo = 'CO' . date('YmdHis') . random_int(10, 99);
            $order = $pdo->prepare('INSERT INTO orders (customer_id, address_id, user_id, order_no, status, total_amount, remark) VALUES (?, ?, NULL, ?, "pending", ?, ?)');
            $order->execute([$student['id'], $addressId, $orderNo, $total, $remark]);
            $orderId = (int) $pdo->lastInsertId();

            $item = $pdo->prepare('INSERT INTO order_items (order_id, dish_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $stock = $pdo->prepare('UPDATE dishes SET stock = GREATEST(stock - ?, 0) WHERE id = ?');
            foreach ($dishRows as $dish) {
                $dishId = (int) $dish['id'];
                $qty = $selected[$dishId];
                $item->execute([$orderId, $dishId, $qty, (float) $dish['price']]);
                $stock->execute([$qty, $dishId]);
            }

            $payment = $pdo->prepare('INSERT INTO payments (order_id, method, amount, status) VALUES (?, "campus_card", ?, "unpaid")');
            $payment->execute([$orderId, $total]);

            if ($remark !== '') {
                $firstDishId = (int) $dishRows[0]['id'];
                $comment = $pdo->prepare('INSERT INTO comments (customer_id, dish_id, content, safe_content) VALUES (?, ?, ?, ?)');
                $comment->execute([$student['id'], $firstDishId, $remark, e($remark)]);
            }

            $pdo->commit();
            $message = '下单成功，订单号：' . $orderNo . '，请等待管理员处理。';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$dishes = $pdo->query(
    'SELECT d.*, c.name AS category_name
     FROM dishes d
     JOIN categories c ON c.id = d.category_id
     WHERE d.status = "on"
     ORDER BY d.category_id, d.id'
)->fetchAll();
$orders = $pdo->prepare(
    'SELECT order_no, status, total_amount, created_at
     FROM orders
     WHERE customer_id = ?
     ORDER BY created_at DESC
     LIMIT 3'
);
$orders->execute([$student['id']]);
$myOrders = $orders->fetchAll();

$statusClasses = [
    'pending' => 'warning',
    'paid' => 'primary',
    'delivered' => 'success',
    'cancelled' => 'danger',
];
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>在线点餐 - <?= e(config('app_name')) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="student-page">
<header class="mini-topbar">
    <div class="mini-topbar-copy">
        <strong>校园订餐系统</strong>
        <span><?= e($student['name']) ?> · 在线点餐</span>
    </div>
    <a class="btn ghost small" href="student_logout.php">退出登录</a>
</header>

<main class="mini-app">
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="menu-layout">
        <aside class="menu-sidebar">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <section class="sidebar-card">
                <div class="sidebar-head">
                    <span class="eyebrow">今日菜单</span>
                    <h2>分类导航</h2>
                </div>
                <nav class="category-rail">
                    <?php foreach ($categories as $index => $category): ?>
                        <a class="<?= $index === 0 ? 'active' : '' ?>" href="#cat-<?= (int) $category['id'] ?>"><?= e($category['name']) ?></a>
                    <?php endforeach; ?>
                </nav>
            </section>

            <section class="sidebar-card order-panel">
                <div class="sidebar-head">
                    <span class="eyebrow">备注信息</span>
                    <h2>订单留言</h2>
                </div>
                <textarea name="remark" placeholder="例如：少辣、不要香菜、送到宿舍楼下"></textarea>
                <div class="order-submit">
                    <span>选好菜品后提交订单</span>
                    <button class="btn primary wide" type="submit">提交订单</button>
                </div>
            </section>
        </aside>

        <section class="menu-main">
            <div class="menu-hero-card">
                <span class="eyebrow">暖心订餐</span>
                <h1>今日想吃点什么？</h1>
                <p>从套餐、主食、饮品和轻食里选择菜品，提交后系统会生成最近订单记录。</p>
            </div>

            <div class="dish-board">
                <?php foreach ($categories as $category): ?>
                    <section class="dish-section" id="cat-<?= (int) $category['id'] ?>">
                        <div class="section-title">
                            <h2><?= e($category['name']) ?></h2>
                            <span class="section-tip">双列菜品卡</span>
                        </div>
                        <div class="dish-list">
                            <?php foreach ($dishes as $dish): ?>
                                <?php if ((int) $dish['category_id'] !== (int) $category['id']) { continue; } ?>
                                <article class="dish-card">
                                    <div class="dish-thumb">食</div>
                                    <div class="dish-copy">
                                        <h3><?= e($dish['name']) ?></h3>
                                        <p><?= e($dish['description']) ?></p>
                                        <div class="dish-meta">
                                            <strong><?= money($dish['price']) ?></strong>
                                            <span>库存 <?= (int) $dish['stock'] ?></span>
                                        </div>
                                    </div>
                                    <div class="qty-box">
                                        <label>份数</label>
                                        <input class="qty-input" type="number" min="0" max="20" name="qty[<?= (int) $dish['id'] ?>]" value="0">
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
    </form>

    <?php if ($myOrders): ?>
        <section class="student-card recent-orders-card">
            <div class="section-title">
                <h2>最近订单</h2>
                <span class="section-tip">最近 3 条</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>订单号</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
                    <tbody>
                    <?php foreach ($myOrders as $order): ?>
                        <tr>
                            <td><?= e($order['order_no']) ?></td>
                            <td><?= money($order['total_amount']) ?></td>
                            <td><span class="badge <?= e($statusClasses[$order['status']] ?? 'warning') ?>"><?= e(status_text($order['status'])) ?></span></td>
                            <td><?= e($order['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
