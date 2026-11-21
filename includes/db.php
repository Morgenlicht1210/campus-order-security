<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function db(bool $withDatabase = true): PDO
{
    static $pdo = null;
    static $serverPdo = null;
    $cfg = config('db');
    $database = $withDatabase ? ';dbname=' . $cfg['name'] : '';
    $dsn = sprintf(
        'mysql:host=%s;port=%s%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $database,
        $cfg['charset']
    );

    if ($withDatabase && $pdo instanceof PDO) {
        return $pdo;
    }
    if (!$withDatabase && $serverPdo instanceof PDO) {
        return $serverPdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $connection = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
    if ($withDatabase) {
        $pdo = $connection;
    } else {
        $serverPdo = $connection;
    }
    return $connection;
}
