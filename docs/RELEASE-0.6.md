# TVRTKE v0.6.0 — izdanje

## Novosti

- Potpuno redizajnirana marketinška GitHub naslovnica: logo, hero slika, pregled mogućnosti i ilustracija pretrage.
- Centralni API: nove rute `/v1/directory`, `/v1/directory/{source}/{id}` i `/v1/sources`, uz zadržavanje starih `/v1/subjects` ruta.
- Novi verzionirani model `registry_sources` i `registry_records` za obrte, udruge, zaklade i dodatne odobrene registre.
- Importer za javne, zakonito preuzete CSV datoteke, s ograničenim serijama, nastavkom na prethodnom offsetu i atomskom objavom izvora.
- Windows: mogućnost odabira vrste izvora, podaci o registru i favoriti odvojeni prema jedinstvenom ključu zapisa.
- WordPress: pretraga svih podržanih vrsta subjekata s izvorno-specifičnim detaljima.
- Izvoz aktualne stranice rezultata u CSV, sa sigurnim tekstnim ćelijama.
- Optimizirano čišćenje zastarjelih uvoza radi kapaciteta MyDataKnox KNOX L.

## Paketi

- TVRTKE-Portable-v0.6.0-win-x64.exe
- TVRTKE-Setup-v0.6.0-win-x64.exe
- TVRTKE-WordPress-v0.6.0.zip
- TVRTKE-v0.6.0-source.zip
- SHA256SUMS.txt

## Važno

**Ovo je razvojno izdanje.** Ne uključuje bazu svih hrvatskih subjekata ni tuđe pristupne podatke. Podaci u Sudreg bazi nisu uvezeni dok na hostingu nisu postavljene vjerodajnice i pokrenuta sinkronizacija. Za obrte, udruge i zaklade potreban je valjani službeni skup podataka koji se smije ponovno koristiti. CSV importer je opći mehanizam; ne dokazuje da je pojedini nacionalni registar kompletan.

Prije nadogradnje centralnog hostinga pokrenuti **`backend/database/004_registry_sources.sql`**. Obnoviti bazu na sigurnoj kopiji prije migracije. Dokumentacija izvora i uvoza: `docs/REGISTRY-SOURCES.md` i `docs/REGISTRY-IMPORT.md`.

Prije produkcije potrebno je testirati stvarni hosting i Sudreg, potvrditi uvjete korištenja i GDPR, objaviti uvjete i politiku privatnosti vanjske API usluge, dovršiti ručni WordPress.org pregled te interaktivne Windows testove. EXE datoteke nisu digitalno potpisane.
