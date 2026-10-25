<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

use Tvrtke\RegistryFeedConfig;

function checked(bool $ok,string $message): void {
    if (!$ok) throw new RuntimeException('Feed test failed: '.$message);
}
function rejected(callable $callback): void {
    try { $callback(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Invalid feed was accepted');
}
$good=[
    'enabled'=>true,
    'download_url'=>'https://data.gov.hr/ckan/dataset/b04f3da4-d2b6-4789-9e5f-dc4cc18ae6fe/resource/535a5b96-9f28-409a-ae99-e245964c16c9/download/data.csv',
    'source_url'=>'https://data.gov.hr/ckan/dataset/registar-udruga',
    'columns'=>['id'=>'REG','name'=>'NAZIV','start_date'=>'POČETAK'],
    'delimiter'=>';','max_rows'=>200000,'coverage'=>'partial',
];
$result=RegistryFeedConfig::validate('udruga',$good);
checked($result['delimiter']===';' && $result['columns']['id']==='REG','valid settings');
rejected(fn()=>RegistryFeedConfig::validate('udruga',$good+['enabled'=>false]));
$disabled=$good; $disabled['enabled']=false;
rejected(fn()=>RegistryFeedConfig::validate('udruga',$disabled));
$missing=$good; unset($missing['columns']['id']);
rejected(fn()=>RegistryFeedConfig::validate('udruga',$missing));
$bad=$good; $bad['download_url']='https://127.0.0.1/private.csv';
rejected(fn()=>RegistryFeedConfig::validate('udruga',$bad));
$bad=$good; $bad['download_url']='https://data.gov.hr@127.0.0.1/x.csv';
rejected(fn()=>RegistryFeedConfig::validate('udruga',$bad));
$bad=$good; $bad['download_url']='https://data.gov.hr/ckan/dataset/public.csv#fragment';
rejected(fn()=>RegistryFeedConfig::validate('udruga',$bad));
$bad=$good; $bad['download_url']='https://data.gov.hr/ckan/dataset/public.csv?redirect=https://localhost/';
rejected(fn()=>RegistryFeedConfig::validate('udruga',$bad));
$bad=$good; $bad['columns']=['id'=>'REG','name'=>'NAME','owner_birth_date'=>'OSOBA'];
rejected(fn()=>RegistryFeedConfig::validate('udruga',$bad));
$bad=$good; $bad['coverage']='complete';
rejected(fn()=>RegistryFeedConfig::validate('udruga',$bad));
$ok=$good; $ok['expected_rows']=1234; $ok['coverage']='complete';
checked(RegistryFeedConfig::validate('udruga',$ok)['expected_rows']===1234,'known expected rows');
echo "Official feed validation tests: OK\n";
