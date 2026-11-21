<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

define('DOS_DEFENSE_STATE_FILE', __DIR__ . '/dos_defense_state.txt');

$pdo = db();
$message = '';
$error = '';
$result = null;

function e_local(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function client_key(string $suffix): string
{
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgentHash = substr(hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 16);
    return $clientIp . '|ua:' . $userAgentHash . '|' . $suffix;
}

function check_rate_limit(string $clientKey, int $maxRequests = 5, int $windowSeconds = 10): bool
{
    $pdo = db();
    $windowStart = (int) (floor(time() / $windowSeconds) * $windowSeconds);

    $stmt = $pdo->prepare(
        'INSERT INTO request_limits (client_key, window_start, request_count)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE request_count = request_count + 1'
    );
    $stmt->execute([$clientKey, $windowStart]);

    $stmt = $pdo->prepare(
        'SELECT request_count FROM request_limits WHERE client_key = ? AND window_start = ?'
    );
    $stmt->execute([$clientKey, $windowStart]);
    $count = (int) $stmt->fetchColumn();

    return $count <= $maxRequests;
}

function log_security_event(string $eventType, string $severity, ?string $payload, string $defense): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO security_events (event_type, severity, payload, defense)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$eventType, $severity, $payload, $defense]);
}

function cleanup_rate_limits(int $windowSeconds = 10): void
{
    $pdo = db();
    $cutoff = time() - ($windowSeconds * 6);
    $pdo->prepare('DELETE FROM request_limits WHERE window_start < ?')->execute([$cutoff]);
}

function simulate_expensive_work(int $rounds): float
{
    $start = microtime(true);
    $value = 0;
    for ($i = 0; $i < $rounds; $i++) {
        $value += crc32((string) ($i . '-' . $value));
    }
    return microtime(true) - $start;
}

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function defense_enabled(): bool
{
    if (!is_file(DOS_DEFENSE_STATE_FILE)) {
        return false;
    }

    return trim((string) file_get_contents(DOS_DEFENSE_STATE_FILE)) === '1';
}

function set_defense_enabled(bool $enabled): void
{
    file_put_contents(DOS_DEFENSE_STATE_FILE, $enabled ? '1' : '0', LOCK_EX);
}

function handle_traffic_api(): void
{
    $workRounds = max(1000, min((int) ($_GET['work_rounds'] ?? 45000), 200000));
    $enabled = defense_enabled();

    if (!$enabled) {
        $elapsed = simulate_expensive_work($workRounds);
        log_security_event(
            'dos',
            'medium',
            '脚本访问：status=success，防御关闭，work_rounds=' . $workRounds,
            '防御关闭，请求放行并进入业务处理'
        );
        json_response([
            'mode' => 'traffic',
            'defense_enabled' => false,
            'status' => 'success',
            'blocked' => false,
            'elapsed' => round($elapsed, 4),
            'message' => '防御关闭：请求已进入高消耗处理流程',
        ]);
    }

    $key = client_key('script_traffic');
    if (!check_rate_limit($key, 5, 10)) {
        log_security_event(
            'dos',
            'high',
            '脚本访问：status=blocked，防御开启，返回429',
            '防御开启，滑动窗口限流拦截'
        );
        json_response([
            'mode' => 'traffic',
            'defense_enabled' => true,
            'status' => 'blocked',
            'blocked' => true,
            'elapsed' => 0,
            'message' => '防御开启：超过阈值，返回 429',
        ], 429);
    }

    $elapsed = simulate_expensive_work($workRounds);
    log_security_event(
        'dos',
        'low',
        '脚本访问：status=success，防御开启但未超阈值，work_rounds=' . $workRounds,
        '防御开启，通过限流检查'
    );
    json_response([
        'mode' => 'traffic',
        'defense_enabled' => true,
        'status' => 'success',
        'blocked' => false,
        'elapsed' => round($elapsed, 4),
        'message' => '防御开启：通过限流检查，允许访问',
    ]);
}

