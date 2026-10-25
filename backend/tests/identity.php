<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

use Tvrtke\Identity;

function check(bool $pass, string $message): void {
    if (!$pass) throw new RuntimeException('Failed: ' . $message);
}
check(Identity::mbs(8000014) === '008000014', 'MBS padding');
check(Identity::oib(123456789) === '00123456789', 'OIB padding');
check(Identity::oib(null) === null, 'null OIB');
check(Identity::date('2024-04-11T11:00:00') === '2024-04-11', 'date');
check(Identity::date('2024-02-30') === null, 'invalid date');
try { Identity::mbs('1 OR 1=1'); throw new RuntimeException('Must reject invalid MBS'); }
catch (InvalidArgumentException $e) {}
echo "Identity tests OK\n";
