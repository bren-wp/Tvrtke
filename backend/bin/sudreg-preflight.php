<?php
declare(strict_types=1);

/**
 * Prints hosting/database readiness, Sudreg version-pinned snapshot, and
 * source counts. Does not echo tokens, client credentials or passwords.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

$options = getopt('', ['online','json']);
$online = isset($options['online']);
$json = isset($options['json']);
$report = [
    'php_version' => PHP_VERSION,
    'extensions' => [
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
        'mbstring' => extension_loaded('mbstring'),
        'json' => extension_loaded('json'),
    ],
    'credentials_configured' => isConfigured('SUDREG_CLIENT_ID') && isConfigured('SUDREG_CLIENT_SECRET'),
    'sync' => [
        'include_inactive' => Tvrtke\Config::get('TVRTKE_SYNC_INCLUDE_INACTIVE', '1') === '1',
        'batch_size' => Tvrtke\SudregCoverage::batchSize((int)Tvrtke\Config::get('TVRTKE_IMPORT_BATCH','200')),
        'row_cap_per_dataset' => Tvrtke\SudregCoverage::rowLimit((int)Tvrtke\Config::get('TVRTKE_IMPORT_ROW_LIMIT','10000')),
        'archive_source_rows' => Tvrtke\Config::get('TVRTKE_SUDREG_ARCHIVE_RAW','0') === '1',
    ],
];

function isConfigured(string $name): bool
{
    $value = Tvrtke\Config::get($name);
    return $value !== '' && !str_contains(strtoupper($value), 'CHANGE_ME');
}

try {
    $db = Tvrtke\Database::connect();
    $report['db_connection'] = 'ok';
    $requiredTables = ['subjects','company_names','addresses','subject_legal_forms','subject_activities',
        'sync_progress','sudreg_raw_records','app_state'];
    $tables = $db->query("SELECT table_name,data_length,index_length FROM information_schema.tables
                          WHERE table_schema=DATABASE()")->fetchAll(PDO::FETCH_ASSOC);
    $tableMap = [];
    $bytes = 0;
    foreach ($tables as $t) {
        $tableMap[$t['table_name']] = true;
        $bytes += (int)$t['data_length'] + (int)$t['index_length'];
    }
    $report['db_table_bytes_estimate'] = $bytes;
    $report['required_tables_present'] = [];
    foreach ($requiredTables as $table) {
        $report['required_tables_present'][$table] = isset($tableMap[$table]);
    }
    if (!in_array(false, $report['required_tables_present'], true)) {
        $statement = $db->query("SELECT dataset,snapshot_id,imported_rows,expected_rows,include_inactive,
                finished,truncated FROM sync_progress ORDER BY dataset");
        $report['staging_progress'] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    $state = $db->query("SELECT state_key,state_value FROM app_state
                         WHERE state_key IN ('active_snapshot','published_partial','published_at','published_scope')")
                ->fetchAll(PDO::FETCH_KEY_PAIR);
    $report['publication'] = [
        'snapshot_id' => $state['active_snapshot'] ?? null,
        'partial' => !isset($state['published_partial']) || $state['published_partial'] !== '0',
        'scope' => $state['published_scope'] ?? 'unknown',
        'published_at_utc' => $state['published_at'] ?? null,
    ];
} catch (Throwable $error) {
    $report['db_connection'] = 'error';
    $report['db_diagnostic'] = 'Database connection or schema check failed; inspect private PHP error log';
    error_log('TVRTKE preflight DB: ' . $error->getMessage());
}

if ($online) {
    if (!$report['credentials_configured']) {
        $report['online'] = ['status'=>'skipped','reason'=>'Missing private Sudreg OAuth2 configuration'];
    } else {
        try {
            $client = new Tvrtke\SudregClient();
            $snapshots = $client->get('snapshots')['data'];
            if (!is_array($snapshots) || !array_is_list($snapshots)) {
                throw new RuntimeException('Unexpected snapshots response');
            }
            usort($snapshots, fn($a,$b) => (int)($a['staleness']??99) <=> (int)($b['staleness']??99));
            $latest = $snapshots[0] ?? [];
            $snapshot = (int)($latest['id'] ?? 0);
            if ($snapshot <= 0) throw new RuntimeException('No valid pinned snapshot');
            $counts = $client->get('counts', ['snapshot_id'=>$snapshot])['data'];
            $report['online'] = [
                'status'=>'ok',
                'snapshot_id'=>$snapshot,
                'snapshot_available_until'=>$latest['available_until'] ?? null,
                'snapshot_timestamp'=>$latest['timestamp'] ?? null,
                'counts_response'=>$counts,
            ];
        } catch (Throwable $error) {
            $report['online'] = ['status'=>'error','reason'=>'Sudreg access or counts request failed; inspect private server logs'];
            error_log('TVRTKE preflight Sudreg: ' . get_class($error) . ' ' . $error->getMessage());
        }
    }
}
if ($json) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} else {
    echo "TVRTKE — Sudreg provjera prije uvoza\n";
    echo "PHP ", $report['php_version'], " | DB: ", ($report['db_connection'] ?? 'unknown'), PHP_EOL;
    foreach ($report['extensions'] as $ext => $enabled) echo $ext, ': ', ($enabled ? 'DA' : 'NE'), PHP_EOL;
    echo "Privatne Sudreg vjerodajnice: ", ($report['credentials_configured'] ? 'postavljene' : 'nedostaju'), PHP_EOL;
    echo "Status objave: ", json_encode($report['publication'] ?? [], JSON_UNESCAPED_UNICODE), PHP_EOL;
    echo "Postavke uvoza: ", json_encode($report['sync'], JSON_UNESCAPED_UNICODE), PHP_EOL;
    echo "SQL tablice: ", json_encode($report['required_tables_present'] ?? [], JSON_UNESCAPED_UNICODE), PHP_EOL;
    echo "Procjena prostora tablica (B): ", ($report['db_table_bytes_estimate'] ?? 'nije dostupna'), PHP_EOL;
    if ($online) {
        echo "Sudreg provjera: ", json_encode($report['online'], JSON_UNESCAPED_UNICODE), PHP_EOL;
    }
}
if (($report['db_connection'] ?? '') !== 'ok' ||
    in_array(false, $report['required_tables_present'] ?? [], true) ||
    ($online && ($report['online']['status'] ?? '') !== 'ok')) {
    exit(1);
}
