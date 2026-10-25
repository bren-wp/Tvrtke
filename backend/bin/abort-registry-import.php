<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

// Explicit operator action; run repeatedly to remove abandoned staging safely.
$options = getopt('', ['source:', 'confirm:']);
try {
    if (!is_array($options) || !isset($options['source'], $options['confirm']) ||
        $options['confirm'] !== 'DELETE_STAGING') {
        throw new InvalidArgumentException('Usage: --source=udruga --confirm=DELETE_STAGING');
    }
    $type = Tvrtke\RegistryCsv::type((string)$options['source']);
    $db = Tvrtke\Database::connect();
    $lock = 'tvrtke_import_' . $type;
    if ((int)$db->query('SELECT GET_LOCK(' . $db->quote($lock) . ',0)')->fetchColumn() !== 1) {
        throw new RuntimeException('Importer is busy; cannot abort staging');
    }
    try {
        $job = $db->prepare('SELECT import_id FROM registry_import_jobs WHERE source_type=?');
        $job->execute([$type]);
        $importId = $job->fetchColumn();
        if ($importId === false) {
            echo "Nema aktivnog uvoza za izvor: $type\n";
            exit(0);
        }
        $published = $db->prepare('SELECT active_import_id FROM registry_sources WHERE source_type=?');
        $published->execute([$type]);
        if ((string)$published->fetchColumn() === (string)$importId) {
            throw new RuntimeException('Refusing to delete published batch');
        }
        $db->beginTransaction();
        try {
            $delete = $db->prepare('DELETE FROM registry_records WHERE source_type=? AND import_id=? LIMIT 250');
            $delete->execute([$type,$importId]);
            $removed = $delete->rowCount();
            if ($removed === 0) {
                $db->prepare('DELETE FROM registry_import_jobs WHERE source_type=? AND import_id=?')
                    ->execute([$type,$importId]);
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        echo sprintf("%s: obrisano %d zapisa iz neobjavljene serije. Ponovite dok posao ne nestane.\n",$type,$removed);
    } finally {
        $db->query('SELECT RELEASE_LOCK(' . $db->quote($lock) . ')');
    }
} catch (Throwable $error) {
    error_log('TVRTKE reset source: ' . $error->getMessage());
    fwrite(STDERR,"Reset zaustavljen: " . $error->getMessage() . PHP_EOL);
    exit(1);
}
