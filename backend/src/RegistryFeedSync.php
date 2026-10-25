<?php
declare(strict_types=1);

namespace Tvrtke;

use PDO;

/**
 * Cron-only sync of official CKAN CSV exports into an isolated source snapshot.
 * Public applications never receive any client credentials or MySQL access.
 */
final class RegistryFeedSync
{
    public function __construct(private PDO $db) {}

    public function tick(string $type, array $settings, string $privateDir): array
    {
        $config = RegistryFeedConfig::validate($type,$settings);
        $dir = realpath($privateDir);
        if ($dir === false || !is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('Configure a writable private feed cache outside the webroot');
        }
        $publicRoots = [
            realpath(dirname(__DIR__) . '/public'),
            Config::get('TVRTKE_PUBLIC_ROOT') === '' ? false : realpath(Config::get('TVRTKE_PUBLIC_ROOT')),
        ];
        foreach ($publicRoots as $root) {
            if (is_string($root) && ($dir === $root || str_starts_with($dir,$root.DIRECTORY_SEPARATOR))) {
                throw new \RuntimeException('Registry feed cache must not be inside a public directory');
            }
        }
        $lockFile = $dir.'/tvrtke-'. $type .'.lock';
        $lock = fopen($lockFile,'c');
        if ($lock === false || !flock($lock,LOCK_EX|LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            throw new \RuntimeException('Registry source sync is already running');
        }
        try {
            return $this->process($type,$config,$dir);
        } finally {
            flock($lock,LOCK_UN);
            fclose($lock);
        }
    }

    private function process(string $type, array $config, string $dir): array
    {
        $currentFile = $dir.'/tvrtke-'.$type.'.csv';
        $pendingDigest = $dir.'/tvrtke-'.$type.'.pending.sha256';
        $publishedDigest = $dir.'/tvrtke-'.$type.'.published.sha256';
        $lastCheck = $dir.'/tvrtke-'.$type.'.last-check';
        $job = $this->db->prepare('SELECT import_id FROM registry_import_jobs WHERE source_type=? LIMIT 1');
        $job->execute([$type]);
        $staging = $job->fetchColumn() !== false;
        // Never mask an unfinished batch merely because the official feed
        // was checked recently. A pending job must resume or fail explicitly.
        if (!$staging && is_file($lastCheck) && !is_file($currentFile) &&
            (time() - (int)filemtime($lastCheck)) < 21600) {
            return ['type'=>$type,'status'=>'checked_recently','rows'=>null];
        }
        if (!$staging) {
            // Download only once per new batch. New external files cannot mutate
            // CSV bytes underneath an in-flight resumable import.
            $temporary = tempnam($dir,'tvrtke-feed-');
            if ($temporary === false) throw new \RuntimeException('Cannot create private CSV temp file');
            try {
                chmod($temporary,0600);
                $this->download($config['download_url'],$temporary,(int)$config['max_bytes']);
                $digest = hash_file('sha256',$temporary);
                if (!is_string($digest)) throw new \RuntimeException('Cannot verify official CSV checksum');
                $old = is_file($publishedDigest) ? trim((string)file_get_contents($publishedDigest)) : '';
                if ($old !== '' && hash_equals($old,$digest)) {
                    touch($lastCheck);
                    return ['type'=>$type,'status'=>'unchanged','rows'=>null];
                }
                if (!rename($temporary,$currentFile)) throw new \RuntimeException('Cannot stage official CSV');
                if (file_put_contents($pendingDigest,$digest,LOCK_EX) === false) {
                    throw new \RuntimeException('Cannot persist new CSV checksum');
                }
                chmod($currentFile,0600);
            } finally {
                if (is_file($temporary)) unlink($temporary);
            }
        } elseif (!is_file($currentFile)) {
            throw new \RuntimeException('Staged source CSV is missing: do not silently restart or change the original dataset');
        }
        if (!is_file($pendingDigest)) {
            // Recover from a crash between staging and digest write; source bytes
            // remain unchanged and the importer independently validates size/mtime.
            $digest = hash_file('sha256',$currentFile);
            if (!is_string($digest) || file_put_contents($pendingDigest,$digest,LOCK_EX) === false) {
                throw new \RuntimeException('Cannot recover pending source checksum');
            }
        }
        $importer = new RegistryImporter($this->db);
        $deadline = microtime(true)+(int)$config['budget_seconds'];
        $out = [];
        for ($page=0;$page<(int)$config['pages_per_run'] && microtime(true)<$deadline;$page++) {
            $out=$importer->tick(
                $type,$currentFile,$config['source_url'],$config['terms_url'],
                $config['columns'],$config['delimiter'],(int)$config['batch'],
                (int)$config['max_rows'],$config['expected_rows'],$config['coverage'],
                false // Never auto-import a natural person's OIB from craft exports.
            );
            if ($out['status']==='published') {
                $digest = trim((string)file_get_contents($pendingDigest));
                if (!preg_match('/^[a-f0-9]{64}$/D',$digest)) {
                    throw new \RuntimeException('Invalid feed digest after publishing');
                }
                if (!rename($pendingDigest,$publishedDigest)) {
                    throw new \RuntimeException('Cannot remember published official source version');
                }
                if (is_file($currentFile)) unlink($currentFile);
                touch($lastCheck);
                break;
            }
        }
        return $out ?: ['type'=>$type,'status'=>'no-progress','rows'=>null];
    }

    private function download(string $url, string $path, int $maxBytes): void
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP curl extension required');
        }
        // A second validation prevents callers from bypassing the manifest.
        RegistryFeedConfig::trustedDownloadUrl($url);
        $handle=fopen($path,'wb');
        if ($handle===false) throw new \RuntimeException('Cannot write private source file');
        $bytes=0;
        $curl=curl_init($url);
        curl_setopt_array($curl,[
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_TIMEOUT=>95,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_USERAGENT=>'TVRTKE/1.0 (official-registry-feed; administrator-approved)',
            CURLOPT_HTTPHEADER=>['Accept: text/csv,application/octet-stream'],
            CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_WRITEFUNCTION=>static function ($ch, string $chunk) use ($handle,$maxBytes,&$bytes): int {
                $length=strlen($chunk);
                if ($bytes+$length>$maxBytes) return 0;
                $written=fwrite($handle,$chunk);
                if ($written===false) return 0;
                $bytes+=$written;
                return $written;
            },
        ]);
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($curl,CURLOPT_PROTOCOLS_STR,'https');
        } else {
            curl_setopt($curl,CURLOPT_PROTOCOLS,CURLPROTO_HTTPS);
        }
        try {
            $ok=curl_exec($curl);
            $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
            $code=curl_errno($curl);
            if ($ok===false || $status!==200 || $bytes<1 || $bytes>$maxBytes) {
                // Numeric-only diagnostics; no URLs, tokens, request content or
                // private record values can be written to the error log.
                throw new \RuntimeException(sprintf(
                    'Official CSV transfer failed: HTTP %d, cURL %d, bytes %d',
                    $status,$code,$bytes
                ));
            }
        } finally {
            curl_close($curl);
            fclose($handle);
        }
    }
}
