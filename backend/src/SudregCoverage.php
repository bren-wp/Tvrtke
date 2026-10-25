<?php
declare(strict_types=1);

namespace Tvrtke;

/** Pure, testable guards for paginated court-register snapshot imports. */
final class SudregCoverage
{
    /** @return array{count:int,next:int,finished:bool,truncated:bool,expected:?int} */
    public static function page(int $offset, int $requested, int $received, ?int $headerTotal, ?int $savedTotal, int $cap): array
    {
        if ($offset < 0 || $requested < 1 || $received < 0 || $received > $requested || $cap < 1) {
            throw new \RuntimeException('Invalid Sudreg pagination state');
        }
        if ($headerTotal !== null && ($headerTotal < 0 || $headerTotal < $offset + $received)) {
            throw new \RuntimeException('Sudreg total is smaller than returned rows');
        }
        if ($savedTotal !== null && $headerTotal !== null && $savedTotal !== $headerTotal) {
            throw new \RuntimeException('Sudreg total changed within pinned snapshot');
        }
        $expected = $headerTotal ?? $savedTotal;
        $next = $offset + $received;
        // Never silently treat an empty/short page as the complete register
        // when the official count says more data exists.
        if ($expected !== null && $next < $expected && $received < $requested) {
            throw new \RuntimeException('Sudreg returned a short page before official total was reached');
        }
        if ($expected !== null && $offset > $expected) {
            throw new \RuntimeException('Sudreg offset exceeds official total');
        }
        $atCap = $next >= $cap;
        $finished = ($expected !== null && $next === $expected)
            || ($expected === null && $received < $requested)
            || $atCap;
        $truncated = $atCap && ($expected === null || $next < $expected);
        return ['count'=>$received, 'next'=>$next, 'finished'=>$finished,
            'truncated'=>$truncated, 'expected'=>$expected];
    }

    public static function limit(int $rows, int $maxRows, int $pageSize): int
    {
        if ($rows < 0 || $maxRows < 1 || $pageSize < 1 || $rows >= $maxRows) {
            throw new \RuntimeException('Sudreg import row limit reached');
        }
        return min($pageSize, $maxRows - $rows);
    }

    public static function batchSize(int $configured): int
    {
        return max(50, min(1000, $configured));
    }

    public static function rowLimit(int $configured): int
    {
        return max(1000, min(5000000, $configured));
    }
}
