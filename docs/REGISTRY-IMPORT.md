# Uvoz podataka iz dodatnih registara — TVRTKE

## Preduvjeti

Instalirajte SQL migracije `001_initial.sql`, `002_enrichment.sql`, `003_publication_state.sql`, **`004_registry_sources.sql`**, **`005_sudreg_full_coverage.sql`** i **`006_registry_lifecycle.sql`** i **`007_registry_details.sql`** redom. PHP 8.2+, MySQL/MariaDB i izvršavanje PHP CLI skripti su obavezni.

Preuzmite isključivo podatke koje pružatelj dopušta ponovno koristiti. CSV datoteku držite u privatnom direktoriju, **izvan web-roota**, nikad u `public_html` ili GitHub repozitoriju.

### Primjer: izvor s kanonskim stupcima

CSV zaglavlje mora imati `id,name`. Opcionalni stupci: `oib,status,county,settlement,address,activity,start_date,end_date`.

```sh
php backend/bin/import-registry.php \
  --source=udruga \
  --file=/home/ACCOUNT/private/approved-udruge.csv \
  --source-url=https://data.gov.hr/ckan/dataset/registar-udruga \
  --coverage=partial \
  --delimiter=, \
  --batch=250
```

> Ovo je primjer formata ulaza, **nije tvrdnja da sirovi CKAN CSV koristi navedeno zaglavlje**. Prvo provjerite stvarne stupce i odgovarajući resurs.

### Različiti nazivi stupaca

Mapa JSON pretvara zaglavlja stvarne datoteke u nazive sustava. Datoteka mora biti privatna, primjer:

```json
{
  "id": "Identifikator izvora",
  "name": "Naziv subjekta",
  "county": "Zupanija",
  "settlement": "Naselje",
  "status": "Status"
}
```

Navedeni nazivi su ilustrativni — **moraju se prilagoditi stvarnoj službenoj datoteci**.

```sh
php backend/bin/import-registry.php --source=zaklada \
  --file=/home/ACCOUNT/private/authorized-zaklade.csv \
  --source-url=https://data.gov.hr/ckan/dataset/registar-zaklada \
  --map=/home/ACCOUNT/private/map.json \
  --delimiter=';'
```

Importer namjerno preuzima samo tražene stupce javnog subjekta. Informacije o zastupnicima, osnivačima i drugim fizičkim osobama nisu podržane. Za `obrt` se OIB ignorira dok se posebno ne odobri uvoz te kategorije podataka.

## Nastavak prekinutog uvoza

- Jedno pokretanje učitava do 250 redaka (zadano). Ponovite istu naredbu kroz cron, s istim nazivom, datotekom i parametrima, do odgovora `published`.
- Napredak se bilježi po bajtovima u `registry_import_jobs` pa se već učitani zapisi ne čitaju ponovno.
- Datoteka se za vrijeme uvoza ne smije mijenjati. Promijenjena veličina ili mtime blokira nastavak.
- Novo izdanje izvora ne prepisuje objavljene podatke sve dok cijela nova datoteka ne prođe provjere.
- Limit `--max-rows` zadan je na 500000, po potrebi se može povisiti do 2000000, ali to **ne jamči** da hosting ima prostora. Kada je limit dosegnut prije kraja datoteke, objava se odbija.
- Uvoz s `--coverage=complete` dopušten je samo uz `--expected-count=N` s provjerenim službenim brojem zapisa u toj datoteci. Potpunost se ne smije izmišljati.
- Duplikati unutar istog izvora i neispravni identifikatori završavaju greškom bez djelomične objave.
- Stare objavljene serije mogu se obrisati u malim serijama kroz `backend/bin/prune-registry.php`.

## KNOX L optimizacija

Održavajte slobodan prostor dovoljan za novu seriju i eventualnu staru seriju tijekom atomic publish. Za veoma velike registre potreban je jači hosting ili vanjski podatkovni servis. Umjesto velikih učitavanja koristite male CLI serije i cPanel cron po dogovorenom rasporedu.

Nikad nemojte slati OAuth2 Client Secret ili MySQL lozinku na WordPress/frontend ili u javni repozitorij.

## Oporavak neuspjelog uvoza

Ako službeni CSV sadrži duplikate, pogrešne stupce ili nevaljane zapise, importer zaustavlja posao bez promjene već objavljenog izvora. Popravite izvor i zatim **namjerno uklonite isključivo njegovu neobjavljenu seriju**:

```sh
php backend/bin/abort-registry-import.php --source=udruga --confirm=DELETE_STAGING
```

Ponavljajte naredbu dok ne javi da nema aktivnog uvoza. Svaki poziv briše najviše 250 redaka i **nikada ne briše aktivno objavljenu seriju**. Ako želite samo nastaviti isti uvoz, nemojte pokretati ovu naredbu.

## Automatsko ažuriranje udruga i zaklada

