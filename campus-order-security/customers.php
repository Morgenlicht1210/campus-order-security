<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();
$message = '';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $data = [
            trim($_POST['name'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['student_no'] ?? ''),
        ];
        if (!empty($_POST['id'])) {
            $stmt = $pdo->prepare('UPDATE customers SET name=?, phone=?, email=?, student_no=? WHERE id=?');
            $stmt->execute(array_merge($data, [(int) $_POST['id']]));
            $message = '客户已更新。';
        } else {
            $stmt = $pdo->prepare('INSERT INTO customers (name, phone, email, student_no) VALUES (?, ?, ?, ?)');
            $stmt->execute($data);
            $customerId = (int) $pdo->lastInsertId();
            $address = $pdo->prepare('INSERT INTO addresses (customer_id, campus, building, room, is_default) VALUES (?, ?, ?, ?, 1)');
            $address->execute([$customerId, trim($_POST['campus'] ?? '滨江校区'), trim($_POST['building'] ?? '待补充'), trim($_POST['room'] ?? '待补充')]);
            $message = '客户及默认地址已新增。';
        }
    }
    if ($action === 'delete') {
        try {
            $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ?');
            $stmt->execute([(int) $_POST['id']]);
            $message = '客户已删除。';
        } catch (Throwable $e) {
            $message = '该客户已有订单关联，不能直接删除。';
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$customers = $pdo->query(
    'SELECT c.*, COUNT(o.id) AS order_count, MIN(CONCAT(a.campus, a.building, a.room)) AS address_text
     FROM customers c
     LEFT JOIN addresses a ON a.customer_id = c.id
     LEFT JOIN orders o ON o.customer_id = c.id
     GROUP BY c.id
     ORDER BY c.id DESC'
)->fetchAll();
$title = '客户管理';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div>
        <h1>客户管理</h1>
        <p>维护订餐客户、学号、联系方式和默认配送地址，体现客户、地址、订单之间的表关联。</p>
    </div>
</section>
<?php if ($message): ?><div class="alert info"><?= e($message) ?></div><?php endif; ?>
<section class="grid two">
    <div class="panel">
        <div class="panel-head"><h2>客户列表</h2><span class="badge info"><?= count($customers) ?> 人</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>姓名</th><th>电话</th><th>学号</th><th>默认地址</th><th>订单数</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><strong><?= e($customer['name']) ?></strong><br><span class="muted"><?= e($customer['email']) ?></span></td>
                        <td><?= e($customer['phone']) ?></td>
                        <td><?= e($customer['student_no']) ?></td>
                        <td><?= e($customer['address_text']) ?></td>
                        <td><?= (int) $customer['order_count'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="btn small" href="customers.php?edit=<?= (int) $customer['id'] ?>">编辑</a>
                                <form method="post" onsubmit="return confirm('确认删除该客户？');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
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
        <div class="panel-head"><h2><?= $edit ? '编辑客户' : '新增客户' ?></h2></div>
        <div class="panel-body">
            <form method="post" class="form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
                <label>姓名<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
                <label>电话<input name="phone" required value="<?= e($edit['phone'] ?? '') ?>"></label>
                <label>邮箱<input name="email" type="email" value="<?= e($edit['email'] ?? '') ?>"></label>
                <label>学号<input name="student_no" value="<?= e($edit['student_no'] ?? '') ?>"></label>
                <?php if (!$edit): ?>
                    <label>校区<input name="campus" value="滨江校区"></label>
                    <label>楼栋<input name="building" value="学生公寓 1 栋"></label>
                    <label>房间<input name="room" value="101"></label>
                <?php endif; ?>
                <button class="btn primary" type="submit">保存客户</button>
            </form>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
