# TVRTKE — audit vizualnih referenci (25.10.2026.)

## Namjena

Ovaj dokument bilježi što stvarni Windows i WordPress kod već implementira u odnosu na 17 korisničkih grafičkih referenci od **1448 × 1086 px** (jedna je 1672 × 941 px). Referentne slike su **dizajnerske makete** i nisu dokaz stvarnog broja zapisa ili dostupnosti službenih API podataka. U prikazu se ne smiju koristiti izmišljene tvrtke, OIB-ovi, datumi sinkronizacije, financije, povijesni događaji ili rezultati kao da su stvarni.

## Vizualni sustav

| Element | Vrijednost / pravilo |
| --- | --- |
| Glavna tamnoplava | `#0B2D4A` |
| Primarni tirkiz | `#00AEA9` / `#00AFA9` |
| Pozadina radnog prostora | `#EFF7FA` |
| Kartice / površine | `#FFFFFF` |
| Sivi tekst | `#5B7490` |
| Konture | `#DBE8F1` |
| Font | Segoe UI Windows / Inter + system fallback WordPress |
| Polumjer kartica | 11–15 px |
| Minimalna korisnička kontrola | 42–45 px visine |
| Fokus tipkovnice | vidljiv focus ring, kontrastnije od obruba |
| Reaktivnost | WordPress prijelazi 1050/680 px i admin 1180/782/460 px |
| Animacija | isključuje se uz `prefers-reduced-motion` |

Statični brand SVG je u `wordpress/tvrtke-registar/assets/logo-mark.svg`. Windows sadrži stvarnu `.ico` aplikacije, a lijeva traka koristi vektorsku interpretaciju iste siluete. **Za egzaktno 1:1 podudaranje logotipa sa slikom potreban je izvorni dizajnerski SVG i ručni vizualni pregled.**

## Windows ekran po ekran

| Referentni ekran | Postojeća implementacija | Preostala razlika |
| --- | --- | --- |
| Pretraga — lijevi meni, filteri, popis i detalji | `MainWindow.xaml`: novi shell 1448 × 960, lijeva navigacija 252 px, header, tražilica, status, tablica, desni panel | Nije fizički screenshotiran na Windowsu; DPI i stanje bez podataka treba potvrditi |
| Profil subjekta | Detaljan desni panel i stvarne `/v1/directory/{source}/{id}` vrijednosti | Polja koja nema javni API (zaposlenici, kapital, financije, navodno povezane tvrtke) nisu lažno dodana |
| Favoriti / izvoz | Funkcionalna lokalna lista favorita i CSV izvoz trenutačne stranice | Maketa odvojenih korisničkih lista te Excel/PDF izvoza nije implementirana |
| API postavke | `ApiSettingsWindow.xaml`: HTTPS polje, stvarna provjera `/v1/health` i `/v1/sources`, sigurnosna objašnjenja | Sinkronizacija je isključivo backend posao, zato Windows ne pohranjuje Sudreg token niti ima gumb koji pokreće privatni uvoz |

## WordPress ekran po ekran

| Referentni ekran | Postojeća implementacija | Preostala razlika |
| --- | --- | --- |
| Admin API postavke | Vlastiti izbornik TVRTKE; hero, odobrenje vanjske usluge, HTTPS URL, sigurnosne informacije | Nema korisničkog polja s pravim Sudreg tajnim ključem, namjerno |
| Admin Prikaz | Funkcionalan odabir liste/kartica i 5–50 rezultata po stranici; ilustracija bloka | Mockup ima dodatne prilagodbe boja, pojedinačnih polja i drugih prikaza, još nisu funkcionalno podržane |
| Admin Uvoz/Izvoz/Statistika | API sinkronizacija se izvodi privatnim CLI procesom, a ne iz javnog WordPress sučelja | Brojke, grafovi i demo-uvoz s makete nisu implementirani kao lažni podaci |
| Javni početni blok / pretraga | Hero, filtri, brze kategorije, stvarna veza prema `/v1/directory`, popis/kartice | Nije verificirano unutar konkretne WordPress teme na produkciji |
| Javni profil | Naziv, status, datumi, izvor, poslovni podaci i djelatnosti; korisnički link prema karti | Nema neautoriziranih financijskih, kontaktnih ni vlasničkih podataka |

