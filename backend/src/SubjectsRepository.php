<?php
declare(strict_types=1);

namespace Tvrtke;

use PDO;

final class SubjectsRepository
{
    public function __construct(private PDO $db) {}

    private function activeSnapshot(): ?string
    {
        $stmt = $this->db->query("SELECT state_value FROM app_state WHERE state_key = 'active_snapshot'");
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function search(string $query, int $page = 1, int $limit = 20): array
    {
        $snapshot = $this->activeSnapshot();
        if ($snapshot === null) {
            return ['items' => [], 'page' => $page, 'limit' => $limit, 'has_more' => false, 'snapshot_id' => null];
        }
        $offset = ($page - 1) * $limit;
        $sql = 'SELECT s.mbs, s.oib, s.status, n.name, a.county, a.settlement, a.street, a.house_number, a.postal_code
            FROM subjects s
            LEFT JOIN company_names n ON n.snapshot_id=s.snapshot_id AND n.mbs=s.mbs
            LEFT JOIN addresses a ON a.snapshot_id=s.snapshot_id AND a.mbs=s.mbs
            WHERE s.snapshot_id=:snapshot';
        $params = ['snapshot' => $snapshot];
        if ($query !== '') {
            $like = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
            $sql .= " AND (n.name LIKE :name ESCAPE '!' OR s.oib=:oib OR s.mbs=:mbs)";
            $params['name'] = $like;
            $params['oib'] = preg_match('/^\d{1,11}$/D', $query) ? str_pad($query, 11, '0', STR_PAD_LEFT) : '';
            $params['mbs'] = preg_match('/^\d{1,9}$/D', $query) ? str_pad($query, 9, '0', STR_PAD_LEFT) : '';
        }
        $sql .= ' ORDER BY n.name ASC, s.mbs ASC LIMIT :limit OFFSET :offset';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $more = count($items) > $limit;
        if ($more) array_pop($items);
        return ['items' => $items, 'page' => $page, 'limit' => $limit, 'has_more' => $more, 'snapshot_id' => $snapshot];
    }

    public function one(string $mbs): ?array
    {
        $snapshot = $this->activeSnapshot();
        if ($snapshot === null) return null;
        $stmt = $this->db->prepare('SELECT s.mbs,s.oib,s.status,s.datum_osnivanja,s.datum_brisanja,n.name,a.county,a.municipality,a.settlement,a.street,a.house_number,a.postal_code
            FROM subjects s LEFT JOIN company_names n ON n.mbs=s.mbs AND n.snapshot_id=s.snapshot_id
            LEFT JOIN addresses a ON a.mbs=s.mbs AND a.snapshot_id=s.snapshot_id
            WHERE s.snapshot_id=:snapshot AND s.mbs=:mbs LIMIT 1');
        $stmt->execute(['snapshot' => $snapshot, 'mbs' => $mbs]);
        $item = $stmt->fetch();
        return $item === false ? null : $item;
    }

    public function status(): array
    {
        $active = $this->activeSnapshot();
        return ['active_snapshot' => $active, 'progress' => $this->db->query('SELECT dataset,snapshot_id,next_offset,imported_rows,finished,truncated,updated_at FROM sync_progress ORDER BY dataset')->fetchAll()];
    }
}
