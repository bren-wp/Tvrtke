<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Tvrtke\Database;
use Tvrtke\SudregGateway;
use Tvrtke\Sync;

function verify(bool $actual, string $message): void {
    if (!$actual) throw new RuntimeException($message);
}

final class FakeSudreg implements SudregGateway
{
    public function __construct(public int $snapshot = 20261025, public bool $shortPage = false) {}

    public function get(string $endpoint, array $params = []): array
    {
        if ($endpoint === 'snapshots') {
            return ['data'=>[['id'=>$this->snapshot,'staleness'=>1]],'headers'=>[]];
        }
        if (($params['only_active'] ?? null) !== 'false') {
            throw new RuntimeException('Full import must explicitly request inactive records');
        }
        if (($params['snapshot_id'] ?? null) !== $this->snapshot) {
            throw new RuntimeException('Snapshot must remain pinned');
        }
        $a = '000000001'; $b = '000000002';
        $sets = [
            'subjekti' => [
                ['mbs'=>$a,'oib'=>'00000000001','status'=>1,'datum_osnivanja'=>'2020-01-01'],
                ['mbs'=>$b,'oib'=>'00000000002','status'=>0,'datum_brisanja'=>'2021-04-12'],
            ],
            'tvrtke' => [
                ['mbs'=>$a,'ime'=>'TVRTKE primjer aktivni d.o.o.'],
                ['mbs'=>$b,'ime'=>'TVRTKE primjer brisani d.o.o.'],
            ],
            'sjedista' => [
                ['mbs'=>$a,'naziv_zupanije'=>'Grad Zagreb','naziv_naselja'=>'Zagreb'],
                ['mbs'=>$b,'naziv_zupanije'=>'Primorsko-goranska','naziv_naselja'=>'Rijeka'],
            ],
            'pravni_oblici' => [
                ['mbs'=>$a,'vrsta_pravnog_oblika'=>['sifra'=>'1','naziv'=>'d.o.o.','kratica'=>'d.o.o.']],
                ['mbs'=>$b,'vrsta_pravnog_oblika'=>['sifra'=>'1','naziv'=>'d.o.o.','kratica'=>'d.o.o.']],
            ],
            'predmeti_poslovanja' => [
                ['mbs'=>$a,'djelatnost_rbr'=>1,'djelatnost_tekst'=>'Računalne usluge'],
                ['mbs'=>$b,'djelatnost_rbr'=>1,'djelatnost_tekst'=>'Trgovina'],
            ],
        ];
        if (!isset($sets[$endpoint])) throw new RuntimeException('Unsupported test endpoint: ' . $endpoint);
        $offset = (int)($params['offset'] ?? 0);
        $limit = (int)($params['limit'] ?? 100);
        $data = array_slice($sets[$endpoint], $offset, $limit);
        if ($this->shortPage && $endpoint === 'subjekti') $data = array_slice($data, 0, 1);
        return ['data'=>$data,'headers'=>['snapshot-id'=>$this->snapshot,'total-count'=>2]];
    }
}

$db=Database::connect();
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$root=dirname(__DIR__) . '/database/';
foreach (['001_initial.sql','002_enrichment.sql','003_publication_state.sql',
          '004_registry_sources.sql','005_sudreg_full_coverage.sql'] as $migration) {
    $raw = file_get_contents($root . $migration);
    if ($raw === false) throw new RuntimeException('Missing migration '.$migration);
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$raw) as $part) {
        $stmt = trim($part);
        if ($stmt !== '' && preg_match('/\b(CREATE|ALTER|INSERT|UPDATE|DELETE)\b/i', $stmt)) {
            $db->exec($stmt);
        }
    }
}
putenv('TVRTKE_SYNC_INCLUDE_INACTIVE=1');
putenv('TVRTKE_SUDREG_ARCHIVE_RAW=1');
putenv('TVRTKE_IMPORT_BATCH=50');
putenv('TVRTKE_IMPORT_ROW_LIMIT=1000');
putenv('TVRTKE_SYNC_PAGES_PER_RUN=4');
putenv('TVRTKE_SYNC_BUDGET_SECONDS=12');
$mock=new FakeSudreg();
$sync=new Sync($db,$mock);
$published=false;
for ($i=0;$i<12;$i++) {
    $message=$sync->tick();
    echo "sync tick ", $i, ": ", $message, PHP_EOL;
    if (str_contains($message,'Objavljena snimka')) { $published=true; break; }
}
verify($published,'Snapshot should publish after all five verified datasets');
$state=$db->query("SELECT state_key,state_value FROM app_state")->fetchAll(PDO::FETCH_KEY_PAIR);
verify(($state['active_snapshot'] ?? '') === '20261025','Active snapshot');
verify(($state['published_partial'] ?? '') === '0','Complete official rows, including inactive');
verify(($state['published_scope'] ?? '') === 'all','Published scope includes inactive');
verify((int)$db->query("SELECT COUNT(*) FROM subjects")->fetchColumn()===2,'Both active and inactive subjects imported');
verify((int)$db->query("SELECT COUNT(*) FROM subjects WHERE status=0")->fetchColumn()===1,'Inactive subject not omitted');
verify((int)$db->query("SELECT COUNT(*) FROM sudreg_raw_records")->fetchColumn()===10,'Lossless rows staged');
$jobs=$db->query("SELECT dataset,expected_rows,imported_rows,include_inactive,finished FROM sync_progress")->fetchAll();
verify(count($jobs)===5,'All five datasets completed');
foreach ($jobs as $job) {
    verify((int)$job['expected_rows']===2 && (int)$job['imported_rows']===2,'Expected counts match');
    verify((int)$job['include_inactive']===1 && (int)$job['finished']===1,'Full mode finished');
}
// A broken next snapshot must NOT displace a successfully published one.
$bad=new Sync($db,new FakeSudreg(20261026,true));
try {
    $bad->tick();
    throw new RuntimeException('Short page should be rejected');
} catch (RuntimeException $error) {
    verify(str_contains($error->getMessage(),'short page'),'Expected strict count mismatch');
}
$active=$db->query("SELECT state_value FROM app_state WHERE state_key='active_snapshot'")->fetchColumn();
verify((string)$active==='20261025','Failed next snapshot cannot replace published records');
echo "Sudreg MySQL integration: OK\n";
