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
        $partial = false;
        foreach ($rows as $row) {
            $imported = (int)($row['imported_rows'] ?? 0);
            $expected = $row['expected_rows'] ?? null;
            $truncated = (int)($row['truncated'] ?? 0) === 1;
            // A new v0.7 full import must explicitly request inactive rows.
            // Older imports without this metadata remain marked partial.
            if ((int)($row['include_inactive'] ?? 0) !== 1) $partial = true;
            if ($expected === null) {
                $partial = true; // Missing official count cannot establish completeness.
            } elseif (!is_numeric($expected) || (int)$expected < 0 || $imported > (int)$expected) {
                throw new \RuntimeException('Invalid official dataset count');
            } elseif ($imported !== (int)$expected && !$truncated) {
                throw new \RuntimeException('Unverified dataset size; publication refused');
            } elseif ($imported !== (int)$expected) {
                $partial = true;
            }
            if ($truncated) $partial = true;
        }
        return $partial;
    }
}
