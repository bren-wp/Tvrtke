# Registri i podrijetlo podataka

TVRTKE razlikuje **mogućnost povezivanja izvora** od **stvarno preuzetih i objavljenih podataka**. Izvor se prikazuje javno tek nakon dovršenog uvoza s provjerenim podrijetlom.

| Registar | Izvor | Način pristupa | Trenutni status |
| --- | --- | --- | --- |
| Sudski registar | https://sudreg-data.gov.hr/ | OAuth2 + API v3.0.4 | Podržan importer, potreban API račun i završeni uvoz |
| Registar udruga | https://data.gov.hr/ckan/dataset/registar-udruga | Objavljeni CSV/JSON/XML resursi, uz provjeru sadržaja | CSV importer pripremljen; nema spremljene potpune baze |
| Registar zaklada | https://data.gov.hr/ckan/dataset/registar-zaklada | Otvoreni podatkovni resursi ako sadrže prikladan pregled zaklada | CSV importer pripremljen; izvor treba provjeriti |
| Obrtni registar | https://pretrazivac-obrta.gov.hr/ | Javni preglednik pretrage | Nije potvrđen nacionalni službeni bulk CSV/API; ne zaobilaziti zaštite |
| Ostali izvori | Svaki ovjereni javni registar uz zakonitu ponovnu uporabu | Izvoz datoteke s potpunim metapodacima o izvornom registru | Importer podržava tip ostalo |

**Ne smije se predstavljati kao potpuna nacionalna baza dok se stvarno ne provjere dostupni izvorni zapisi i njihov broj.**

## Granice izvora

- Sudski registar ne sadrži automatski sve obrte i udruge te ne predstavlja nacionalni poslovni registar svih vrsta subjekata.
- Dataset „Obrtni registar” koji je objavila Sisačko-moslavačka županija na data.gov.hr upućuje na preglednik, ne nudi provjeren kompletan nacionalni download.
- Skup „Registar udruga” na data.gov.hr ima resurse „Djelatnosti”, „CTS” i „Osobe”. **Nemojte izravno uvoziti resurs „Osobe”** jer sadrži podatke o fizičkim osobama; odaberite prikladan izvor s javnim podacima o pravnoj osobi i verificirajte njegove stupce.
- Za obrte izbjegavati pohranu OIB-a vlasnika; importer ga prema zadanim postavkama odbacuje.
- Izvori imaju različite pravne i tehničke definicije statusa; nepoznat status ne označava se automatski neaktivnim.
- Jedan pravni subjekt može biti upisan u više registara; pojedinačni zapisi ostaju povezani sa svojim registrom umjesto nepouzdane automatske deduplikacije po nazivu.
- Zaštita osobnih podataka, licencije, autorska prava, dinamika osvježavanja i prava redistribucije provjeravaju se za svaki izvor posebno.

## Pokrivenost

Svaki vanjski izvor ima `coverage` stanje `partial` ili `complete`, broj objavljenih zapisa, datum i URL izvora. `complete` zahtijeva *izvana verificiran očekivani broj redaka* i uvoz točno toliko redaka. To nije samo po sebi dokaz potpunosti državnog registra: potrebno je neovisno potvrditi obuhvat izvornog skupa.

Endpoint `GET /v1/sources` vraća samo **uspješno objavljene vanjske izvore**. Sadržaj koji je još u uvozu nije javno vidljiv. `GET /v1/directory` vraća zajedničke zapise i oznaku `partial`.
