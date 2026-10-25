# TVRTKE v0.2.0 – detalji i filtriranje (razvojna verzija)

## Dodatni podaci iz Sudreg API-ja v3.0.4

- `pravni_oblici` sa `expand_relations=true`: šifra, naziv i kratica pravnog oblika.
- `predmeti_poslovanja` sa `expand_relations=true`: popis djelatnosti subjekta i NKD podaci ako su navedeni.
- `subjekti`, `tvrtke`, `sjedista`: prethodno podržani skupovi.

**Važno:** `predmeti_poslovanja` opisuje registrirane predmete poslovanja, a ne nužno jednu *pretežitu djelatnost*. Nismo pretpostavili da su to identični podaci. U ovoj verziji ne postoji pretraga djelatnosti obrta ili puni FINA financijski podaci.

## REST API

`GET /v1/subjects?q=&county=&settlement=&activity=&legal_form=&status=&page=1&limit=20`

Parametri:
- `q`: prefiks naziva ili puni OIB/MBS (do 100 znakova).
- `county`, `settlement`: prefiks županije / naselja, do 100 znakova.
- `activity`: prefiks teksta registrirane djelatnosti / NKD naziva ili točna NKD šifra.
- `legal_form`: šifra pravnog oblika, najviše 12 znamenki.
- `status`: `1` aktivan ili `0` neaktivan.
- `page`: 1–10000; `limit`: 1–50.

Svaka stranica sadrži `items`, `page`, `limit`, `has_more`, `snapshot_id` i `partial`.

`GET /v1/subjects/{mbs}` vraća `item` uključujući `activities`, pravni oblik i zastavicu `partial`. Nepostojeći subjekt vraća HTTP 404.

Ako je bilo koji dataset aktivne snimke skraćen ograničenjem `TVRTKE_IMPORT_ROW_LIMIT`, `partial=true`; to NIJE cjeloviti registar.

## Nadogradnja baze

1. Napraviti sigurnosnu kopiju baze i privatne konfiguracije.
2. Izvršiti `backend/database/002_enrichment.sql` **jednom** nakon `001_initial.sql` (zadnji `CREATE INDEX` nije idempotentan).
3. Objaviti nadograđene PHP datoteke prije idućeg cron pokretanja. Migracija i kod moraju odgovarati istoj verziji.
4. Postojeće instalacije s tri dovršena skupa dobit će dva nova skupa u `sync_progress` i postupno ih preuzeti; API će tijekom dopune vraćati trenutno raspoložive podatke.
5. Provjeriti `GET /v1/status` i usporediti `snapshot_id` te `truncated` prije javne objave.

**KNOX L:** Baza, indeksi, pohrana i cron kvote su ograničeni. Zadani limit po skupu (10 000 redaka) štiti hosting od nekontroliranog rasta, ali ne jamči međusobnu potpunost uvezenih podataka. Sinkronizacija stvarnog Sudrega i instalacija na hosting još nisu provedene.

## Sigurnost i sukladnost

Ne objavljivati `config.local.php`, client secret ili admin token. Windows ne pristupa MySQL-u nego HTTPS API-ju. Provjeriti stvarna prava redistribucije i zaštitu osobnih podataka prije produkcijske objave. Dodatne kategorije podataka mogu zahtijevati dodatnu zaštitu i pravnu analizu.
