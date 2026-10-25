=== TVRTKE – Poslovni registar ===
Contributors: bren-wp
Tags: hrvatska, poslovni registar, tvrtke, sudski registar
Requires at least: 6.4
Requires PHP: 8.2
Stable tag: 0.5.0
License: MIT
License URI: https://opensource.org/license/mit

Pretraživanje javnih subjekata Sudskog registra RH pomoću centralnog TVRTKE API-ja.

== Description ==

TVRTKE prikazuje javnu tražilicu i osnovne profile poslovnih subjekata pomoću
centralnog TVRTKE API servisa. Podržava naziv, OIB, MBS, županiju, mjesto,
djelatnost i status. Nudi Gutenberg blok "TVRTKE pretraga" te shortcode [tvrtke_pretraga].

**Vanjska usluga i obrada podataka:** ovaj plugin ne sadrži Sudreg bazu;
ne može pretraživati bez odvojeno postavljenog centralnog API-ja. Administrator
unosi HTTPS adresu API-ja i posebno aktivira korištenje vanjske usluge.
Prije aktivacije plugin ne šalje upite na API. Nakon aktivacije, pri korisničkoj
pretrazi WordPress poslužitelj šalje upit (naziv ili OIB/MBS), županiju,
mjesto, djelatnost, status, stranicu i standardne mrežne metapodatke
(uključujući IP adresu WordPress poslužitelja) na postavljeni API.
API može bilježiti metapodatke HTTP zahtjeva. Posjetiteljev IP nije
izričito uključen u parametre. Nema telemetrije, reklamnog praćenja
ni vanjskih JS/CSS biblioteka. WordPress predmemorira odgovor do 5 minuta.

**Pružatelj usluge:** TVRTKE, https://tvrtke.com.hr/ .
**Izvorni kod backend usluge:** https://github.com/bren-wp/Tvrtke .
Za prijavu u WordPress.org direktorij pružatelj usluge treba prije toga
objaviti zasebnu javnu politiku privatnosti i uvjete korištenja, a zatim
njihove stvarne poveznice dodati u ovaj readme (nisu potvrđene).

== Installation ==

1. Postavite vlastiti PHP/MySQL backend prema repozitoriju.
2. Instalirajte WordPress dodatak i otvorite Postavke > TVRTKE.
3. Unesite pouzdanu HTTPS adresu API-ja i izričito uključite vanjsku uslugu.
4. Dodajte Gutenberg blok "TVRTKE pretraga" ili shortcode [tvrtke_pretraga].
5. Dovršite inicijalnu sinkronizaciju prije očekivanja potpunijih rezultata.

== Frequently Asked Questions ==

= Je li baza poslovnih subjekata uključena u dodatak? =
Ne. Centralni API i MySQL bazu potrebno je zasebno postaviti.

= Je li potreban korisnički račun za posjetitelje? =
Ne. Samo administrator konfigurira vanjsku uslugu.

= Prikazuje li plugin sve poslovne subjekte u Hrvatskoj? =
Ne nužno. Uvoz može biti ograničen resursima hostinga; plugin tada prikazuje upozorenje.

= Brišu li se postavke pri deinstalaciji? =
Da, lokalne postavke. Predmemorirani podaci istječu prema WordPress pravilima.

== Changelog ==

= 0.5.0 =
* Izričita aktivacija API usluge, precizniji opis prijenosa podataka.
* Gutenberg blok, filter mjesta, poboljšano stiliziranje i pristupačnost.
* Brisanje opcija pri deinstalaciji.

= 0.4.0 =
* Poboljšanja prikaza djelomičnih rezultata.
