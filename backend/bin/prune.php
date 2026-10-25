<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $db = Tvrtke\Database::connect();
    if ((int) $db->query("SELECT GET_LOCK('tvrtke_sync_v1', 0)")->fetchColumn() !== 1) {
        exit(0); // Do not race the importer.
    }
    try {
        $active = $db->query("SELECT state_value FROM app_state WHERE state_key='active_snapshot'")->fetchColumn();
        $staging = $db->query('SELECT snapshot_id FROM sync_progress LIMIT 1')->fetchColumn();
        if ($active === false) exit(0); // Keep all data until the first publication.
        $keep = array_values(array_unique(array_map('strval', array_filter([$active, $staging], fn($v) => $v !== false))));
        foreach (['subjects', 'company_names', 'addresses', 'subject_legal_forms', 'subject_activities'] as $table) {
            $placeholders = implode(',', array_fill(0, count($keep), '?'));
            $stmt = $db->prepare("DELETE FROM $table WHERE snapshot_id NOT IN ($placeholders) LIMIT 500");
            $stmt->execute($keep);
            echo "$table: obrisano " . $stmt->rowCount() . " zastarjelih redaka\n";
        }
    } finally {
        $db->query("SELECT RELEASE_LOCK('tvrtke_sync_v1')");
    }
} catch (Throwable $e) {
    error_log('TVRTKE prune: ' . $e->getMessage());
    fwrite(STDERR, "Neuspjelo čišćenje baze.\n");
    exit(1);
}
