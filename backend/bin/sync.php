<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

try {
    echo (new Tvrtke\Sync(Tvrtke\Database::connect(), new Tvrtke\SudregClient()))->tick() . PHP_EOL;
} catch (Throwable $e) {
    error_log('TVRTKE cron: ' . $e->getMessage());
    fwrite(STDERR, 'Greška pri sinkronizaciji. Provjerite privatni PHP error log.' . PHP_EOL);
    exit(1);
}
