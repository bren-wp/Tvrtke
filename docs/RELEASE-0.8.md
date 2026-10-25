# TVRTKE v0.8.0 — prilagodljivo Windows i WordPress sučelje

**Razvojno (prerelease) izdanje, nije potvrđena produkcijska baza ni pixel-perfect usporedba iz stvarno pokrenutih aplikacija.**

## Windows

- Navigacijski stupac 252/210 px na širokim ekranima; kompaktan izbor kategorija i radnji na užim zaslonima.
- Responzivna 4/2/1 mreža filtera pri približno 1230/790 DIPs; desktop rezultat i profil jedan uz drugi, na manjim prozorima u dvije vertikalne cjeline.
- Najmanja širina prozora 540 DIPs, automatska okomita navigacija sadržajem za zaslone visokog DPI-ja i niske rezolucije.
- Bez fiktivnih tvrtki, brojača ili datuma sinkronizacije: prikazuju se samo podaci API-ja.
- HTTPS API provjera i filtrirani javni rezultati; privatne vjerodajnice nikad se ne ugrađuju u EXE.

## WordPress

- Pet glavnih polja tražilice na širokim ekranima i pristupačna proširena polja putem `details/summary`.
- CSS container queries omogućuju prilagodbu uskim sadržajnim stupcima WordPress tema; responzivne kartice, popis i profili.
- Administracijske postavke imaju prilagodljiv raspored i jasno odobrenje korištenja vanjskog servisa.
- Odvojeni administracijski podizbornici Pretraga, Uvoz/Izvoz (pregled stvarnih objavljenih izvora), Statistika (stvarna objavljena brojila, ne statistika korištenja) i Pomoć.
- Revidirani zajednički vektorski TVRTKE znak za dodatak, dokumentaciju i WordPress.org SVG ikonu.
- Header autora `Brendigo`, WordPress.org contributor `brendigo`.
- Gutenberg blok i `[tvrtke_pretraga]` ostaju kompatibilni.

## Podaci i sigurnost

Podaci se čitaju iz objavljenih privatno sinkroniziranih službenih registara. Projekt **ne sadrži gotovu kompletnu nacionalnu bazu** niti tvrdi da obuhvaća sve aktivne i neaktivne obrte. Administratorske Sudreg/OAuth2 vjerodajnice, MySQL lozinke i privatni CSV cache ne smiju biti dio distribucije.

## Vizualna QA

Za objektivnu 1:1 potvrdu potrebno je usporediti stvarno renderirane Windows/WordPress zaslone s dostavljenim PNG referencama na jednakom DPI-ju, veličini, fontu i sadržaju. GitHub Actions potvrđuje build, PHP lint, MariaDB integracijske testove i WordPress.org Plugin Check, ali **ne simulira stvarne Windows screenshotove ili svaku WP temu**.

## Distribucija

- `TVRTKE-Setup-v0.8.0-win-x64.exe`
- `TVRTKE-Portable-v0.8.0-win-x64.exe`
- `TVRTKE-WordPress-v0.8.0.zip`
- `TVRTKE-v0.8.0-source.zip`
- `SHA256SUMS.txt`
