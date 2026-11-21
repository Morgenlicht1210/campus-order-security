<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'status') {
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$_POST['status'] ?? 'pending', (int) $_POST['id']]);
        $message = '订单状态已更新。';
    }
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $message = '订单及其订单明细、支付记录已删除。';
    }
    if ($action === 'create') {
        $customerId = (int) $_POST['customer_id'];
        $dishId = (int) $_POST['dish_id'];
        $quantity = max(1, (int) $_POST['quantity']);

        $customerStmt = $pdo->prepare('SELECT a.id AS address_id FROM addresses a WHERE a.customer_id = ? ORDER BY a.is_default DESC, a.id LIMIT 1');
        $customerStmt->execute([$customerId]);
        $address = $customerStmt->fetch();

        $dishStmt = $pdo->prepare('SELECT price FROM dishes WHERE id = ? AND status = "on"');
        $dishStmt->execute([$dishId]);
        $dish = $dishStmt->fetch();

        if ($address && $dish) {
            $pdo->beginTransaction();
            $orderNo = 'CO' . date('YmdHis') . random_int(10, 99);
            $total = (float) $dish['price'] * $quantity;
            $order = $pdo->prepare('INSERT INTO orders (customer_id, address_id, user_id, order_no, status, total_amount, remark) VALUES (?, ?, ?, ?, "pending", ?, ?)');
            $order->execute([$customerId, (int) $address['address_id'], user()['id'], $orderNo, $total, trim($_POST['remark'] ?? '')]);
            $orderId = (int) $pdo->lastInsertId();
            $item = $pdo->prepare('INSERT INTO order_items (order_id, dish_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $item->execute([$orderId, $dishId, $quantity, (float) $dish['price']]);
            $payment = $pdo->prepare('INSERT INTO payments (order_id, method, amount, status) VALUES (?, "campus_card", ?, "unpaid")');
            $payment->execute([$orderId, $total]);
            $stock = $pdo->prepare('UPDATE dishes SET stock = GREATEST(stock - ?, 0) WHERE id = ?');
            $stock->execute([$quantity, $dishId]);
            $pdo->commit();
            $message = '订单已创建，并同步写入订单明细、支付表和库存。';
        } else {
            $message = '客户地址或上架菜品不存在。';
        }
    }
}

$orders = $pdo->query(
    'SELECT o.*, c.name AS customer_name, a.campus, a.building, a.room, p.method, p.status AS pay_status
     FROM orders o
     JOIN customers c ON c.id = o.customer_id
     JOIN addresses a ON a.id = o.address_id
     LEFT JOIN payments p ON p.order_id = o.id
     ORDER BY o.created_at DESC'
)->fetchAll();
$customers = $pdo->query('SELECT id, name, phone FROM customers ORDER BY id')->fetchAll();
$dishes = $pdo->query('SELECT id, name, price FROM dishes WHERE status = "on" ORDER BY id')->fetchAll();
$title = '订单管理';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div>
        <h1>订单管理</h1>
        <p>订单创建使用事务，写入 orders、order_items、payments 并同步扣减 dishes 库存。</p>
    </div>
</section>
<?php if ($message): ?><div class="alert info"><?= e($message) ?></div><?php endif; ?>
<section class="grid two">
    <div class="panel">
        <div class="panel-head"><h2>订单列表</h2><span class="badge info"><?= count($orders) ?> 单</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>订单</th><th>客户</th><th>地址</th><th>金额</th><th>状态</th><th>支付</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?= e($order['order_no']) ?></strong><br><span class="muted"><?= e($order['created_at']) ?></span></td>
                        <td><?= e($order['customer_name']) ?></td>
                        <td><?= e($order['campus'] . ' ' . $order['building'] . ' ' . $order['room']) ?></td>
                        <td><?= money($order['total_amount']) ?></td>
                        <td><span class="badge <?= badge_class($order['status']) ?>"><?= e(status_text($order['status'])) ?></span></td>
                        <td><?= e($order['method'] ?? '-') ?> / <?= e($order['pay_status'] ?? '-') ?></td>
                        <td>
                            <div class="actions">
                                <form method="post" class="actions">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                    <select name="status">
                                        <?php foreach (['pending','paid','preparing','delivering','completed','cancelled'] as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e(status_text($status)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn small" type="submit">更新</button>
                                </form>
                                <form method="post" onsubmit="return confirm('确认删除该订单？');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                    <button class="btn small danger" type="submit">删除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <aside class="panel">
        <div class="panel-head"><h2>创建订单</h2></div>
        <div class="panel-body">
            <form method="post" class="form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                <label>客户
                    <select name="customer_id" required>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= (int) $customer['id'] ?>"><?= e($customer['name'] . ' / ' . $customer['phone']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>菜品
                    <select name="dish_id" required>
                        <?php foreach ($dishes as $dish): ?>
                            <option value="<?= (int) $dish['id'] ?>"><?= e($dish['name']) ?> - <?= money($dish['price']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>数量<input name="quantity" type="number" min="1" value="1" required></label>
                <label>备注<textarea name="remark" placeholder="例如：少辣、下课后送达"></textarea></label>
                <button class="btn primary" type="submit">生成订单</button>
            </form>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
