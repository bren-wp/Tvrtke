<?php
declare(strict_types=1);

namespace Tvrtke;

use PDO;

/** Small, resumable, version-pinned batches for shared hosting. CLI only. */
final class Sync
{
    private const DATASETS = SnapshotPublication::DATASETS;

    public function __construct(private PDO $db, private SudregClient $client) {}

    public function tick(): string
    {
        if ((int) $this->db->query("SELECT GET_LOCK('tvrtke_sync_v1', 0)")->fetchColumn() !== 1) {
            return 'Sinkronizacija se već izvršava.';
        }
        try {
            return $this->process();
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('tvrtke_sync_v1')");
        }
    }

    private function process(): string
    {
        $snapshots = $this->client->get('snapshots')['data'];
        if (!array_is_list($snapshots)) throw new \RuntimeException('Unexpected snapshots payload');
        usort($snapshots, fn(array $a, array $b) => ((int) ($a['staleness'] ?? 99)) <=> ((int) ($b['staleness'] ?? 99)));
        $fresh = (int) ($snapshots[0]['id'] ?? 0);
        if ($fresh < 1) throw new \RuntimeException('No Sudreg snapshots available');
        $progress = $this->db->query('SELECT dataset,snapshot_id,finished FROM sync_progress')->fetchAll(PDO::FETCH_ASSOC);
        $active = $this->db->query("SELECT state_value FROM app_state WHERE state_key='active_snapshot'")->fetchColumn();
        // Upgrade an existing v0.1 installation in place without dropping published data.
        // Missing datasets are imported into the same pinned snapshot before marking it complete.
        if ($progress !== [] && count(array_unique(array_column($progress, 'snapshot_id'))) === 1) {
            $present = array_column($progress, 'dataset');
            $add = $this->db->prepare('INSERT IGNORE INTO sync_progress(dataset,snapshot_id) VALUES(?,?)');
            foreach (self::DATASETS as $dataset) {
                if (!in_array($dataset, $present, true)) {
                    $add->execute([$dataset, $progress[0]['snapshot_id']]);
                    $progress[] = ['dataset' => $dataset, 'snapshot_id' => $progress[0]['snapshot_id'], 'finished' => 0];
                }
            }
        }
        $unfinished = array_values(array_filter($progress, fn(array $p) => !(int) $p['finished']));
        $ongoing = $unfinished !== [] ? (int) $unfinished[0]['snapshot_id'] : $fresh;
        $available = array_map(fn(array $s) => (int) ($s['id'] ?? 0), $snapshots);
        if (!in_array($ongoing, $available, true)) $ongoing = $fresh;
        if ((string) $active === (string) $fresh && $unfinished === []) {
            return 'Podaci su već sinkronizirani za aktualnu snimku ' . $fresh;
        }
        if ($ongoing !== $fresh && $unfinished === []) $ongoing = $fresh;
        // Set up staging for a snapshot only when it differs from the saved progress.
        $stagedId = $progress !== [] ? (int) $progress[0]['snapshot_id'] : 0;
        if ($stagedId !== $ongoing || $progress === []) {
            $this->db->beginTransaction();
            try {
                $this->db->exec('DELETE FROM sync_progress');
                $insert = $this->db->prepare('INSERT INTO sync_progress(dataset,snapshot_id) VALUES(?,?)');
                foreach (self::DATASETS as $dataset) $insert->execute([$dataset, $ongoing]);
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        }
        $stmt = $this->db->prepare('SELECT * FROM sync_progress WHERE finished=0 AND snapshot_id=? ORDER BY FIELD(dataset,\'subjekti\',\'tvrtke\',\'sjedista\',\'pravni_oblici\',\'predmeti_poslovanja\') LIMIT 1');
        $stmt->execute([$ongoing]);
        $job = $stmt->fetch();
        if ($job === false) {
            // An empty / invalid import must never replace the previously published registry.
            $counts = $this->db->prepare("SELECT dataset,imported_rows,finished,truncated FROM sync_progress WHERE snapshot_id=?");
            $counts->execute([$ongoing]);
            $progressRows = $counts->fetchAll(PDO::FETCH_ASSOC);
            $partial = SnapshotPublication::isPartial($progressRows);
            $this->db->beginTransaction();
            try {
                $upsert = $this->db->prepare('INSERT INTO app_state(state_key,state_value) VALUES(?,?) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value)');
                $upsert->execute(['active_snapshot', (string)$ongoing]);
                $upsert->execute(['published_partial', $partial ? '1' : '0']);
                $upsert->execute(['published_at', gmdate('Y-m-d H:i:s')]);
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
            return 'Objavljena snimka ' . $ongoing;
        }
        $dataset = (string) $job['dataset'];
        $offset = (int) $job['next_offset'];
        $maxRows = max(100, min(200000, (int) Config::get('TVRTKE_IMPORT_ROW_LIMIT', '10000')));
        $pageSize = max(10, min(500, (int) Config::get('TVRTKE_IMPORT_BATCH', '100')));
        $remaining = $maxRows - $offset;
        if ($remaining <= 0) {
            $this->db->prepare('UPDATE sync_progress SET finished=1,truncated=1 WHERE dataset=?')->execute([$dataset]);
            return $dataset . ': dosegnuto ograničenje ' . $maxRows;
        }
        $limit = min($pageSize, $remaining);
        $response = $this->client->get($dataset, ['snapshot_id' => $ongoing, 'offset' => $offset, 'limit' => $limit] + (in_array($dataset, ['pravni_oblici', 'predmeti_poslovanja'], true) ? ['expand_relations' => 'true'] : []));
        if (isset($response['headers']['snapshot-id']) && (int) $response['headers']['snapshot-id'] !== $ongoing) {
            throw new \RuntimeException('Snapshot mismatch; batch rejected');
        }
        $rows = $response['data'];
        if (!array_is_list($rows)) throw new \RuntimeException('Unexpected dataset payload: ' . $dataset);
        if (count($rows) > $limit) throw new \RuntimeException('Sudreg returned more rows than requested');
        $count = count($rows);
        $next = $offset + $count;
        $total = $response['headers']['total-count'] ?? null;
        $finished = $count === 0 || $count < $limit || ($total !== null && $next >= $total) || $next >= $maxRows;
        $truncated = $next >= $maxRows && ($total === null || $next < $total);
        $this->db->beginTransaction();
        try {
            $this->saveRows($dataset, $ongoing, $rows);
            $update = $this->db->prepare('UPDATE sync_progress SET next_offset=?,imported_rows=?,finished=?,truncated=? WHERE dataset=? AND snapshot_id=?');
            $update->execute([$next, $next, (int) $finished, (int) $truncated, $dataset, $ongoing]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return sprintf('%s: %d zapisa, ukupno %d, snimka %d%s', $dataset, $count, $next, $ongoing, $truncated ? ' (ograničeni uvoz)' : '');
    }

    private function saveRows(string $dataset, int $snapshot, array $rows): void
    {
        $sql = match ($dataset) {
            'subjekti' => 'INSERT INTO subjects (snapshot_id,mbs,oib,status,datum_osnivanja,datum_brisanja) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE oib=VALUES(oib),status=VALUES(status),datum_osnivanja=VALUES(datum_osnivanja),datum_brisanja=VALUES(datum_brisanja)',
            'tvrtke' => 'INSERT INTO company_names(snapshot_id,mbs,name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)',
            'sjedista' => 'INSERT INTO addresses(snapshot_id,mbs,county,municipality,settlement,street,house_number,postal_code) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE county=VALUES(county),municipality=VALUES(municipality),settlement=VALUES(settlement),street=VALUES(street),house_number=VALUES(house_number),postal_code=VALUES(postal_code)',
            'pravni_oblici' => 'INSERT INTO subject_legal_forms(snapshot_id,mbs,form_code,form_name,form_abbreviation) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE form_code=VALUES(form_code),form_name=VALUES(form_name),form_abbreviation=VALUES(form_abbreviation)',
            'predmeti_poslovanja' => 'INSERT INTO subject_activities(snapshot_id,mbs,activity_rbr,activity_text,nkd_code,nkd_name,nkd_version) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE activity_text=VALUES(activity_text),nkd_code=VALUES(nkd_code),nkd_name=VALUES(nkd_name),nkd_version=VALUES(nkd_version)',
        };
        $statement = $this->db->prepare($sql);
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['mbs'])) throw new \RuntimeException('Missing MBS in Sudreg row');
            $mbs = Identity::mbs($row['mbs']);
            $values = match ($dataset) {
                'subjekti' => [$snapshot, $mbs, Identity::oib($row['oib'] ?? null), (int) ($row['status'] ?? 0), Identity::date($row['datum_osnivanja'] ?? null), Identity::date($row['datum_brisanja'] ?? null)],
                'tvrtke' => [$snapshot, $mbs, mb_substr((string) ($row['ime'] ?? ''), 0, 512)],
                'sjedista' => [$snapshot, $mbs, $row['naziv_zupanije'] ?? null, $row['naziv_opcine'] ?? null, $row['naziv_naselja'] ?? ($row['naselje_van_sifrarnika'] ?? null), $row['ulica'] ?? null, isset($row['kucni_broj']) ? (string) $row['kucni_broj'] . (string) ($row['kucni_podbroj'] ?? '') : null, isset($row['postanski_broj']) ? (string) $row['postanski_broj'] : null],
                'pravni_oblici' => [$snapshot, $mbs, isset($row['vrsta_pravnog_oblika']['sifra']) ? (string)$row['vrsta_pravnog_oblika']['sifra'] : null, $row['vrsta_pravnog_oblika']['naziv'] ?? null, $row['vrsta_pravnog_oblika']['kratica'] ?? null],
                'predmeti_poslovanja' => [$snapshot, $mbs, (int)($row['djelatnost_rbr'] ?? throw new \RuntimeException('Missing activity sequence')), $row['djelatnost_tekst'] ?? null, $row['nacionalna_klasifikacija_djelatnosti']['sifra'] ?? null, $row['nacionalna_klasifikacija_djelatnosti']['puni_naziv'] ?? null, $row['nacionalna_klasifikacija_djelatnosti']['verzija'] ?? null],
            };
            $statement->execute($values);
        }
    }
}
