# TVRTKE v0.5.0 — funkcionalni razvoj i WP.org provjere

## WordPress dodatak
- Gutenberg blok `tvrtke-registar/pretraga` s poslužiteljskim renderiranjem.
- Aktivacija korištenja vanjske API usluge tek nakon izričitog pristanka administratora.
- Naprednija pretraga uključuje mjesto uz postojeći naziv, županiju, djelatnost i status.
- Lokalizacija teksta putem WordPress `__()`, `esc_html__()` i `esc_attr__()`.
- Opširniji `readme.txt` opisuje vanjski API, podatke u zahtjevima i ograničeni skup rezultata.
- Prilikom deinstalacije uklanjaju se spremljene postavke (transient cache istječe automatski).
- Poseban GitHub workflow pokreće **službeni WordPress Plugin Check**. Samo uspješan build nije odobrenje WordPress.org.
- Prije prijave na wordpress.org potrebno je objaviti stvarne uvjete korištenja i politiku privatnosti API usluge, aktualizirati poveznice, testirati na WP instalaciji i provjeriti rezultate Plugin Checka.

## Windows
- Postavke URL-a centralnog API-ja spremljene su po korisniku u `%LOCALAPPDATA%/Brendigo/TVRTKE/settings.json`; dopušten je samo HTTPS.
- Lokalni favoriti se pohranjuju u istoj datoteci i mogu se vidjeti bez ponovne pretrage.
- EXE je samostalan Windows x64; setup se izrađuje kroz Inno Setup 6.
- OIB i MBS nisu dijeljeni na vanjski servis iz Windows klijenta osim kroz upite koje korisnik izričito pokrene.

## Backend
- API verzija 0.5.0, postojeća SQL shema v0.4 (nema nove migracije).
- Sinkronizacija pet osnovnih skupova ostaje ograničena, pa je prikaz djelomičan kada je uvoz skraćen.

## Uvjeti puštanja u produkciju
1. Provjeriti Sudreg OAuth2 u stvarnom okruženju i uvjete daljnje distribucije podataka.
2. Migrirati bazu i testirati cron na MyDataKnox KNOX L.
3. Testirati Windows korisničke scenarije i WordPress front-end u pravoj WP instalaciji.
4. Provjeriti da su objavljene stranice privatnosti i uvjeta usluge.
5. Digitalno potpisati EXE ako postoji odgovarajući certifikat.