Projekt sadrži administrativni CLI mehanizam `backend/bin/sync-public-feeds.php` za službene CKAN CSV izvoze. Primjerni manifest `backend/feeds.example.json` navodi provjerene izvore za udruge i zaklade s portala data.gov.hr; **nije automatski uključen**, jer je imena stupaca su 25.10.2026. provjerena u službenom CSV-u, ali format, obuhvat i dozvolu treba provjeriti na svakom novom izvozu. Izvoz označen `Osobe` ne koristiti. U programu se ne smiju prikazivati podaci fizičkih osoba koji nisu potrebni za poslovni imenik.

1. Napraviti privatni `backend/feeds.local.json` iz primjera; ne kopirati u `public_html`, web-accessible `api/public` ili Git.
2. Preuzeti službeni CSV iz odobrenog izvora u privremenu privatnu mapu i utvrditi stvarne nazive stupaca:
   ```sh
   php backend/bin/sync-public-feeds.php --inspect=/home/ACCOUNT/private/udruge.csv --delimiter=,
   ```
   Parametar `--inspect` ispisuje samo **nazive stupaca**, ne sadržaj fizičkih osoba.
3. U `columns` unijeti stvarni `id` i `name`, te opcionalno `status`, `county`, `settlement`, `activity`, `start_date`, `end_date`. Kolone koje ne postoje **ne izmišljati**. Ne dodavati podatke o zastupnicima ili vlasnicima.
4. Primjer sadrži `"enabled": true` za dva službena kataloška resursa s provjerenim zaglavljima. Kopiranje privatnog manifesta i aktiviranje cron zadatka predstavljaju izričitu administratorsku odluku. Ako službeni CSV promijeni strukturu ili sadrži nevaljane podatke, importer obustavlja objavu bez mijenjanja aktivne baze. Potpunost (`coverage: complete`) dopuštena je samo uz neovisno potvrđen `expected_rows`.
5. Pozivati kratku sinkronizaciju za pojedini izvor iz cPanela:
   ```cron
   */10 * * * * /usr/local/bin/php /home/ACCOUNT/tvrtke-backend/bin/sync-public-feeds.php --source=udruga >/dev/null 2>&1
   */10 * * * * /usr/local/bin/php /home/ACCOUNT/tvrtke-backend/bin/sync-public-feeds.php --source=zaklada >/dev/null 2>&1
   ```
   Svaki poziv nastavlja postojeći uvoz; za provjeru ima li novog izvoza server ne preuzima identičan CSV svakih deset minuta. Nakon uspješne objave kopija izvoza i napredak ostaju privatni.
6. Izvor se objavljuje tek nakon potpune validacije svih redaka. Izvorni URL i datum objave izlaze u `GET /v1/sources`.

Službene nacionalne podatke o svim obrtima **nije moguće deklarirati preuzetima** dok nije potvrđen bulk izvor ili autorizirani pristup takvom izvoru. Ne zaobilaziti ograničenja Portala obrtnog registra. Ažuriranje Sudreg API-ja i ovih feedova izvršava se potpuno na privatnom poslužitelju; Windows i WordPress ne dobivaju ključeve ni pristup MySQL-u.

Prilikom povećanja limita tijekom Sudreg uvoza i dalje su potrebni kontrola kvote i dostupnost iste službene snimke; novi limit sam po sebi ne jamči dovršetak prije isteka snimke.

### Provjereni podaci hrvatskih registara (25.10.2026.)

- **Udruge CTS CSV:** 71 333 redaka u trenutačnom izvoru; statusi `AKTIVAN` (43 171), `BRISAN` (26 373), `PRESTANAK DJELOVANJA` (1 789). To je stanje pregledanog izvoza, ne trajno jamstvo nacionalne pokrivenosti. Mapiranje koristi `REGISTARSKI_BROJ`, `NAZIV`, `OIB` pravne osobe, `STATUS`, `ZUPANIJA`, `SJEDISTE`, `OPIS_DJELATNOSTI`, `DATUM_OSNIVACKE_SKUPSTINE`, `DATUM_UPISA` i `DATUM_STATUSA`.
- **Zaklade CTS CSV:** potvrđeno zaglavlje: `REGISTARSKI_BROJ`, `NAZIV`, `STATUS`, `OIB_ZAKLADE`, `SJEDISTE`, `DATUM_UPISA` i `DATUM_STATUSA`. Broj zapisa nije potvrđen jer su agregatni zahtjevi povremeno vraćali pogrešku HTTP 500; importer pri tome ne objavljuje nepotpun CSV.
- Datumski format u izvozu udruga je mjesec/dan/godina s 12-satnim vremenom, npr. struktura `M/D/YYYY hh:mm:ss AM`. Parser uz ISO i hrvatski datum prihvaća i ovaj službeni format.
- `OPIS_DJELATNOSTI` kod pojedinih udruga premašuje 33 000 znakova. Za te podatke koristi se `MEDIUMTEXT`, dok rezultati pretrage šalju skraćeni sažetak; detalj može sadržavati puni javni opis. Ne uvoziti osobne podatke iz zasebnih resursa `Osobe`.
- `DATUM_OSNIVACKE_SKUPSTINE` je datum osnivačke skupštine, a ne nužno datum pravnog upisa. `DATUM_UPISA` i `DATUM_STATUSA` ostaju zasebna polja.