## Centralni registri i privatnost

1. Sudreg `only_active=false` i fiksirani snapshot ID omogućuju uvoz javno dostupnih aktivnih i neaktivnih sudskih zapisa.
2. Službeni CSV podaci udruga i zaklada mapiraju se zasebno, uz provjeru broja redaka i datumâ.
3. Obrtni registar nije proglašen uvezenim bez verificiranog dopuštenog nacionalnog bulk izvora.
4. Client ID, Client Secret, MySQL lozinke i privatna arhiva nikada se ne stavljaju u Windows, WordPress ili javni repozitorij.
5. Brojevi `553.218`, `132.604`, `66.347` i `3.912` na dizajnerskim referencama nisu potvrđena stvarna stanja baze — zato se **ne hardkodiraju**.
6. Datumi osnivanja/upisa/promjene statusa/brisanja razlikuju se; nisu međusobno zamjenjivi.
7. Nije moguće tehnički obećati da korisnik javne tražilice nikada neće kopirati *javno prikazane* podatke. Ovlasti privatne baze i tajni mogu se zaštititi, javna pretraga i njezini vidljivi odgovori po definiciji nisu tajni.

## Testovi prije tvrdnje “pixel-perfect 1:1”

- Windows: pokrenuti `dotnet publish` i snimiti stvarne ekrane pri 100%, 125% i 150% DPI, širine 1010 / 1448 / 1920 i različitim duljinama naziva.
- WordPress: testirati desktop/tablet/mobitel širine 1448 / 768 / 390 px, blok unutar više aktivnih tema, govorne čitače i navigaciju tipkovnicom.
- Usporediti snimku aplikacije i korisnički izvorni PNG, po slojevima: poravnanja, line-height, veličine ikonica, font, margine, aktivna/fokus/disabled stanja.
- Provjeriti `empty/loading/error/partial` stanja te dugotrajni import i prekid veze.
- Testirati opseg podataka i datum objave na stvarnom MyDataKnox hostingu, tek nakon zasebne privatne konfiguracije.
- Ne označavati status 1:1 ako vizualna diffs provjera nije stvarno provedena.

## Promjene v0.8.0 i pragovi prilagodbe

**Windows:** referentni desktop raspored ostaje 252 px sidebar + stupac rezultata i profil; pri širini prozora < 1330 DIPs sidebar se sužava na 210 px; pri < 1120 DIPs zamjenjuje se kompaktnim izbornikom koji zadržava sve radnje. Filteri se prelamaju u 4 / 2 / 1 stupac oko 1230 i 790 DIPs. Ispod 995 DIPs detalji se prikazuju ispod rezultata; sadržaj je vertikalno pomičan i kad je Windows skaliranje 150% ili više. Najmanja dopuštena širina prozora: 540 DIPs.

**WordPress:** CSS container query na širini samog kratkog koda/Gutenberg bloka prelama tražilicu na 5 / 3 / 2 / 1 stupac te rezultate i kartice na mobilnom sadržajnom prostoru. Dodatni filtri otvoreni su izvorno podržanim HTML elementom `details` koji radi i bez JavaScripta. Administracija prelama hero, tabove, pregled i podatkovne tablice, a podizbornici za podatkovne izvore i statistiku prikazuju stvarne metapodatke API-ja.

**Autorstvo:** `Author: Brendigo`; `Contributors: brendigo`. Windows Company, Authors i instalacijski izdavač koriste `Brendigo`.

**Granice potvrde:** uspješna kompilacija, lint i Plugin Check ne jamče egzaktan pikselni diff s PNG referencama. Ne postoje automatske snimke Windows desktopa na jednakoj Windows temi/DPI-ju i učitanoj privatnoj bazi, pa je 1:1 stanje i dalje zadatak vizualne provjere.
