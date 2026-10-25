<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__).'/src/bootstrap.php';

$options = getopt('', ['source:', 'inspect:', 'delimiter:']);
try {
    if (isset($options['inspect'])) {
        $file=(string)$options['inspect'];
        if ($file==='' || !is_file($file) || !is_readable($file)) {
            throw new InvalidArgumentException('Inspect an existing private CSV file');
        }
        $delimiter = (string)($options['delimiter'] ?? ',');
        if ($delimiter==='tab') $delimiter="\t";
        if (!in_array($delimiter,[',',';', "\t"],true)) throw new InvalidArgumentException('Invalid delimiter');
        $handle=fopen($file,'rb');
        if ($handle===false) throw new RuntimeException('Cannot inspect CSV');
        try {
            $header=fgetcsv($handle,0,$delimiter,'"','');
            if ($header===false || !is_array($header)) throw new RuntimeException('CSV header missing');
            $header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]) ?? $header[0];
            echo json_encode(['header'=>$header],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), PHP_EOL;
            exit(0);
        } finally {
            fclose($handle);
        }
    }
    $type=(string)($options['source'] ?? '');
    Tvrtke\RegistryCsv::type($type);
    $manifest=Tvrtke\Config::get('TVRTKE_FEEDS_CONFIG',
        dirname(__DIR__).'/feeds.local.json');
    if (!is_file($manifest) || !is_readable($manifest)) {
        throw new RuntimeException('Missing private feeds.local.json configuration');
    }
    $settings=json_decode((string)file_get_contents($manifest),true,32,JSON_THROW_ON_ERROR);
    if (!is_array($settings) || !isset($settings[$type]) || !is_array($settings[$type])) {
        throw new RuntimeException('No administrator-approved manifest for selected source');
    }
    $cache=Tvrtke\Config::get('TVRTKE_FEEDS_CACHE_DIR',dirname(__DIR__).'/private-feeds');
    if (!is_dir($cache) && !mkdir($cache,0700,true) && !is_dir($cache)) {
        throw new RuntimeException('Cannot create private source-cache directory');
    }
    $result=(new Tvrtke\RegistryFeedSync(Tvrtke\Database::connect()))
        ->tick($type,$settings[$type],$cache);
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
} catch (Throwable $error) {
    error_log('TVRTKE public feed sync: '.get_class($error).' '.$error->getMessage());
    fwrite(STDERR,"Uvoz službenog CSV izvora nije dovršen. Provjerite privatni log.\n");
    exit(1);
}
