<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM comments WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $message = '留言已删除。';
    }
}

$comments = $pdo->query(
    'SELECT cm.*, c.name AS customer_name, c.phone, d.name AS dish_name
     FROM comments cm
     JOIN customers c ON c.id = cm.customer_id
     JOIN dishes d ON d.id = cm.dish_id
     ORDER BY cm.created_at DESC'
)->fetchAll();
$title = '留言管理';
$allowUnsafeInlineScript = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div>
        <h1>留言管理</h1>
        <p>查看学生提交的订餐备注、评价和配送建议。</p>
    </div>
</section>
<?php if ($message): ?><div class="alert info"><?= e($message) ?></div><?php endif; ?>

<section class="panel">
    <div class="panel-head"><h2>学生留言</h2><span class="badge info"><?= count($comments) ?> 条</span></div>
    <div class="panel-body lab-list">
        <?php foreach ($comments as $comment): ?>
            <div class="lab-item">
                <div class="actions" style="justify-content: space-between;">
                    <strong><?= e($comment['customer_name']) ?> / <?= e($comment['dish_name']) ?></strong>
                    <form method="post" onsubmit="return confirm('确认删除这条留言？');">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $comment['id'] ?>">
                        <button class="btn small danger" type="submit">删除</button>
                    </form>
                </div>
                <span class="muted"><?= e($comment['phone']) ?> / <?= e($comment['created_at']) ?></span>
                <div><?php echo $comment['content']; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
