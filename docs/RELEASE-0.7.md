# TVRTKE v0.7.0 — objedinjena pretraga hrvatskih registara

**Razvojno izdanje; nije potvrđena instalacija na stvarni MyDataKnox KNOX L hosting niti potpuna nacionalna baza.**

## Korisničke funkcionalnosti

- Windows aplikacija: poslovni imenik Sudskog registra, obrta, udruga i zaklada (ovisno o dostupnim službenim izvorima), pretraga, filtri statusa i kategorije, profili, favoriti, CSV izvoz.
- Prikaz aktivnih i neaktivnih subjekata, s datumima osnivanja i brisanja kada ih odgovarajući javni registar pruža.
- WordPress plugin: responzivna tražilica, Gutenberg blok i shortcode, službeni naziv registra, status, registracijski broj i datumi gdje postoje.

## Centralna baza i automatsko ažuriranje

- OAuth2 Sudreg Client ID/Secret koriste se **isključivo u privatnom poslužiteljskom PHP CLI procesu**, nikad u aplikaciji, WordPress pluginu, javnim URL-ovima ili repozitoriju.
- Sudreg koristi fiksirani `snapshot_id`, `only_active=false`, validaciju `X-Total-Count`, paginaciju i transakcijsku objavu. Nepravilni ili prekinuti skupovi ne zamjenjuju prethodno objavljene.
- Novi operator-only CLI: `backend/bin/sudreg-preflight.php --online`, `backend/bin/sync.php`.
- Dodatni odobreni javni CSV izvori koriste verzionirana izdanja: `backend/bin/sync-public-feeds.php --source=udruga` / `--source=zaklada`, zapis izvora i odvojeno vrijeme objave.
- Službeni CSV zaglavlja udruga i zaklada provjerena su 25.10.2026. Primjer privatnog manifesta ima pripremljene stvarne mape stupaca; automatizacija se aktivira tek nakon instalacije na autorizirani hosting. Pokazni registar udruga sadržavao je 71 333 zapisa (43 171 aktivnih, 26 373 brisanih, 1 789 prestalih). Zaklade povremeno vraćaju HTTP 500 pa uvoz mora ostati fail-safe. Obrtni registar zasad nema verificiran potpuni nacionalni bulk API.
- Nove migracije: `005_sudreg_full_coverage.sql` i `006_registry_lifecycle.sql` i `007_registry_details.sql`. Izvršiti nakon 001–004 i obavezne sigurnosne kopije.
- Opcionalna arhiva izvornih Sudreg redaka isključena je prema zadanim postavkama zbog diska, privatnosti i činjenice da javni Sudreg API ne pruža punu povijest svih promjena.

## Provjere

GitHub Actions provodi PHP lint, validaciju izvora, stvarni MariaDB integracijski uvoz, Windows .NET build, Windows pakiranje i WordPress Plugin Check. **Automatski testovi nisu potvrda dopuštene ponovne uporabe osobnih podataka ili produkcijskih performansi.**

## Paketi

- `TVRTKE-Portable-v0.7.0-win-x64.exe`
- `TVRTKE-Setup-v0.7.0-win-x64.exe`
- `TVRTKE-WordPress-v0.7.0.zip`
- `TVRTKE-v0.7.0-source.zip`
- `SHA256SUMS.txt`

## Granice

Bez stvarnih privatnih Sudreg vjerodajnica pohranjenih na hostingu, cPanel/SSH/cron pristupa i MySQL baze nije moguće tvrditi da su stvarni podaci uvezeni. KNOX L može biti nedostatan za potpunu povijesnu arhivu ili sve dostupne API datasete. Prije WordPress.org predaje obavezne su javno dostupne informacije o operateru vanjskog servisa, privatnost i uvjeti korištenja. Windows EXE nije potpisan.

- Sigurnost javnog API-ja: zaseban MySQL korisnik sa SELECT ovlastima samo nad dopuštenim tablicama, bez pristupa izvornim arhivama i bez INSERT/UPDATE/DELETE.
- Datumi `datum_osnivanja`, `datum_brisanja`, `datum_upisa`, `datum_statusa` dolaze iz različitih izvornih polja i ne smiju se poistovjetiti.
