# TVRTKE — objektivna usporedba s referentnim ekranima

Korisničke ilustracije pokazuju cilj, a ne izmjereno stanje programa. Osobito se
ne smije tretirati „pixel-perfect 1:1” kao ispunjeno dok ne postoji **stvarna**
snimka prozora na istom DPI-ju, sadržaju i veličini.

## Pravila usporedbe

1. Na Windowsu otvoriti svaki stvarni prozor (pretraga, API postavke, favoriti) na točnoj ciljnoj veličini, uz istu DPI skalu i font kao referenca.
2. Na WordPressu provjeriti admin i javne zaslone pri 1448, 1024, 768 i 390 CSS px; izabrati istu WP temu, font i demonstracijske podatke za usporedbu.
3. Omogućiti stanje tipkovničkog fokusa, hover, onemogućeni gumb i prikaz pri nedostupnom API-ju; napraviti odgovarajuće screenshotove.
4. Usporediti izvornu i stvarnu sliku bez skaliranja:

```shell
python -m pip install Pillow
python scripts/compare-ui.py referenca.png stvarni-ekran.png --diff=razlike.png
```

Program ispisuje srednju apsolutnu razliku kanala, postotak piksela koji odstupaju više od tolerancije, dimenzije i pojačanu PNG sliku razlika. Kod različitih dimenzija vraća grešku, **nikad ne mijenja veličinu ili rasteže referencu**.

Niti jedan rezultat diffa nije izrađen u CI-ju za v0.9.0 zato što snimke stvarno pokrenutih Windows i WordPress aplikacija u odgovarajućem referentnom okruženju nisu dostupne. Automatski WPF build, WordPress Plugin Check i CSS container queries nisu zamjena za vizualnu provjeru.
