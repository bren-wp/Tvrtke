# TVRTKE — Poslovni registar Hrvatske

Razvojna verzija zajedničkog sustava za **tvrtke.com.hr**: centralni PHP/MySQL API, WordPress plugin i Windows desktop klijent. Izvor podataka je javni API Sudskog registra RH **v3.0.4**. Ciljana platforma je **MyDataKnox KNOX L**, uz ograničen kapacitet shared hostinga.

> **Status: v0.4.0 – razvojno izdanje, nije spremno za produkciju.** Nije postavljeno na tvrtke.com.hr; stvarna sinkronizacija nije provjerena bez tvojih OAuth2 pristupnih podataka i hostinga. Trenutačni uvoz sadrži pet odabranih skupova (`subjekti`, `tvrtke`, `sjedista`, `pravni_oblici`, `predmeti_poslovanja`) i može biti namjerno ograničen brojem redaka. Time baza nije potpuni Sudski registar.

## Komponente

- [`backend/`](backend/) — PHP 8.2+, PDO MySQL, OAuth2 Sudreg klijent, verzionirana baza, cron importer i javni JSON API.
- [`wordpress/tvrtke-registar/`](wordpress/tvrtke-registar/) — dodatak za WordPress s postavkom API adrese i shortcodeom `[tvrtke_pretraga]`.
- [`windows/Tvrtke.Desktop/`](windows/Tvrtke.Desktop/) — početni nativni WPF desktop klijent za Windows (.NET 10).
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — točni koraci instalacije na cPanel shared hosting i sigurnosna pravila.
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — poznata ograničenja i sljedeće razvojne faze.

## Kako radi

1. Cron pokreće `backend/bin/sync.php` (jedna ograničena API stranica po izvođenju).
2. Importer dohvaća najnoviji dostupni Sudreg `snapshot_id` i drži ga konstantnim tijekom uvoza.
3. Podaci se pohranjuju u MySQL s ključem `(snapshot_id, MBS)`. Napredak se atomarno sprema sa svakom serijom.
4. Tek nakon uvoza svih pet skupova objavljuje se nova aktivna snimka u `app_state`.
5. Windows i WordPress čitaju **isti** skup s API-ja, bez izravne veze na MySQL iz Windows aplikacije.
6. Zasebni cron `backend/bin/prune.php` postupno briše stare snimke radi kontrole prostora.

## Brzi pregled API-ja

| Ruta | Pristup | Namjena |
| --- | --- | --- |
| `GET /v1/health` | Javno | Status PHP servisa (ne provjerava bazu) |
| `GET /v1/subjects?q=...&page=1&limit=20` | Javno | Pretraživanje naziva prefiksom, OIB-a ili MBS-a |
| `GET /v1/subjects/{mbs}` | Javno | Lokalni osnovni podaci subjekta |
| `GET /v1/status` | Admin Bearer token | Interni status importera |

### Lokalna provjera

```sh
php -l backend/public/index.php
php backend/tests/identity.php
php -S 127.0.0.1:8080 -t backend/public backend/public/router.php
# u drugoj konzoli: curl http://127.0.0.1:8080/v1/health
```

Za stvarnu pretragu potrebna je MySQL baza s instaliranom shemom i objavljena snimka.

## Izvori

- [Sudreg – službeni changelog](https://sudreg-data.gov.hr/api/javni/dokumentacija/changelog)
- [Sudreg – SwaggerHub v3.0.4](https://app.swaggerhub.com/apis/mpu.gov.hr/sudreg_javni/v3.0.4)
- Dokumentacija korisnika *Upute za razvojne inženjere, v3.0.0* i OpenAPI v3.0.4 dostavljeni za projekt.

## Licenca

MIT. Prije javne objave velike baze treba provjeriti uvjete korištenja službenih podataka, osobne podatke i GDPR te odgovarajuće obveze informiranja korisnika.

## Novosti v0.2.0 (razvojna grana)

- Dodatna migracija [`002_enrichment.sql`](backend/database/002_enrichment.sql) za pravne oblike i registrirane djelatnosti.
- `Sync` sada preuzima i `pravni_oblici` i `predmeti_poslovanja` u ograničenim serijama, koristeći `expand_relations=true`.
- API podržava filtre `county`, `settlement`, `status`, `activity`, `legal_form`; detalji subjekta uključuju listu djelatnosti.
- WordPress shortcode prikazuje kartice, pojedinačne profile i mobilno prilagodljive filtre.
- Windows klijent nudi filtre i učitavanje detalja pri odabiru subjekta.

Upute za nadogradnju i ograničenja: [`docs/RELEASE-0.2.md`](docs/RELEASE-0.2.md). **Ova grana nije produkcijsko izdanje.**

## Izdavanje i Windows instalacija (v0.4.0)

- `windows/installer/Tvrtke.iss` — Inno Setup 6 skripta za instalaciju po korisniku.
- `.github/workflows/release.yml` — Windows self-contained single-file x64 EXE, Setup EXE, WordPress ZIP, izvorni ZIP i SHA256SUMS.
- Nakon što `release/v0.4.0` grana primi commit, workflow izrađuje [GitHub razvojno izdanje](https://github.com/bren-wp/Tvrtke/releases/tag/v0.4.0).
- Windows ima izvoz trenutačno prikazanih rezultata u CSV (ne cijelog registra).
- Detalji i ograničenja: [`docs/RELEASE-0.4.md`](docs/RELEASE-0.3.md).
- Binarke **nisu digitalno potpisane**, stvarna Sudreg sinkronizacija i produkcijska instalacija nisu provjerene.

## Pouzdanost prikaza u v0.4.0

Oznaka djelomičnog uvoza pripada **objavljenoj** snimci. Ako započne sinkronizacija sljedeće snimke, upozorenje ostaje vidljivo. Importer odbija objaviti nepotpune ili prazne osnovne skupove. Windows ispravlja pogreške paginacije i hvata nevaljan JSON bez rušenja sučelja.
