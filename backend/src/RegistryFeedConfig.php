<?php
declare(strict_types=1);

namespace Tvrtke;

/**
 * Trusted open-data feed definitions. All configuration is private, CLI-only
 * and limited to HTTPS downloads hosted by official public-data portals.
 */
final class RegistryFeedConfig
{
    /** @return array<string,mixed> */
    public static function validate(string $type, array $raw): array
    {
        RegistryCsv::type($type);
        if (($raw['enabled'] ?? false) !== true) {
            throw new \InvalidArgumentException('Official feed not explicitly enabled: ' . $type);
        }
        $downloadUrl = self::trustedDownloadUrl($raw['download_url'] ?? null);
        $sourceUrl = RegistryCsv::httpsUrl((string)($raw['source_url'] ?? ''));
        $termsUrl = RegistryCsv::httpsUrl((string)($raw['terms_url'] ?? ''),true);
        if (!isset($raw['columns']) || !is_array($raw['columns']) ||
            !isset($raw['columns']['id'],$raw['columns']['name'])) {
            throw new \InvalidArgumentException('Inspect official CSV and map id/name columns before enabling feed');
        }
        $columns = $raw['columns'];
        $allowed = ['id','name','oib','status','county','settlement','address','activity','start_date','end_date','registration_date','status_date'];
        foreach ($columns as $field => $column) {
            if (!in_array($field,$allowed,true) || !is_string($column) ||
                $column === '' || strlen($column) > 150) {
                throw new \InvalidArgumentException('Invalid official CSV column mapping');
            }
        }
        $delimiter = (string)($raw['delimiter'] ?? ',');
        if ($delimiter === 'tab') $delimiter = "\t";
        if (!in_array($delimiter,[',',';', "\t"],true)) {
            throw new \InvalidArgumentException('Unsupported CSV separator');
        }
        $maxBytes = filter_var($raw['max_bytes'] ?? 50000000,FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1024,'max_range'=>250000000]]);
        $maxRows = filter_var($raw['max_rows'] ?? 500000,FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>100,'max_range'=>2000000]]);
        $batch = filter_var($raw['batch'] ?? 300,FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>500]]);
        $pages = filter_var($raw['pages_per_run'] ?? 5,FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>10]]);
        $budget = filter_var($raw['budget_seconds'] ?? 18,FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>3,'max_range'=>40]]);
        foreach (['max_bytes'=>$maxBytes,'max_rows'=>$maxRows,'batch'=>$batch,
                  'pages_per_run'=>$pages,'budget_seconds'=>$budget] as $name=>$value) {
            if ($value === false) throw new \InvalidArgumentException('Invalid feed quota: '.$name);
        }
        $count = $raw['expected_rows'] ?? null;
        if ($count !== null) {
            $count = filter_var($count,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($count === false || $count > $maxRows) {
                throw new \InvalidArgumentException('Unverified or oversized expected row count');
            }
        }
        $coverage = (string)($raw['coverage'] ?? 'partial');
        if (!in_array($coverage,['partial','complete'],true) ||
            ($coverage === 'complete' && $count === null)) {
            throw new \InvalidArgumentException('Verified expected count is required for complete coverage');
        }
        return [
            'download_url'=>$downloadUrl,'source_url'=>$sourceUrl,'terms_url'=>$termsUrl,
            'columns'=>$columns,'delimiter'=>$delimiter,'max_bytes'=>$maxBytes,
            'max_rows'=>$maxRows,'batch'=>$batch,'pages_per_run'=>$pages,'budget_seconds'=>$budget,
            'expected_rows'=>$count,'coverage'=>$coverage
        ];
    }

    public static function trustedDownloadUrl(mixed $url): string
    {
        if (!is_string($url) || strlen($url) > 512 ||
            !preg_match('~^https://~i',$url)) {
            throw new \InvalidArgumentException('HTTPS official open-data CSV URL required');
        }
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' ||
            !in_array($host,['data.gov.hr','p-data.gov.hr'],true) ||
            isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['port']) || isset($parts['fragment']) || isset($parts['query']) ||
            !str_starts_with((string)($parts['path'] ?? ''),'/ckan/dataset/') ||
            !str_ends_with(strtolower((string)($parts['path'] ?? '')),'.csv')) {
            throw new \InvalidArgumentException('Download URL must point to an approved official CKAN CSV resource');
        }
        return $url;
    }
}
