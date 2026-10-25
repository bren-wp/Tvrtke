<?php
declare(strict_types=1);

namespace Tvrtke;

use PDO;

/**
 * Read-only unified registry search. A Sudreg snapshot is read only when published;
 * external registries are read only through their atomically published import IDs.
 */
final class DirectoryRepository
{
    public function __construct(private PDO $db) {}

    private static function prefix(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
    }

    private function publication(): array
    {
        return $this->db->query("SELECT state_key,state_value FROM app_state
            WHERE state_key IN ('active_snapshot','published_partial','published_at')")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function sources(): array
    {
        $sources = $this->db->query('SELECT source_type,coverage,record_count,published_at_utc,source_url
             FROM registry_sources WHERE active_import_id IS NOT NULL ORDER BY source_type')->fetchAll(PDO::FETCH_ASSOC);
        return $sources;
    }

    /** @return array<string,mixed> */
    public function search(string $query, int $page = 1, int $limit = 20, array $filters = []): array
    {
        $type = (string)($filters['type'] ?? 'all');
        if (!in_array($type, ['all','sudreg', ...RegistryCsv::TYPES], true)) {
            throw new \InvalidArgumentException('Nepoznata vrsta registra');
        }
        $publication = $this->publication();
        $snapshot = $publication['active_snapshot'] ?? null;
        $registered = $this->sources();
        $partial = !isset($publication['published_partial']) || $publication['published_partial'] !== '0';
        foreach ($registered as $source) {
            if ($source['coverage'] !== 'complete') $partial = true;
        }
        // With multiple registries, the source coverage is intentionally unknown
        // until independently verified; do not advertise national completeness.
        if ($type === 'all') {
            $available = array_column($registered, 'source_type');
            foreach (['obrt','udruga','zaklada'] as $wanted) {
                if (!in_array($wanted, $available, true)) $partial = true;
            }
        } elseif ($type !== 'sudreg') {
            $record = array_values(array_filter($registered, fn(array $x): bool => $x['source_type'] === $type));
            $partial = $record === [] || $record[0]['coverage'] !== 'complete';
        }
        $query = trim($query);
        $segments = [];
        $params = [];
        // At most the first K candidates from each source can contribute to the
        // global top K; cap each branch before UNION to reduce MySQL temp tables.
        $candidateCap = ($page * $limit) + 1;
        $county = trim((string)($filters['county'] ?? ''));
        $settlement = trim((string)($filters['settlement'] ?? ''));
        $activity = trim((string)($filters['activity'] ?? ''));
        $legal = trim((string)($filters['legal_form'] ?? ''));
        $status = trim((string)($filters['status'] ?? ''));
        $oib = preg_match('/^[0-9]{1,11}$/D', $query) ? str_pad($query, 11, '0', STR_PAD_LEFT) : '';
        $mbs = preg_match('/^[0-9]{1,9}$/D', $query) ? str_pad($query, 9, '0', STR_PAD_LEFT) : '';

        if ($snapshot !== null && in_array($type, ['all','sudreg'], true)) {
            $court = "SELECT CONCAT('sudreg:',s.mbs) AS registry_key,
                  'sudreg' AS source_type,s.mbs AS source_identifier,s.mbs,s.oib,
                  s.status AS status,IF(s.status=1,'Aktivan','Neaktivan / brisan') AS status_label,
                  s.datum_osnivanja,s.datum_brisanja,n.name,a.county,a.settlement,a.street,a.house_number,a.postal_code,
                  l.form_name AS legal_form,l.form_abbreviation AS legal_form_short,
                  NULL AS activity,NULL AS source_url
                FROM subjects s
                LEFT JOIN company_names n ON n.snapshot_id=s.snapshot_id AND n.mbs=s.mbs
                LEFT JOIN addresses a ON a.snapshot_id=s.snapshot_id AND a.mbs=s.mbs
                LEFT JOIN subject_legal_forms l ON l.snapshot_id=s.snapshot_id AND l.mbs=s.mbs
                WHERE s.snapshot_id=?";
            $params[] = $snapshot;
            if ($query !== '') {
                $court .= " AND (n.name LIKE ? ESCAPE '!' OR s.oib=? OR s.mbs=?)";
                array_push($params, self::prefix($query),$oib,$mbs);
            }
            if ($county !== '') { $court .= " AND a.county LIKE ? ESCAPE '!'"; $params[] = self::prefix($county); }
            if ($settlement !== '') { $court .= " AND a.settlement LIKE ? ESCAPE '!'"; $params[] = self::prefix($settlement); }
            if ($status !== '') { $court .= " AND s.status=?"; $params[] = $status; }
            if ($legal !== '') { $court .= " AND l.form_code=?"; $params[] = $legal; }
            if ($activity !== '') {
                $court .= " AND EXISTS (SELECT 1 FROM subject_activities d WHERE d.snapshot_id=s.snapshot_id
                      AND d.mbs=s.mbs AND (d.activity_text LIKE ? ESCAPE '!' OR d.nkd_name LIKE ? ESCAPE '!' OR d.nkd_code=?))";
                array_push($params,self::prefix($activity),self::prefix($activity),$activity);
            }
            $segments[] = 'SELECT * FROM (' . $court . ' ORDER BY n.name,s.mbs LIMIT ' . (int)$candidateCap . ') AS court_slice';
        }
        if ($type !== 'sudreg' && $legal === '') {
            $external = "SELECT CONCAT(r.source_type,':',r.record_hash) AS registry_key,
                     r.source_type,r.source_identifier,NULL AS mbs,r.oib,
                     CASE WHEN r.status='active' THEN 1 WHEN r.status='inactive' THEN 0 ELSE NULL END AS status,
                     CASE r.status WHEN 'active' THEN 'Aktivan' WHEN 'inactive' THEN 'Neaktivan' ELSE 'Nepoznat' END AS status_label,
                     NULL AS datum_osnivanja,NULL AS datum_brisanja,r.name,r.county,r.settlement,r.address AS street,NULL AS house_number,
                     NULL AS postal_code,NULL AS legal_form,NULL AS legal_form_short,
                     r.activity,src.source_url
                FROM registry_records r
                INNER JOIN registry_sources src ON src.source_type=r.source_type
                     AND src.active_import_id=r.import_id
                WHERE 1=1";
            if ($type !== 'all') { $external .= ' AND r.source_type=?'; $params[] = $type; }
            if ($query !== '') {
                $external .= " AND (r.name LIKE ? ESCAPE '!' OR r.oib=? OR r.source_identifier=?)";
                array_push($params,self::prefix($query),$oib,$query);
            }
            if ($county !== '') { $external .= " AND r.county LIKE ? ESCAPE '!'"; $params[] = self::prefix($county); }
            if ($settlement !== '') { $external .= " AND r.settlement LIKE ? ESCAPE '!'"; $params[] = self::prefix($settlement); }
            if ($status !== '') { $external .= " AND r.status=?"; $params[] = $status === '1' ? 'active' : 'inactive'; }
            if ($activity !== '') { $external .= " AND r.activity LIKE ? ESCAPE '!'"; $params[] = self::prefix($activity); }
            $segments[] = 'SELECT * FROM (' . $external . ' ORDER BY r.name,r.source_type,r.record_hash LIMIT ' . (int)$candidateCap . ') AS external_slice';
        }
        if ($segments === []) return ['items'=>[], 'page'=>$page, 'limit'=>$limit, 'has_more'=>false,
            'partial'=>$partial,'snapshot_id'=>$snapshot,'sources'=>$registered];
        $sql = 'SELECT * FROM (' . implode(' UNION ALL ', $segments) . ') AS records ORDER BY name,registry_key LIMIT ? OFFSET ?';
        $params[] = $limit + 1;
        $params[] = ($page - 1) * $limit;
        $stmt = $this->db->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, $index >= count($params) - 2 ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Prefer narrower filters instead of expensive unbounded deep paging on KNOX L.
        $more = count($items) > $limit && $page < 500;
        if ($more) array_pop($items);
        return ['items'=>$items, 'page'=>$page, 'limit'=>$limit, 'has_more'=>$more,
            'partial'=>$partial, 'snapshot_id'=>$snapshot, 'sources'=>$registered];
    }

    /** @return array<string,mixed>|null */
    public function one(string $type, string $id): ?array
    {
        if ($type === 'sudreg') {
            $item = (new SubjectsRepository($this->db))->one(Identity::mbs($id));
            if ($item === null) return null;
            $item['source_type'] = 'sudreg';
            $item['registry_key'] = 'sudreg:' . $item['mbs'];
            $item['source_identifier'] = $item['mbs'];
            $item['status_label'] = (int)$item['status'] === 1 ? 'Aktivan' : 'Neaktivan / brisan';
            return $item;
        }
        RegistryCsv::type($type);
        if (!preg_match('/^[a-f0-9]{64}$/D', $id)) {
            throw new \InvalidArgumentException('Neispravan ID izvora');
        }
        $stmt = $this->db->prepare("SELECT r.source_type,r.record_hash,r.source_identifier,r.name,r.oib,
              r.status AS registry_status,r.county,r.settlement,r.address AS street,r.activity,
              src.source_url,src.coverage,src.published_at_utc
            FROM registry_records r INNER JOIN registry_sources src
                 ON src.source_type=r.source_type AND src.active_import_id=r.import_id
            WHERE r.source_type=? AND r.record_hash=? LIMIT 1");
        $stmt->execute([$type,$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) return null;
        $raw = (string)$row['registry_status'];
        $row['status'] = $raw === 'active' ? 1 : ($raw === 'inactive' ? 0 : null);
        $row['status_label'] = $raw === 'active' ? 'Aktivan' : ($raw === 'inactive' ? 'Neaktivan' : 'Nepoznat');
        $row['mbs'] = null;
        $row['registry_key'] = $type . ':' . $id;
        $row['legal_form'] = null;
        $row['activities'] = $row['activity'] ? [['activity_text' => $row['activity']]] : [];
        $row['partial'] = $row['coverage'] !== 'complete';
        unset($row['record_hash'], $row['registry_status']);
        return $row;
    }
}
