# TVRTKE v0.4.0 — razvojno izdanje

**Status: prerelease.** Windows/WordPress/backend nisu instalirani niti end-to-end testirani na tvrtke.com.hr i MyDataKnox hostingu (bez Sudreg OAuth2 vjerodajnica).

## Ispravci

- Oznaka **djelomičnog uvoza** zapisuje se uz objavljenu snimku u `app_state`. Više se ne gubi početkom uvoza iduće snimke.
- Zabranjena je objava praznih i nedovršenih snimki.
- Dodani su unit testovi za validaciju objave: nedostajući, duplicirani, nepotpuni skupovi i prazan osnovni skup.
- Windows: provjera HTTPS adrese, JSON greške ne ruše async event, neuspješna promjena stranice ne mijenja broj stranice; zastarjeli zahtjevi za detaljima se otkazuju.
- WordPress: jasna poruka kada prva snimka još nije dostupna; izbjegnuta fatalna greška bez `mbstring`.
- Migracija `backend/database/003_publication_state.sql` inicijalizira konzervativni prikaz djelomičnosti za postojeću instalaciju.

## Datoteke izdanja

- `TVRTKE-Portable-v0.4.0-win-x64.exe` — portable program s uključenim .NET runtimeom.
- `TVRTKE-Setup-v0.4.0-win-x64.exe` — instalacijski program po korisniku i deinstalacija.
- `TVRTKE-WordPress-v0.4.0.zip` — WordPress plugin.
- `TVRTKE-v0.4.0-source.zip` — cjelokupni izvorni kod.
- `SHA256SUMS.txt` — SHA-256 otisci.

**Windows datoteke nisu digitalno potpisane.** SmartScreen može upozoriti. Na postojećem hostingu prije `sync.php` primijeniti SQL migraciju 003. Za produkciju trebaju provjere dozvola Sudreg API-ja, sigurnosti i performansi na KNOX L, pravnih uvjeta i testovi stvarnih korisničkih scenarija.
