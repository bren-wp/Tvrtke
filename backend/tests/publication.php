<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Tvrtke\SnapshotPublication;

function expect_rejection(array $rows, string $label): void
{
    try {
        SnapshotPublication::isPartial($rows);
    } catch (RuntimeException $e) {
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $label);
}

$rows = array_map(static fn(string $name): array => [
    'dataset' => $name,
    'finished' => 1,
    'imported_rows' => 100,
    'truncated' => 0,
], SnapshotPublication::DATASETS);
assert(SnapshotPublication::isPartial($rows) === false);
$truncated = $rows;
$truncated[2]['truncated'] = 1;
assert(SnapshotPublication::isPartial($truncated) === true);
$missing = $rows;
array_pop($missing);
expect_rejection($missing, 'missing dataset');
$unfinished = $rows;
$unfinished[1]['finished'] = 0;
expect_rejection($unfinished, 'unfinished');
$empty = $rows;
$empty[0]['imported_rows'] = 0;
expect_rejection($empty, 'empty subjects');
$duplicate = $rows;
$duplicate[4] = $duplicate[0];
expect_rejection($duplicate, 'duplicate dataset');
echo "Publication validation OK\n";
