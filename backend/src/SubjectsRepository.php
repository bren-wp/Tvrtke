<?php
declare(strict_types=1);

namespace Tvrtke;

use PDO;

final class SubjectsRepository
{
    public function __construct(private PDO $db) {}

    private function activeSnapshot(): ?string
    {
        $value = $this->db->query("SELECT state_value FROM app_state WHERE state_key='active_snapshot'")->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    /** @param array{county?:string,settlement?:string,status?:string,activity?:string,legal_form?:string} $filters */
    public function search(string $query, int $page = 1, int $limit = 20, array $filters = []): array
    {
        $snapshot = $this->activeSnapshot();
        if ($snapshot === null) {
            return ['items' => [], 'page' => $page, 'limit' => $limit, 'has_more' => false, 'snapshot_id' => null, 'partial' => true];
        }
        $sql = 'SELECT s.mbs,s.oib,s.status,n.name,a.county,a.settlement,a.street,a.house_number,a.postal_code,
                       l.form_name AS legal_form,l.form_abbreviation AS legal_form_short
                FROM subjects s
                LEFT JOIN company_names n ON n.snapshot_id=s.snapshot_id AND n.mbs=s.mbs
                LEFT JOIN addresses a ON a.snapshot_id=s.snapshot_id AND a.mbs=s.mbs
                LEFT JOIN subject_legal_forms l ON l.snapshot_id=s.snapshot_id AND l.mbs=s.mbs
                WHERE s.snapshot_id=:snapshot';
        $params = ['snapshot' => $snapshot];
        if ($query !== '') {
            $sql .= " AND (n.name LIKE :name ESCAPE '!' OR s.oib=:oib OR s.mbs=:mbs)";
            $params['name'] = self::escapedPrefix($query);
            $params['oib'] = preg_match('/^\d{1,11}$/D', $query) ? str_pad($query, 11, '0', STR_PAD_LEFT) : '';
            $params['mbs'] = preg_match('/^\d{1,9}$/D', $query) ? str_pad($query, 9, '0', STR_PAD_LEFT) : '';
        }
        foreach (['county' => 'a.county', 'settlement' => 'a.settlement'] as $name => $column) {
            $value = trim($filters[$name] ?? '');
            if ($value !== '') {
                $sql .= " AND {$column} LIKE :{$name} ESCAPE '!'";
                $params[$name] = self::escapedPrefix($value);
            }
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= ' AND s.status=:status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['legal_form'])) {
            $sql .= ' AND l.form_code=:legal_form';
            $params['legal_form'] = $filters['legal_form'];
        }
        if (!empty($filters['activity'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM subject_activities act
                     WHERE act.snapshot_id=s.snapshot_id AND act.mbs=s.mbs
                     AND (act.activity_text LIKE :activity_text ESCAPE '!'
                       OR act.nkd_name LIKE :activity_name ESCAPE '!'
                       OR act.nkd_code=:activity_code))";
            $params['activity_text'] = self::escapedPrefix($filters['activity']);
            $params['activity_name'] = self::escapedPrefix($filters['activity']);
            $params['activity_code'] = $filters['activity'];
        }
        $sql .= ' ORDER BY n.name ASC, s.mbs ASC LIMIT :limit OFFSET :offset';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $more = count($items) > $limit;
        if ($more) array_pop($items);
        return ['items' => $items, 'page' => $page, 'limit' => $limit,
            'has_more' => $more, 'snapshot_id' => $snapshot, 'partial' => $this->isPartial($snapshot)];
    }

    private static function escapedPrefix(string $text): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text) . '%';
    }

    public function one(string $mbs): ?array
    {
        $snapshot = $this->activeSnapshot();
        if ($snapshot === null) return null;
        $stmt = $this->db->prepare('SELECT s.mbs,s.oib,s.status,s.datum_osnivanja,s.datum_brisanja,
                 n.name,a.county,a.municipality,a.settlement,a.street,a.house_number,a.postal_code,
                 l.form_code,l.form_name AS legal_form,l.form_abbreviation AS legal_form_short
                 FROM subjects s
                 LEFT JOIN company_names n ON n.mbs=s.mbs AND n.snapshot_id=s.snapshot_id
                 LEFT JOIN addresses a ON a.mbs=s.mbs AND a.snapshot_id=s.snapshot_id
                 LEFT JOIN subject_legal_forms l ON l.mbs=s.mbs AND l.snapshot_id=s.snapshot_id
                 WHERE s.snapshot_id=:snapshot AND s.mbs=:mbs LIMIT 1');
        $stmt->execute(['snapshot' => $snapshot, 'mbs' => $mbs]);
        $item = $stmt->fetch();
        if ($item === false) return null;
        $activities = $this->db->prepare('SELECT activity_rbr,activity_text,nkd_code,nkd_name,nkd_version
            FROM subject_activities WHERE snapshot_id=? AND mbs=? ORDER BY activity_rbr LIMIT 100');
        $activities->execute([$snapshot, $mbs]);
        $item['activities'] = $activities->fetchAll();
        $item['snapshot_id'] = $snapshot;
        $item['partial'] = $this->isPartial($snapshot);
        return $item;
    }

    private function isPartial(string $snapshot): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM sync_progress WHERE snapshot_id=? AND truncated=1');
        $stmt->execute([$snapshot]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function status(): array
    {
        return ['active_snapshot' => $this->activeSnapshot(),
            'progress' => $this->db->query('SELECT dataset,snapshot_id,next_offset,imported_rows,finished,truncated,updated_at FROM sync_progress ORDER BY dataset')->fetchAll()];
    }
}