function run_unprotected_demo(int $requestCount, int $workRounds): array
{
    $items = [];
    $slowCount = 0;
    $total = 0.0;

    for ($i = 1; $i <= $requestCount; $i++) {
        $elapsed = simulate_expensive_work($workRounds);
        $total += $elapsed;
        if ($elapsed > 0.15) {
            $slowCount++;
        }
        $items[] = [
            'index' => $i,
            'status' => 200,
            'elapsed' => $elapsed,
            'note' => $elapsed > 0.15 ? '响应变慢' : '正常返回',
        ];
    }

    return [
        'mode' => '未防御接口',
        'total' => $requestCount,
        'allowed' => $requestCount,
        'blocked' => 0,
        'slow' => $slowCount,
        'average' => $requestCount > 0 ? $total / $requestCount : 0,
        'items' => $items,
        'summary' => '未加限流时，请求全部进入业务处理；在高频请求下会持续消耗 PHP 和数据库资源，存在 DoS 风险。',
    ];
}

function run_protected_demo(int $requestCount, int $workRounds): array
{
    $items = [];
    $allowed = 0;
    $blocked = 0;
    $total = 0.0;
    $key = client_key('dos_protected');

    for ($i = 1; $i <= $requestCount; $i++) {
        if (!check_rate_limit($key, 5, 10)) {
            $blocked++;
            $items[] = [
                'index' => $i,
                'status' => 429,
                'elapsed' => 0.0,
                'note' => '已被限流拦截',
            ];
            continue;
        }

        $elapsed = simulate_expensive_work($workRounds);
        $total += $elapsed;
        $allowed++;
        $items[] = [
            'index' => $i,
            'status' => 200,
            'elapsed' => $elapsed,
            'note' => '允许处理',
        ];
    }

    if ($blocked > 0) {
        log_security_event(
            'dos',
            'high',
            '本地请求洪泛模拟：总请求 ' . $requestCount . '，拦截 ' . $blocked,
            '滑动窗口限流，超过阈值返回 429 并写入日志'
        );
    }

    return [
        'mode' => '已防御接口',
        'total' => $requestCount,
        'allowed' => $allowed,
        'blocked' => $blocked,
        'slow' => 0,
        'average' => $allowed > 0 ? $total / $allowed : 0,
        'items' => $items,
        'summary' => '加入限流后，超过阈值的请求直接返回 429，不再进入高消耗业务处理，并记录到 request_limits 与 security_events。',
    ];
}

cleanup_rate_limits();

if (($_GET['api'] ?? '') === 'traffic') {
    handle_traffic_api();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['demo_action'] ?? '';

    if ($action === 'clear_logs') {
        $pdo->exec('DELETE FROM security_events');
        $pdo->exec('DELETE FROM request_limits');
        $message = '安全事件日志和频率限制记录已清空。';
    } elseif ($action === 'enable_defense') {
        set_defense_enabled(true);
        $pdo->exec('DELETE FROM request_limits');
        $message = 'DoS 防御已开启：脚本请求超过阈值后会返回 429 并写入日志。';
    } elseif ($action === 'disable_defense') {
        set_defense_enabled(false);
        $pdo->exec('DELETE FROM request_limits');
        $message = 'DoS 防御已关闭：脚本请求将全部放行并记录为成功访问。';
    } else {
        $requestCount = max(1, min((int) ($_POST['request_count'] ?? 20), 80));
        $workRounds = max(1000, min((int) ($_POST['work_rounds'] ?? 45000), 200000));

        if ($action === 'unprotected') {
            $result = run_unprotected_demo($requestCount, $workRounds);
            $message = '未防御 DoS 模拟完成：请求全部进入处理流程。';
        }

        if ($action === 'protected') {
            $result = run_protected_demo($requestCount, $workRounds);
            $message = '已防御 DoS 模拟完成：超阈值请求已被限流拦截。';
        }
    }
}

