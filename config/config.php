<?php
declare(strict_types=1);

/**
 * 数据库与安全参数配置
 *
 * 默认值对应本地 phpStudy 环境（MySQL 端口 3307，账号 root/root）。
 * 如需覆盖，可通过环境变量设置，避免把真实口令写进代码：
 *   DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS
 *   DOS_WINDOW_SECONDS / DOS_MAX_REQUESTS
 *
 * ⚠️ 本项目仅用于本地教学实验，请勿用于生产环境。
 */
$get = static function (string $key, string $default): string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

return [
    'app_name' => '校园订餐管理系统',
    'db' => [
        'host' => $get('DB_HOST', '127.0.0.1'),
        'port' => $get('DB_PORT', '3307'),
        'name' => $get('DB_NAME', 'campus_order_security'),
        'user' => $get('DB_USER', 'root'),
        'pass' => $get('DB_PASS', 'root'),
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'dos_window_seconds' => (int) $get('DOS_WINDOW_SECONDS', '60'),
        'dos_max_requests' => (int) $get('DOS_MAX_REQUESTS', '6'),
    ],
];
