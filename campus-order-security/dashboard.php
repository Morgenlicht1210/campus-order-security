<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$title = '系统首页';
$pdo = db();
$stats = [
    'orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'dishes' => (int) $pdo->query('SELECT COUNT(*) FROM dishes')->fetchColumn(),
    'customers' => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
    'comments' => (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
];
$recentOrders = $pdo->query(
    'SELECT o.*, c.name AS customer_name, c.phone
     FROM orders o
     JOIN customers c ON c.id = o.customer_id
     ORDER BY o.created_at DESC
     LIMIT 8'
)->fetchAll();
$latestComments = $pdo->query(
    'SELECT cm.*, c.name AS customer_name, d.name AS dish_name
     FROM comments cm
     JOIN customers c ON c.id = cm.customer_id
     JOIN dishes d ON d.id = cm.dish_id
     ORDER BY cm.created_at DESC
     LIMIT 5'
)->fetchAll();

$allowUnsafeInlineScript = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div>
        <h1>校园订餐业务管理系统</h1>
        <p>面向校园订餐场景，完成菜品、客户、订单、支付和学生留言的一体化管理。</p>
    </div>
    <a class="btn primary" href="orders.php">处理订单</a>
</section>

<section class="grid stats">
    <div class="card"><div class="stat-label">订单总数</div><div class="stat-value"><?= $stats['orders'] ?></div><div class="stat-note">含待处理、配送、完成</div></div>
    <div class="card"><div class="stat-label">菜品数量</div><div class="stat-value"><?= $stats['dishes'] ?></div><div class="stat-note">关联分类与订单明细</div></div>
    <div class="card"><div class="stat-label">学生数量</div><div class="stat-value"><?= $stats['customers'] ?></div><div class="stat-note">关联地址、订单、留言</div></div>
    <div class="card"><div class="stat-label">留言数量</div><div class="stat-value"><?= $stats['comments'] ?></div><div class="stat-note">来自学生注册与反馈</div></div>
</section>

<section class="grid two">
    <div class="panel">
        <div class="panel-head">
            <h2>最近订单</h2>
            <a class="btn small" href="orders.php">管理订单</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>订单号</th><th>学生</th><th>电话</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
                <tbody>
                <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><?= e($order['order_no']) ?></td>
                        <td><?= e($order['customer_name']) ?></td>
                        <td><?= e($order['phone']) ?></td>
                        <td><?= money($order['total_amount']) ?></td>
                        <td><span class="badge <?= badge_class($order['status']) ?>"><?= e(status_text($order['status'])) ?></span></td>
                        <td><?= e($order['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <aside class="panel">
        <div class="panel-head">
            <h2>最新学生留言</h2>
            <a class="btn small" href="feedback.php">查看全部</a>
        </div>
        <div class="panel-body lab-list">
            <?php foreach ($latestComments as $comment): ?>
                <div class="lab-item">
                    <strong><?= e($comment['customer_name']) ?> / <?= e($comment['dish_name']) ?></strong>
                    <span><?php echo $comment['content']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
