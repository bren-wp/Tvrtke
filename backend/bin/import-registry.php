<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

// Only consume files acquired lawfully from their published owners.
// Example:
// php backend/bin/import-registry.php --source=udruga --file=/home/account/private/udruge.csv \
//     --source-url=https://data.gov.hr/ckan/dataset/registar-udruga --map=/home/account/private/udruge-map.json
$options = getopt('', [
    'source:', 'file:', 'source-url:', 'terms-url:', 'map:', 'delimiter:',
    'expected-count:', 'coverage:', 'max-rows:', 'batch:', 'allow-craft-oib'
]);
if (!is_array($options) || !isset($options['source'], $options['file'], $options['source-url']) ||
    !is_string($options['source']) || !is_string($options['file']) || !is_string($options['source-url'])) {
    fwrite(STDERR, "Missing --source, --file and --source-url\n");
    exit(2);
}
try {
    $map = [];
    if (isset($options['map'])) {
        if (!is_string($options['map']) || !is_file($options['map']) || !is_readable($options['map'])) {
            throw new InvalidArgumentException('Column-map JSON file is missing');
        }
        $map = json_decode((string) file_get_contents($options['map']), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($map) || array_is_list($map)) throw new InvalidArgumentException('Expected field-to-column JSON object');
    }
    $expected = null;
    if (isset($options['expected-count'])) {
        $raw = filter_var($options['expected-count'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($raw === false) throw new InvalidArgumentException('Invalid expected count');
        $expected = $raw;
    }
    $delimiter = (string) ($options['delimiter'] ?? ',');
    if ($delimiter === 'tab') $delimiter = "\t";
    $result = (new Tvrtke\RegistryImporter(Tvrtke\Database::connect()))->tick(
        $options['source'],
        $options['file'],
        $options['source-url'],
        isset($options['terms-url']) ? (string)$options['terms-url'] : null,
        $map,
        $delimiter,
        (int)($options['batch'] ?? 250),
        (int)($options['max-rows'] ?? 500000),
        $expected,
        (string)($options['coverage'] ?? 'partial'),
        array_key_exists('allow-craft-oib', $options)
    );
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $error) {
    error_log('TVRTKE source import: ' . $error->getMessage());
    fwrite(STDERR, "Import halted: " . $error->getMessage() . PHP_EOL);
    exit(1);
}
