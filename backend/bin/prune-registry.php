<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $db = Tvrtke\Database::connect();
    if ((int)$db->query("SELECT GET_LOCK('tvrtke_prune_registry',0)")->fetchColumn() !== 1) {
        exit(0);
    }
    try {
        // Only obsolete published or abandoned batches; never delete an active
        // publication or a currently resumable staging import.
        $select = $db->query("SELECT r.source_type,r.import_id,r.record_hash
           FROM registry_records r
           JOIN registry_sources s ON s.source_type=r.source_type
           LEFT JOIN registry_import_jobs j ON j.source_type=r.source_type AND j.import_id=r.import_id
           WHERE r.import_id<>s.active_import_id AND j.import_id IS NULL
           LIMIT 200");
        $rows = $select->fetchAll(PDO::FETCH_ASSOC);
        if ($rows !== []) {
            $delete = $db->prepare('DELETE FROM registry_records WHERE source_type=? AND import_id=? AND record_hash=?');
            $db->beginTransaction();
            try {
                foreach ($rows as $r) $delete->execute([$r['source_type'],$r['import_id'],$r['record_hash']]);
                $db->commit();
            } catch (Throwable $error) {
                $db->rollBack();
                throw $error;
            }
        }
        echo sprintf("Obrisano zastarjelih zapisa: %d\n", count($rows));
    } finally {
        $db->query("SELECT RELEASE_LOCK('tvrtke_prune_registry')");
    }
} catch (Throwable $error) {
    error_log('TVRTKE prune registry: ' . $error->getMessage());
    fwrite(STDERR,"Čišćenje nije uspjelo. Provjerite privatni PHP log.\n");
    exit(1);
}
