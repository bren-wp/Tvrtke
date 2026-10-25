<?php
declare(strict_types=1);

namespace Tvrtke;

/** Pure validation of staging completeness, independent from MySQL and the Sudreg API. */
final class SnapshotPublication
{
    public const DATASETS = ['subjekti', 'tvrtke', 'sjedista', 'pravni_oblici', 'predmeti_poslovanja'];

    /** @param list<array<string,mixed>> $rows */
    public static function isPartial(array $rows): bool
    {
        if (count($rows) !== count(self::DATASETS)) {
            throw new \RuntimeException('Cannot publish incomplete snapshot: missing datasets');
        }
        $byName = [];
        foreach ($rows as $row) {
            $dataset = $row['dataset'] ?? null;
            if (!is_string($dataset) || isset($byName[$dataset]) || !in_array($dataset, self::DATASETS, true)) {
                throw new \RuntimeException('Cannot publish snapshot: invalid dataset list');
            }
            if ((int)($row['finished'] ?? 0) !== 1) {
                throw new \RuntimeException('Cannot publish unfinished snapshot: ' . $dataset);
            }
            $byName[$dataset] = $row;
        }
        foreach (self::DATASETS as $required) {
            if (!isset($byName[$required])) {
                throw new \RuntimeException('Cannot publish incomplete snapshot: ' . $required);
            }
        }
        foreach (['subjekti', 'tvrtke'] as $required) {
            if ((int)($byName[$required]['imported_rows'] ?? 0) <= 0) {
                throw new \RuntimeException('Cannot publish empty registry dataset: ' . $required);
            }
        }
        foreach ($rows as $row) {
            if ((int)($row['truncated'] ?? 0) === 1) return true;
        }
        return false;
    }
}
