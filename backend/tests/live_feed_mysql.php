<?php
declare(strict_types=1);
// GitHub Actions only: import a real open-licensed official CSV into disposable
// MariaDB and verify aggregates. Never display entity names, IDs, OIB or emails.
require dirname(__DIR__) . '/src/bootstrap.php';

$db=Tvrtke\Database::connect();
foreach ([
    '001_initial.sql','002_enrichment.sql','003_publication_state.sql',
    '004_registry_sources.sql','005_sudreg_full_coverage.sql',
    '006_registry_lifecycle.sql','007_registry_details.sql'
] as $migration) {
    $text=file_get_contents(dirname(__DIR__) . '/database/' . $migration);
    if ($text===false) throw new RuntimeException('Missing migration');
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$text) as $raw) {
        $sql=trim($raw);
        if ($sql!=='' && preg_match('/\b(CREATE|ALTER|INSERT|UPDATE|DELETE)\b/i',$sql)) {
            $db->exec($sql);
        }
    }
}
$manifest=json_decode((string)file_get_contents(dirname(__DIR__) . '/feeds.example.json'),true,32,JSON_THROW_ON_ERROR);
if (!is_array($manifest) || !isset($manifest['udruga'])) throw new RuntimeException('Missing official association source mapping');
$dir=sys_get_temp_dir().'/tvrtke-approved-source-'.bin2hex(random_bytes(5));
if (!mkdir($dir,0700)) throw new RuntimeException('No private CI directory');
try {
    $sync=new Tvrtke\RegistryFeedSync($db);
    $result=null;
    for ($i=0;$i<160;$i++) {
        $result=$sync->tick('udruga',$manifest['udruga'],$dir);
        if (($result['status'] ?? '')==='published') break;
        if (($result['status'] ?? '')==='checked_recently' ||
            ($result['status'] ?? '')==='unchanged') {
            throw new RuntimeException('Official registry not imported from fresh source');
        }
    }
    if (($result['status']??'')!=='published') throw new RuntimeException('Registry not published within bounded test ticks');
    $stmt=$db->query("SELECT record_count FROM registry_sources WHERE source_type='udruga' AND active_import_id IS NOT NULL");
    $count=(int)$stmt->fetchColumn();
    if ($count<1000) throw new RuntimeException('Official registry appears empty or truncated');
    $stmt=$db->query("SELECT status,COUNT(*) AS n FROM registry_records WHERE source_type='udruga' GROUP BY status");
    $statuses=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($statuses['active']) || empty($statuses['inactive'])) {
        throw new RuntimeException('Active and deleted records not imported');
    }
    $m=(int)$db->query("SELECT MAX(CHAR_LENGTH(activity)) FROM registry_records WHERE source_type='udruga'")->fetchColumn();
    if ($m<513) throw new RuntimeException('Full official activity descriptions were unexpectedly truncated');
    $dates=(int)$db->query("SELECT COUNT(*) FROM registry_records WHERE source_type='udruga' AND datum_upisa IS NOT NULL")->fetchColumn();
    if ($dates<1000) throw new RuntimeException('Official registry registration dates not decoded');
    echo json_encode(['source'=>'udruga','published_records'=>$count,
        'status_counts'=>$statuses,'longest_activity_characters'=>$m,
        'records_with_registration_date'=>$dates],
        JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
} finally {
    // Runner filesystem is ephemeral, but do not leave a local copy behind.
    foreach (glob($dir.'/*') ?: [] as $path) {
        if (is_file($path)) unlink($path);
    }
    rmdir($dir);
}
