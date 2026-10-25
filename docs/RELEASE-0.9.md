# TVRTKE v0.9.0 — prilagodljiva Windows i WordPress sučelja

**Razvojno izdanje (prerelease).** Usklađivanje prema korisničkim referencama, bez neprovjerene tvrdnje da je svaki piksel identičan.

## Windows

- Tri korisnička prozora: pretraga, postavke i favoriti/liste/izvoz.
- Glavna aplikacija počinje u centriranom, **nemaksimaliziranom** prozoru najviše oko 84% širine i 82% visine radne površine monitora; nominalna veličina 1180 × 780 DIPs.
- Per-monitor DPI podrška kroz `MonitorFromWindow`/`GetMonitorInfo` i WPF `TransformFromDevice`, bez WinForms globalnih implicitnih namespace konflikata.
- Devet usklađenih vektorskih navigacijskih ikona.
- Sklopivi sidebar i filteri 4/2/1, rezultati i profil se prelamaju vertikalno kad je prozor uži.
- Korisnički favoriti, imenovane liste, pretraga favorita i siguran CSV izvoz (neutralizacija formula).

## WordPress

- Jedinstvena SVG obitelj ikona na javnim i administracijskim ekranima, službeni logo u javnom hero prikazu.
- Responzivna pretraga s naprednim filtrima te kartični ili popisni raspored.
- Zasebne kartice profila: osnovni podaci, lokacija/službeni izvor, registrirane djelatnosti. Bez izmišljenih kontaktnih, financijskih ili povijesnih podataka.
- Funkcionalne postavke: 5–50 rezultata po stranici, 1–1440 minuta WordPress predmemorije, odabir istaknutih podataka (mjesto, djelatnost, OIB pravne osobe), administrativno odobrenje vanjskog servisa.
- Jedinstvena navigacija podstranica: Postavke, Pretraga, Uvoz/Izvoz, Statistika i Pomoć.
- WordPress.org metadata **Contributors: brendigo**, **Author: Brendigo**; Windows **Company/Authors: Brendigo**, dodatni metadata **Contributors: brendigo**.

## Privatnost, granice i provjere

- Sudreg OAuth2 ID/Secret, MySQL vjerodajnice i privatni CSV nisu dio Windows/WP distribucije. Oboje komuniciraju samo s javnim HTTPS API-jem.
- Korisničke reference uključuju ilustrativne primjere i neprovjerene nacionalne brojače; ne dodaju se kao stvarni podaci.
- Potpuna nacionalna baza i dalje zahtijeva autorizirani MyDataKnox deployment i službene izvore, osobito za sve obrte.
- CI provjerava WPF build, vektorske ikone, strukturni UI ugovor, PHP/MariaDB testove, WordPress Plugin Check i pakiranje. **Objektivna pixel-po-pixel 1:1 potvrda zahtijeva stvarno renderirane screenshotove Windows/WP uz iste fontove, podatke i DPI te vizualni diff.**
- Windows EXE nije digitalno potpisan, WordPress.org dodatak još nije službeno predan.

## Distribucija

- `TVRTKE-Portable-v0.9.0-win-x64.exe`
- `TVRTKE-Setup-v0.9.0-win-x64.exe`
- `TVRTKE-WordPress-v0.9.0.zip`
- `TVRTKE-v0.9.0-source.zip`
- `SHA256SUMS.txt`
