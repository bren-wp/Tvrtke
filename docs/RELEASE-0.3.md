# TVRTKE v0.3.0 — razvojno izdanje

**Status: prerelease.** Windows klijent i WordPress plugin koriste isti centralni PHP/MySQL API.
Izdanje još nije postavljeno na `tvrtke.com.hr`, niti je provedeno stvarno preuzimanje podataka
uz Sudreg OAuth2 pristup na korisničkom hostingu. Prije javnog korištenja potrebna je sigurnosna,
pravna i funkcionalna provjera te mjerenje kapaciteta MyDataKnox KNOX L paketa.

## Datoteke izdanja

| Datoteka | Svrha |
| --- | --- |
| `TVRTKE-Portable-v0.3.0-win-x64.exe` | Samostalna 64-bitna Windows aplikacija bez instalacije (.NET runtime uključen) |
| `TVRTKE-Setup-v0.3.0-win-x64.exe` | Instalacijski program za Windows (po korisniku, bez administratorskih prava) |
| `TVRTKE-WordPress-v0.3.0.zip` | Instalacijski WordPress plugin |
| `TVRTKE-v0.3.0-source.zip` | Izvorni kod, backend, WordPress i Windows projekt |
| `SHA256SUMS.txt` | SHA-256 kontrolni zbrojevi svih prethodnih paketa |

## Novosti

- Automatska priprema Windows portable EXE-a i Setup EXE-a u GitHub Actions na Windows runneru.
- Inno Setup instalacijski program sa Start Menu prečacem, deinstalacijom i opcionalnim prečacem na radnoj površini.
- Windows: CSV izvoz **trenutačno prikazane stranice rezultata** (nije izvoz kompletnog registra), zaštita od spreadsheet formula injection.
- Sinkronizirani brojevi verzija Windows aplikacije, WordPress plugina i dokumentacije.
- GitHub release uključuje izvorni ZIP, WordPress ZIP i datoteku SHA256SUMS.

## Instalacija

- **Portable:** preuzeti EXE i pokrenuti na Windows 10/11 x64. Potrebna je internetska veza za centralni API.
- **Setup:** pokrenuti Setup EXE, slijediti korake, program se instalira samo za trenutnog korisnika.
- **WordPress:** prenijeti ZIP kroz *Dodaci → Dodaj novi → Prenesi dodatak*. Podesiti HTTPS URL API-ja u *Postavke → TVRTKE*.
- **Backend:** vidjeti [`DEPLOYMENT.md`](DEPLOYMENT.md) za MyDataKnox KNOX L, SQL migracije, privatne tajne i cron.

**Windows binarne datoteke nisu digitalno potpisane.** Windows SmartScreen može prikazati upozorenje.
Nisu isporučene stvarne Sudreg baze niti pristupni podaci. Ne objavljivati tajne, postavke ili osobne podatke u repozitorij ili javno izdanje.
