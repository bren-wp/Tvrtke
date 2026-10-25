<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Tvrtke\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($path)) require $path;
    }
});

// Optional local secrets file is ignored by Git. Prefer environment variables in production.
$local = dirname(__DIR__) . '/config.local.php';
if (is_file($local)) require $local;
