<?php
declare(strict_types=1);

namespace Tvrtke;

/**
 * Strict mapping of authorized public registry CSV exports.
 * No owner/person columns are imported. Craft-owner OIB values are ignored by default.
 */
final class RegistryCsv
{
    public const TYPES = ['obrt', 'udruga', 'zaklada', 'ostalo'];
    private const FIELDS = ['id', 'name', 'oib', 'status', 'county', 'settlement', 'address', 'activity', 'start_date', 'end_date', 'registration_date', 'status_date'];

    /**
     * Preserve original registry dates; do not infer dates from import time.
     */
    private static function date(?string $input): ?string
    {
        if ($input === null || $input === '') return null;
        $value = rtrim(trim($input), '.');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) $format = '!Y-m-d';
        elseif (preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/D', $value)) $format = '!j.n.Y';
        elseif (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4} \d{2}:\d{2}:\d{2} (AM|PM)$/D', $value)) $format = '!n/j/Y h:i:s A';
        else throw new \InvalidArgumentException('Unsupported registry date format');
        $date = \DateTimeImmutable::createFromFormat($format, $value);
        if (!$date || $date->format('Y-m-d') < '1800-01-01' ||
            ($format === '!Y-m-d' && $date->format('Y-m-d') !== $value) ||
            ($format === '!j.n.Y' && $date->format('j.n.Y') !== $value)) {
            throw new \InvalidArgumentException('Invalid registry date');
        }
        return $date->format('Y-m-d');
    }

    public static function type(string $value): string
    {
        if (!in_array($value, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported registry source type');
        }
        return $value;
    }

    public static function httpsUrl(string $value, bool $optional = false): ?string
    {
        if ($optional && $value === '') return null;
        $url = parse_url($value);
        if (!is_array($url) || ($url['scheme'] ?? '') !== 'https' ||
            empty($url['host']) || isset($url['user']) || isset($url['pass']) ||
            isset($url['fragment']) || strlen($value) > 512) {
            throw new \InvalidArgumentException('A public HTTPS source or terms URL is required');
        }
        return $value;
    }

    /**
     * @param list<string> $header
     * @param array<string,string> $mapping canonical field => header label
     * @return array<string,int>
     */
    public static function columns(array $header, array $mapping = []): array
    {
        if ($header === []) throw new \InvalidArgumentException('Missing CSV header');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
        $positions = [];
        foreach ($header as $i => $name) {
            $label = trim($name);
            if ($label === '' || isset($positions[$label])) throw new \InvalidArgumentException('Invalid or duplicate CSV header');
            $positions[$label] = $i;
        }
        foreach ($mapping as $field => $label) {
            if (!in_array($field, self::FIELDS, true) || !is_string($label) || $label === '') {
                throw new \InvalidArgumentException('Invalid column map');
            }
        }
        $indexes = [];
        foreach (self::FIELDS as $field) {
            $label = $mapping[$field] ?? $field;
            if (isset($positions[$label])) $indexes[$field] = $positions[$label];
            elseif (in_array($field, ['id', 'name'], true) || array_key_exists($field,$mapping)) {
                throw new \InvalidArgumentException('Required CSV column is missing: ' . $label);
            }
        }
        return $indexes;
    }

    /** @param list<string|null> $line @param array<string,int> $columns
     *  @return array<string,mixed>
     */
    public static function normalize(array $line, array $columns, string $type, bool $allowCraftOib = false): array
    {
        self::type($type);
        $get = static function (string $field, int $limit) use ($line, $columns): ?string {
            if (!isset($columns[$field])) return null;
            $value = trim((string) ($line[$columns[$field]] ?? ''));
            if ($value === '') return null;
            if (preg_match('//u', $value) !== 1) throw new \InvalidArgumentException('CSV must be valid UTF-8');
            if ((function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) > $limit) {
                throw new \InvalidArgumentException('CSV field too long: ' . $field);
            }
            return $value;
        };
        $identifier = $get('id', 128);
        $name = $get('name', 512);
        if ($identifier === null || $name === null) {
            throw new \InvalidArgumentException('Public identifier and name are required');
        }
        $statusRaw = strtolower($get('status', 100) ?? '');
        $status = match ($statusRaw) {
            '1', 'active', 'aktivan', 'aktivna', 'u radu', 'djeluje' => 'active',
            '0', 'inactive', 'neaktivan', 'neaktivna', 'brisan', 'brisana', 'prestanak djelovanja',
            'odjavljen', 'odjavljena', 'prestao s radom' => 'inactive',
            default => 'unknown',
        };
        $oib = $type === 'obrt' && !$allowCraftOib ? null : $get('oib', 11);
        if ($oib !== null) $oib = Identity::oib($oib);
        return [
            'record_hash' => hash('sha256', $identifier),
            'source_identifier' => $identifier,
            'name' => $name,
            'oib' => $oib,
            'status' => $status,
            'county' => $get('county', 128),
            'settlement' => $get('settlement', 128),
            'address' => $get('address', 255),
            'activity' => $get('activity', 100000),
            'start_date' => self::date($get('start_date', 32)),
            'end_date' => self::date($get('end_date', 32)),
            'registration_date' => self::date($get('registration_date', 32)),
            'status_date' => self::date($get('status_date', 32)),
        ];
    }
}
