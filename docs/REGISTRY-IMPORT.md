# Uvoz podataka iz dodatnih registara — TVRTKE

## Preduvjeti

Instalirajte SQL migracije `001_initial.sql`, `002_enrichment.sql`, `003_publication_state.sql`, **`004_registry_sources.sql`** redom. PHP 8.2+, MySQL/MariaDB i izvršavanje PHP CLI skripti su obavezni.

Preuzmite isključivo podatke koje pružatelj dopušta ponovno koristiti. CSV datoteku držite u privatnom direktoriju, **izvan web-roota**, nikad u `public_html` ili GitHub repozitoriju.

### Primjer: izvor s kanonskim stupcima

CSV zaglavlje mora imati `id,name`. Opcionalni stupci: `oib,status,county,settlement,address,activity`.

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
