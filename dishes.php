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
            (int) ($_POST['category_id'] ?? 0),
            (float) ($_POST['price'] ?? 0),
            (int) ($_POST['stock'] ?? 0),
            $_POST['status'] === 'off' ? 'off' : 'on',
            trim($_POST['description'] ?? ''),
        ];
        if (!empty($_POST['id'])) {
            $stmt = $pdo->prepare('UPDATE dishes SET name=?, category_id=?, price=?, stock=?, status=?, description=? WHERE id=?');
            $stmt->execute(array_merge($data, [(int) $_POST['id']]));
            $message = '菜品已更新。';
        } else {
            $stmt = $pdo->prepare('INSERT INTO dishes (name, category_id, price, stock, status, description) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute($data);
            $message = '菜品已新增。';
        }
    }
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM dishes WHERE id = ?');
        try {
            $stmt->execute([(int) $_POST['id']]);
            $message = '菜品已删除。';
        } catch (Throwable $e) {
            $message = '该菜品已有订单明细关联，不能直接删除，可改为下架。';
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM dishes WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$dishes = $pdo->query(
    'SELECT d.*, c.name AS category_name
     FROM dishes d
     JOIN categories c ON c.id = d.category_id
     ORDER BY d.id DESC'
)->fetchAll();
$title = '菜品管理';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div>
        <h1>菜品管理</h1>
        <p>实现新增、查询、修改、删除四类核心数据库操作，并维护菜品与分类的关联。</p>
    </div>
</section>
<?php if ($message): ?><div class="alert info"><?= e($message) ?></div><?php endif; ?>
<section class="grid two">
    <div class="panel">
        <div class="panel-head"><h2>菜品列表</h2><span class="badge info"><?= count($dishes) ?> 条</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>名称</th><th>分类</th><th>价格</th><th>库存</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($dishes as $dish): ?>
                    <tr>
                        <td><strong><?= e($dish['name']) ?></strong><br><span class="muted"><?= e($dish['description']) ?></span></td>
                        <td><?= e($dish['category_name']) ?></td>
                        <td><?= money($dish['price']) ?></td>
                        <td><?= (int) $dish['stock'] ?></td>
                        <td><span class="badge <?= $dish['status'] === 'on' ? 'success' : 'muted' ?>"><?= $dish['status'] === 'on' ? '上架' : '下架' ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="btn small" href="dishes.php?edit=<?= (int) $dish['id'] ?>">编辑</a>
                                <form method="post" onsubmit="return confirm('确认删除该菜品？');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $dish['id'] ?>">
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
        <div class="panel-head"><h2><?= $edit ? '编辑菜品' : '新增菜品' ?></h2></div>
        <div class="panel-body">
            <form method="post" class="form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
                <label>菜品名称<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
                <label>分类
                    <select name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int)($edit['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>价格<input name="price" type="number" step="0.01" min="0" required value="<?= e((string)($edit['price'] ?? '')) ?>"></label>
                <label>库存<input name="stock" type="number" min="0" required value="<?= e((string)($edit['stock'] ?? '')) ?>"></label>
                <label>状态
                    <select name="status">
                        <option value="on" <?= ($edit['status'] ?? '') === 'on' ? 'selected' : '' ?>>上架</option>
                        <option value="off" <?= ($edit['status'] ?? '') === 'off' ? 'selected' : '' ?>>下架</option>
                    </select>
                </label>
                <label>描述<textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></label>
                <button class="btn primary" type="submit">保存菜品</button>
            </form>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
