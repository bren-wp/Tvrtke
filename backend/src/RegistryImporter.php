<?php
declare(strict_types=1);

namespace Tvrtke;

use PDO;

/** Bounded, resumable and atomically published imports from authorized CSV files. */
final class RegistryImporter
{
    public function __construct(private PDO $db) {}

    /**
     * @param array<string,string> $columnMap
     * @return array{status:string,source:string,rows:int,import_id:string}
     */
    public function tick(
        string $type,
        string $file,
        string $sourceUrl,
        ?string $termsUrl = null,
        array $columnMap = [],
        string $delimiter = ',',
        int $rowsPerTick = 250,
        int $maxTotalRows = 500000,
        ?int $expectedRows = null,
        string $coverage = 'partial',
        bool $allowCraftOib = false
    ): array {
        RegistryCsv::type($type);
        RegistryCsv::httpsUrl($sourceUrl);
        RegistryCsv::httpsUrl($termsUrl ?? '', true);
        if (!in_array($delimiter, [',', ';', "\t"], true)) throw new \InvalidArgumentException('Unsupported CSV separator');
        if ($rowsPerTick < 1 || $rowsPerTick > 500 || $maxTotalRows < 1 || $maxTotalRows > 2000000) {
            throw new \InvalidArgumentException('Unsafe import limits');
        }
        if (!in_array($coverage, ['partial', 'complete'], true)) {
            throw new \InvalidArgumentException('Unsupported coverage state');
        }
        if ($expectedRows !== null && $expectedRows < 1) throw new \InvalidArgumentException('Invalid expected row count');
        if ($coverage === 'complete' && $expectedRows === null) {
            throw new \InvalidArgumentException('Complete coverage requires a verified expected count');
        }
        if ($expectedRows !== null && $expectedRows > $maxTotalRows) {
            throw new \InvalidArgumentException('Expected records exceed configured storage limit');
        }
        $real = realpath($file);
        if ($real === false || !is_file($real) || !is_readable($real)) {
            throw new \RuntimeException('CSV file not found; keep source files outside the public webroot');
        }
        // Source exports may contain sensitive data: reject known public directories.
        $home = getenv('HOME');
        $roots = [
            realpath(dirname(__DIR__) . '/public'),
            Config::get('TVRTKE_PUBLIC_ROOT') !== '' ? realpath(Config::get('TVRTKE_PUBLIC_ROOT')) : false,
            is_string($home) && $home !== '' ? realpath($home . '/public_html') : false,
        ];
        foreach ($roots as $root) {
            if (is_string($root) && ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR))) {
                throw new \RuntimeException('Source CSV must be stored outside the public webroot');
            }
        }
        $size = filesize($real);
        $mtime = filemtime($real);
        if ($size === false || $mtime === false || $size === 0) throw new \RuntimeException('Invalid CSV file');
        $handle = fopen($real, 'rb');
        if ($handle === false) throw new \RuntimeException('Cannot read CSV input');
        $lock = 'tvrtke_import_' . $type;
        if ((int) $this->db->query("SELECT GET_LOCK(" . $this->db->quote($lock) . ",0)")->fetchColumn() !== 1) {
            fclose($handle);
            throw new \RuntimeException('A source import is already running');
        }
        try {
            $jobStmt = $this->db->prepare('SELECT * FROM registry_import_jobs WHERE source_type=?');
            $jobStmt->execute([$type]);
            $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
            if ($job === false) {
                $header = fgetcsv($handle, 0, $delimiter, '"', '');
                if ($header === false || !is_array($header)) throw new \InvalidArgumentException('CSV header not found');
                $columns = RegistryCsv::columns($header, $columnMap);
                $importId = bin2hex(random_bytes(16));
                $position = ftell($handle);
                if ($position === false) throw new \RuntimeException('Cannot read CSV offset');
                $meta = json_encode(['columns' => $columns, 'allow_craft_oib' => $allowCraftOib], JSON_THROW_ON_ERROR);
                $add = $this->db->prepare('INSERT INTO registry_import_jobs
                    (source_type,import_id,input_size,input_mtime,next_byte_offset,expected_rows,source_url,terms_url,coverage,csv_delimiter,header_json)
                    VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $add->execute([$type,$importId,$size,$mtime,$position,$expectedRows,$sourceUrl,$termsUrl,$coverage,$delimiter,$meta]);
                $job = [
                    'import_id' => $importId, 'input_size' => $size, 'input_mtime' => $mtime,
                    'next_byte_offset' => $position, 'imported_rows' => 0, 'expected_rows' => $expectedRows,
                    'source_url' => $sourceUrl, 'terms_url' => $termsUrl,
                    'coverage' => $coverage, 'csv_delimiter' => $delimiter, 'header_json' => $meta,
                ];
            }
            if ((int)$job['input_size'] !== $size || (int)$job['input_mtime'] !== $mtime ||
                (string)$job['source_url'] !== $sourceUrl || (string)$job['csv_delimiter'] !== $delimiter ||
                (string)$job['coverage'] !== $coverage ||
                (string)($job['terms_url'] ?? '') !== (string)($termsUrl ?? '') ||
                ($job['expected_rows'] === null ? null : (int)$job['expected_rows']) !== $expectedRows) {
                throw new \RuntimeException('Input or import configuration changed; original staging kept unchanged');
            }
            $meta = json_decode((string)$job['header_json'], true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($meta) || !isset($meta['columns']) || !is_array($meta['columns']) ||
                (bool)($meta['allow_craft_oib'] ?? false) !== $allowCraftOib) {
                throw new \RuntimeException('CSV import mapping changed or is corrupt');
            }
            // Validate mapping again to avoid silently mixing data from a changed command.
            $firstRow = null;
            rewind($handle);
            $firstRow = fgetcsv($handle, 0, $delimiter, '"', '');
            if ($firstRow === false || RegistryCsv::columns($firstRow, $columnMap) !== $meta['columns']) {
                throw new \RuntimeException('CSV column mapping changed');
            }
            if (fseek($handle, (int)$job['next_byte_offset']) !== 0) {
                throw new \RuntimeException('Could not resume CSV at saved byte offset');
            }
            $importId = (string)$job['import_id'];
            $total = (int)$job['imported_rows'];
            $atLimit = $total >= $maxTotalRows;
            if ($atLimit && fgetcsv($handle, 0, $delimiter, '"', '') !== false) {
                throw new \RuntimeException('Maximum record limit reached; no partial source published');
            }
            $insert = $this->db->prepare('INSERT INTO registry_records
              (source_type,import_id,record_hash,source_identifier,name,oib,status,county,settlement,address,activity)
              VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $progress = $this->db->prepare('UPDATE registry_import_jobs
              SET next_byte_offset=?,imported_rows=? WHERE source_type=? AND import_id=?');
            $count = 0;
            $finished = $atLimit;
            $this->db->beginTransaction();
            try {
                while (!$finished && $count < $rowsPerTick && $total < $maxTotalRows) {
                    $row = fgetcsv($handle, 0, $delimiter, '"', '');
                    if ($row === false) { $finished = true; break; }
                    if ($row === [null] || $row === []) continue;
                    $data = RegistryCsv::normalize($row, $meta['columns'], $type, $allowCraftOib);
                    $insert->execute([
                        $type,$importId,$data['record_hash'],$data['source_identifier'],$data['name'],
                        $data['oib'],$data['status'],$data['county'],$data['settlement'],$data['address'],$data['activity'],
                    ]);
                    ++$count;
                    ++$total;
                }
                $next = ftell($handle);
                if ($next === false) throw new \RuntimeException('Could not save CSV byte offset');
                $progress->execute([$next,$total,$type,$importId]);
                // Finalize only if the actual stream reached EOF; hitting a quota never publishes a partial batch accidentally.
                if ($finished) {
                    if ($total === 0) throw new \RuntimeException('Refusing to publish an empty source');
                    if ($expectedRows !== null && $total !== $expectedRows) {
                        throw new \RuntimeException('Import count does not match externally verified source count');
                    }
                    $publish = $this->db->prepare('INSERT INTO registry_sources
                       (source_type,active_import_id,source_url,terms_url,coverage,record_count,published_at_utc)
                       VALUES (?,?,?,?,?,?,UTC_TIMESTAMP())
                       ON DUPLICATE KEY UPDATE active_import_id=VALUES(active_import_id),
                       source_url=VALUES(source_url),terms_url=VALUES(terms_url),coverage=VALUES(coverage),
                       record_count=VALUES(record_count),published_at_utc=VALUES(published_at_utc)');
                    $publish->execute([$type,$importId,$sourceUrl,$termsUrl,$coverage,$total]);
                    $this->db->prepare('DELETE FROM registry_import_jobs WHERE source_type=? AND import_id=?')
                        ->execute([$type,$importId]);
                }
                $this->db->commit();
            } catch (\Throwable $error) {
                $this->db->rollBack();
                throw $error;
            }
            return ['status' => $finished ? 'published' : 'staging', 'source' => $type,
                'rows' => $total, 'import_id' => $importId];
        } finally {
            fclose($handle);
            $this->db->query("SELECT RELEASE_LOCK(" . $this->db->quote($lock) . ")");
        }
    }
}
