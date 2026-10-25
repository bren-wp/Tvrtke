<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
use Tvrtke\SudregCoverage;

function mustReject(callable $fn, string $reason): void {
    try { $fn(); }
    catch (RuntimeException $e) { return; }
    throw new RuntimeException('Did not reject ' . $reason);
}
function must(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }

$first = SudregCoverage::page(0, 500, 500, 1250, null, 5000);
must(!$first['finished'] && !$first['truncated'] && $first['expected'] === 1250,'first page');
$second = SudregCoverage::page(500,500,500,1250,1250,5000);
must(!$second['finished'] && $second['next']===1000,'second page');
$final = SudregCoverage::page(1000,500,250,1250,1250,5000);
must($final['finished'] && !$final['truncated'] && $final['next']===1250,'last page');
$bounded = SudregCoverage::page(1000,250,250,2500,2500,1250);
must($bounded['finished'] && $bounded['truncated'],'capped import');
$unknown = SudregCoverage::page(0,500,100,null,null,5000);
must($unknown['finished'] && $unknown['expected'] === null, 'unverified total must stay unknown');
mustReject(fn()=>SudregCoverage::page(0,500,50,1250,null,5000), 'short non-final page');
mustReject(fn()=>SudregCoverage::page(0,500,500,1500,1000,5000), 'changing pinned total');
mustReject(fn()=>SudregCoverage::page(0,500,501,501,null,5000), 'overlong batch');
mustReject(fn()=>SudregCoverage::page(1500,500,100,1000,null,5000), 'offset beyond count');
must(SudregCoverage::batchSize(2000) === 1000,'batch ceiling');
must(SudregCoverage::rowLimit(999999999) === 5000000,'import cap');
echo "Sudreg coverage tests OK\n";
