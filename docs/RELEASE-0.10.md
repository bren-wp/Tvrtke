# TVRTKE v0.10.0 — vizualno usklađivanje i responzivnost

**Razvojno izdanje** Windows aplikacije i WordPress dodatka, s novim zasebnim prozorom profila te ujednačenim vizualnim identitetom.

## Windows

- Četiri prilagodljiva prozora: **Pretraživanje**, **Profil subjekta**, **Favoriti i liste** i **API postavke**.
- Početno otvaranje glavne aplikacije u normalnom, centriranom prozoru približno **84% širine** i **82% visine** radnog područja, ograničenom na **1180 × 780 WPF DIP**; nikada prisilno maksimalizirano.
- Na malim ekranima i pri višem DPI-ju prozori ostaju u raspoloživom radnom području. Različiti prijelomi navigacije, filtera, tablice i profila upravljani su dostupnom širinom, ne nominalnom veličinom ekrana.
- Zasebni **Profil subjekta** prikazuje isključivo postojeće podatke javnog API-ja — datume, status, registracijske identifikatore, sjedište i djelatnosti. Nema izmišljenih podataka.
- Ujednačene tamnoplave/tirkizne boje, četverostupčani znak TVRTKE i čitljivije kontrole s tipkovničkim fokusom.
- **Company/Authors: Brendigo**, **Contributors: brendigo**, isti krediti u instalacijskom programu.

## WordPress

- Revidiran raspored javnog pretraživanja, kartica rezultata, profilnih kartica i navigacije. Responzivni prijelomi mjere širinu ugrađenog WordPress bloka (container queries).
- Administracija ostaje dostupna i pri uskom WordPress sadržaju; urednije su kartice API-ja, prikaza, privatnosti i shortcodea.
- **Kopiraj** je stvarni administrativni gumb s korisničkom povratnom informacijom; kartice postavki ažuriraju aktivni prikaz.
- Usklađen SVG logotip u javnom i administratorskom WordPress sučelju.
- **Author: Brendigo**, **Contributors: brendigo** (točno velika/mala slova).
- Pretraga je javna samo preko centralnog HTTPS API-ja, ne preko neposrednog MySQL pristupa; niti jedan Sudreg Client Secret ne nalazi se u distribuciji.

## Provjere i ograničenja

Automatske provjere uključuju Windows .NET build, izradu ikone i instalacijskih EXE paketa, strukturni WPF/UI audit, PHP/MariaDB testove te službeni WordPress Plugin Check.

**Izvorne slike su dizajnerske reference, ne provjerene snimke izvršavanja.** Doslovnu pixel-po-pixel jednakost pri 100%, 125%, 150% DPI i svim WordPress temama nije moguće pošteno potvrditi bez stvarnog pokretanja i screenshot usporedbe na istim uređajima. Za postupak pogledati `docs/UI-SCREENSHOT-QA.md`.

Ne tvrdimo da su podaci cjelokupne Hrvatske prethodno uvezeni: produkcijski MyDataKnox pristup, SQL migracije, autorizirana sinkronizacija svih izvora i verifikacija pokrivenosti potrebni su zasebno. EXE datoteke nisu digitalno potpisane; WordPress.org dodatak nije službeno odobren.

## Paketi

- `TVRTKE-Setup-v0.10.0-win-x64.exe`
- `TVRTKE-Portable-v0.10.0-win-x64.exe`
- `TVRTKE-WordPress-v0.10.0.zip`
- `TVRTKE-v0.10.0-source.zip`
- `SHA256SUMS.txt`
