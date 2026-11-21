<?php
declare(strict_types=1);

function config($key = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }
    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function current_path(): string
{
    return basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
}

function money($value): string
{
    return '￥' . number_format((float) $value, 2);
}

function status_text(string $status): string
{
    return [
        'pending' => '待处理',
        'paid' => '已支付',
        'preparing' => '备餐中',
        'delivering' => '配送中',
        'completed' => '已完成',
        'cancelled' => '已取消',
    ][$status] ?? $status;
}

function badge_class(string $status): string
{
    return [
        'pending' => 'warning',
        'paid' => 'info',
        'preparing' => 'info',
        'delivering' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
        'blocked' => 'danger',
        'safe' => 'success',
    ][$status] ?? 'muted';
}