$rateStatus = $pdo->query(
    'SELECT client_key, window_start, request_count
     FROM request_limits ORDER BY window_start DESC, id DESC LIMIT 20'
)->fetchAll();

$events = $pdo->query(
    'SELECT * FROM security_events ORDER BY created_at DESC, id DESC LIMIT 20'
)->fetchAll();

$title = 'DoS攻击演示与防御';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e_local($title) ?> - 校园订餐系统</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Microsoft YaHei', sans-serif; background: #0f172a; color: #e2e8f0; line-height: 1.7; padding: 2rem; }
        .container { max-width: 1120px; margin: 0 auto; }
        h1 { color: #38bdf8; margin-bottom: 0.5rem; }
        .subtitle { color: #94a3b8; margin-bottom: 2rem; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .card h2 { color: #f8fafc; font-size: 1.1rem; margin-bottom: 1rem; }
        .danger { border-left: 4px solid #ef4444; }
        .success { border-left: 4px solid #22c55e; }
        .warning { border-left: 4px solid #f59e0b; }
        .info { border-left: 4px solid #38bdf8; }
        .muted { color: #94a3b8; font-size: 0.9rem; }
        label { display: block; margin: 0.8rem 0 0.3rem; color: #94a3b8; font-size: 0.9rem; }
        input { width: 100%; padding: 0.6rem; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #e2e8f0; font-size: 0.95rem; }
        button { padding: 0.65rem 1.2rem; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; margin-top: 1rem; }
        .danger-btn { background: #ef4444; }
        .success-btn { background: #16a34a; }
        .plain-btn { background: #334155; color: #e2e8f0; }
        .alert { padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-success { background: rgba(34,197,94,0.15); border: 1px solid #22c55e; color: #86efac; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 1rem; }
        th, td { padding: 0.6rem; text-align: left; border-bottom: 1px solid #334155; vertical-align: top; }
        th { color: #38bdf8; }
        .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; }
        .badge-ok { background: rgba(34,197,94,0.18); color: #86efac; }
        .badge-block { background: rgba(239,68,68,0.18); color: #fca5a5; }
        .badge-high { background: rgba(239,68,68,0.2); color: #fca5a5; }
        .badge-medium { background: rgba(245,158,11,0.2); color: #fcd34d; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 1rem; }
        .stat { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 1rem; }
        .stat strong { display: block; font-size: 1.3rem; color: #f8fafc; }
        @media (max-width: 800px) { .grid, .stats { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <h1>DoS 拒绝服务攻击演示与防御</h1>
    <p class="subtitle">本地授权课程实验：脚本持续发送请求，网页端手动开启/关闭 DoS 防御并记录访问结果。</p>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= e_local($message) ?></div>
    <?php endif; ?>

    <div class="card <?= defense_enabled() ? 'success' : 'danger' ?>">
        <h2>DoS 防御开关</h2>
        <p class="muted">
            当前状态：
            <strong style="color:<?= defense_enabled() ? '#86efac' : '#fca5a5' ?>;">
                <?= defense_enabled() ? '已开启' : '已关闭' ?>
            </strong>
            。Python 脚本始终请求同一个接口 <code>dos_demo.php?api=traffic</code>，是否放行或拦截由这里的开关决定。
        </p>
        <form method="post" style="display:inline-block;margin-right:1rem;">
            <input type="hidden" name="demo_action" value="disable_defense">
            <button type="submit" class="danger-btn">关闭防御：全部放行</button>
        </form>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="demo_action" value="enable_defense">
            <button type="submit" class="success-btn">开启防御：限流拦截</button>
        </form>
    </div>

    <div class="grid">
        <div class="card danger">
            <h2>阶段 A：关闭防御后运行脚本</h2>
            <p class="muted">脚本发送的请求会全部进入高消耗处理流程，并在日志中记录为 success。用于展示未防御时的 DoS 风险。</p>
        </div>

        <div class="card success">
            <h2>阶段 B：开启防御后运行脚本</h2>
            <p class="muted">脚本仍请求同一个接口，但超过 10 秒 5 次后网页端返回 429，并在日志中记录为 blocked。</p>
        </div>
    </div>

    <?php if ($result): ?>
        <div class="card <?= $result['blocked'] > 0 ? 'success' : 'danger' ?>">
            <h2><?= e_local($result['mode']) ?> - 运行结果</h2>
            <p class="muted"><?= e_local($result['summary']) ?></p>
            <div class="stats">
                <div class="stat"><span class="muted">总请求</span><strong><?= (int) $result['total'] ?></strong></div>
                <div class="stat"><span class="muted">允许处理</span><strong><?= (int) $result['allowed'] ?></strong></div>
                <div class="stat"><span class="muted">拦截请求</span><strong><?= (int) $result['blocked'] ?></strong></div>
                <div class="stat"><span class="muted">平均耗时</span><strong><?= number_format((float) $result['average'], 4) ?>s</strong></div>
            </div>
            <table>
                <tr><th>#</th><th>状态</th><th>耗时</th><th>说明</th></tr>
                <?php foreach (array_slice($result['items'], 0, 30) as $item): ?>
                    <tr>
                        <td><?= (int) $item['index'] ?></td>
                        <td><span class="badge <?= (int) $item['status'] === 429 ? 'badge-block' : 'badge-ok' ?>"><?= e_local((string) $item['status']) ?></span></td>
                        <td><?= number_format((float) $item['elapsed'], 4) ?>s</td>
                        <td><?= e_local($item['note']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <div class="card info">
        <h2>防御修改流程</h2>
        <ol style="margin-left:1.5rem;color:#94a3b8;">
            <li>先点击“关闭防御”，运行 Python 脚本，观察请求全部 success。</li>
            <li>再点击“开启防御”，不改脚本，继续发送请求。</li>
            <li>开启后请求进入 <code>check_rate_limit()</code>，超过阈值直接返回 429。</li>
            <li>限流计数写入 <code>request_limits</code>，成功和拦截事件写入 <code>security_events</code>。</li>
        </ol>
    </div>

    <div class="card info">
        <h2>当前频率限制状态 (request_limits)</h2>
        <?php if ($rateStatus): ?>
            <table>
                <tr><th>客户端标识</th><th>窗口开始</th><th>请求数</th></tr>
                <?php foreach ($rateStatus as $row): ?>
                    <tr>
                        <td><?= e_local($row['client_key']) ?></td>
                        <td><?= e_local((string) $row['window_start']) ?></td>
                        <td><?= (int) $row['request_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="muted">暂无频率限制记录。请先执行已防御接口。</p>
        <?php endif; ?>
    </div>

    <div class="card warning">
        <h2>安全事件日志 (security_events)</h2>
        <form method="post" style="margin-bottom:1rem;">
            <input type="hidden" name="demo_action" value="clear_logs">
            <button type="submit" class="plain-btn">清空日志</button>
        </form>
        <?php if ($events): ?>
            <table>
                <tr><th>ID</th><th>类型</th><th>严重级别</th><th>Payload</th><th>防御措施</th><th>时间</th></tr>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= (int) $event['id'] ?></td>
                        <td><?= e_local($event['event_type']) ?></td>
                        <td><span class="badge badge-<?= e_local($event['severity']) ?>"><?= e_local($event['severity']) ?></span></td>
                        <td><?= e_local(mb_substr($event['payload'] ?? '', 0, 80)) ?></td>
                        <td><?= e_local($event['defense']) ?></td>
                        <td><?= e_local($event['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="muted">暂无安全事件记录。请先执行已防御接口并触发限流。</p>
        <?php endif; ?>
    </div>

    <div style="text-align:center;margin-top:2rem;">
        <a href="index.php" style="color:#38bdf8;text-decoration:none;">返回首页</a>
    </div>
</div>
</body>
</html>
