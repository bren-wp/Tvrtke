<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Tvrtke\RegistryCsv;

function check(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}

function rejects(callable $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('Expected InvalidArgumentException');
}

$header = ["\xEF\xBB\xBF" . 'Maticni broj','Naziv','OIB','Status','Zupanija','Mjesto'];
$map = ['id'=>'Maticni broj','name'=>'Naziv','oib'=>'OIB','status'=>'Status','county'=>'Zupanija','settlement'=>'Mjesto'];
$columns = RegistryCsv::columns($header,$map);
$line = ['00012345','Primjer test obrta','01234567890','U radu','Primorsko-goranska','Rijeka'];
$obrt = RegistryCsv::normalize($line,$columns,'obrt');
check($obrt['source_identifier'] === '00012345','Native identifier must preserve leading zeros');
check($obrt['oib'] === null,'Craft owner OIB must not be copied by default');
check($obrt['status'] === 'active','Active status mapping');
check($obrt['record_hash'] === hash('sha256','00012345'),'Stable hash');
$association = RegistryCsv::normalize($line,$columns,'udruga');
check($association['oib'] === '01234567890','Legal entity OIB preserved');
check($association['name'] === 'Primjer test obrta','Name preserved');
check(RegistryCsv::normalize($line,$columns,'obrt',true)['oib'] === '01234567890','Explicit craft OIB opt-in');
check(RegistryCsv::httpsUrl('https://data.gov.hr/ckan/dataset/registar-udruga') !== null,'HTTPS provenance');
rejects(fn() => RegistryCsv::type('osobe'));
rejects(fn() => RegistryCsv::httpsUrl('http://example.test/dataset'));
rejects(fn() => RegistryCsv::httpsUrl('https://user:password@data.gov.hr'));
rejects(fn() => RegistryCsv::columns(['id','id','name']));
rejects(fn() => RegistryCsv::columns(['id'],[]));
rejects(fn() => RegistryCsv::normalize(['','','','','',''],$columns,'udruga'));
rejects(fn() => RegistryCsv::normalize(['abc','Naziv','nije-oib','','',''],$columns,'udruga'));
rejects(fn() => RegistryCsv::normalize(['x',str_repeat('N',513),'','','',''],$columns,'udruga'));
$withDates = $line;
$withDates[] = '15.3.2020.';
$withDates[] = '2024-09-30';
$dateCols = $columns + ['start_date'=>6,'end_date'=>7];
$dateRow = RegistryCsv::normalize($withDates,$dateCols,'udruga');
check($dateRow['start_date']==='2020-03-15','Croatian founding date');
check($dateRow['end_date']==='2024-09-30','ISO cessation date');
check(RegistryCsv::normalize(array_replace($withDates,[6=>'31.2.2024.']),$dateCols,'udruga')['start_date'] === null,
    'Invalid official dates must remain unknown, never roll over into fabricated dates');
check($obrt['start_date'] === null && $obrt['end_date'] === null,'Missing dates must stay null');
$official = ['REG-123','Udruga test','AKTIVAN','1/12/2020 12:00:00 AM','2/14/2022 09:31:27 PM'];
$officialCols = ['id'=>0,'name'=>1,'status'=>2,'registration_date'=>3,'status_date'=>4];
$parsed = RegistryCsv::normalize($official,$officialCols,'udruga');
check($parsed['registration_date']==='2020-01-12','Official CKAN US locale timestamp parse');
check($parsed['status_date']==='2022-02-14','Official CKAN timestamp day');
check(RegistryCsv::normalize(['2','Test','PRESTANAK DJELOVANJA'],['id'=>0,'name'=>1,'status'=>2],'udruga')['status']==='inactive','Official cessation status');
check(RegistryCsv::normalize(['2','Test','Aktivan','2/30/2020 12:00:00 AM'],
    ['id'=>0,'name'=>1,'status'=>2,'registration_date'=>3],'udruga')['registration_date'] === null,
    'Impossible official US date must remain null');
echo "Registry CSV checks: OK\n";
