# Instalacija – MyDataKnox KNOX L (cPanel)

**Status: dokumentirani postupak; instalacija na pravi hosting još nije izvršena.** Nije poznato dopušta li konkretni račun udaljeni API subdomain, cron u kratkom intervalu ili kolika je stvarna MySQL kvota. Te detalje treba potvrditi u cPanelu.

## Preduvjeti

- PHP **8.2+**, proširenja `pdo_mysql`, `curl`, `mbstring`, `json`.
- MySQL/MariaDB baza s dovoljno prostora; korisnik baze s pravima nad vlastitom bazom.
- SSL za `tvrtke.com.hr` i planiranu poddomenu `api.tvrtke.com.hr`.
- Cron naredba koja pokreće PHP CLI i poštuje hosting politiku.
- Besplatno registriran javni Sudreg OAuth2 račun (`client_id` / `client_secret`); ne dijeliti tajne u GitHubu, WordPress editoru ni s krajnjim korisnicima.

## Preporučeni raspored datoteka

```text
/home/CPANELUSER/
  tvrtke-backend/
    config.local.php   # PRIVATNO, ne spremati u Git
    src/               # PRIVATNO
    bin/               # PRIVATNO
    database/          # PRIVATNO
    public/            # Document Root poddomene api.tvrtke.com.hr
  public_html/         # postojeći WordPress na tvrtke.com.hr
```

Ako cPanel ne dopušta Document Root unutar `tvrtke-backend/public`, **ne objavljujte cijelu mapu backend**: izradite zasebnu javnu mapu koja sadrži samo sadržaj `public/` i prilagodite putanju do `src/bootstrap.php` u `index.php`/`router.php`. `config.local.php` i `src/` uvijek moraju biti izvan javnog webroota.

## Instalacija

1. Kreirajte praznu MySQL bazu `CPANELUSER_tvrtke` i korisnika u cPanelu.
2. Uvezite `backend/database/001_initial.sql`, zatim `backend/database/002_enrichment.sql`, zatim `backend/database/003_publication_state.sql` i naposljetku **`backend/database/004_registry_sources.sql`** (svaku migraciju samo jednom) putem phpMyAdmin (provjerite rezultat migracije).
3. Kopirajte `backend/config.example.php` u **`backend/config.local.php`**, stavite stvarne vrijednosti i postavite datoteku izvan Document Roota. Preporučena prava `0600` gdje su podržana; tajni sadržaj nikada ne slati u Git.
4. U cPanelu podesite `api.tvrtke.com.hr` sa Document Rootom na `backend/public/` i uključite valjani HTTPS certifikat. Provjerite `https://api.tvrtke.com.hr/v1/health`.
5. Nakon potvrde stvarnih cron limita konfigurirajte npr. svakih **5–10 minuta** (ne češće od dopuštenog):

   ```cron
   */10 * * * * /usr/local/bin/php /home/CPANELUSER/tvrtke-backend/bin/sync.php >/dev/null 2>&1
   */10 * * * * /usr/local/bin/php /home/CPANELUSER/tvrtke-backend/bin/prune.php >/dev/null 2>&1
   */10 * * * * /usr/local/bin/php /home/CPANELUSER/tvrtke-backend/bin/prune-registry.php >/dev/null 2>&1
   ```

   Putanju do PHP CLI alata morate prilagoditi stvarnoj putanji u cPanelu. Na prvoj instalaciji pokrenite skripte ručno u terminalu ako je dostupan. Za dijagnostiku usmjerite stdout/stderr na **privatni** log s rotacijom umjesto u `/dev/null`; nikada ne zapisujte OAuth2 tajne.
6. Provjerite dovršetak sinkronizacije pozivom `GET /v1/status` s `Authorization: Bearer <TVRTKE_ADMIN_TOKEN>` (iz privatnog računala, ne iz web stranice).
7. Napravite ZIP mape `wordpress/tvrtke-registar/`, instalirajte kroz WordPress dodatke te u **Postavke → TVRTKE** upišite `https://api.tvrtke.com.hr` i **izričito uključite** povezivanje s vanjskom API uslugom.
8. Stavite `[tvrtke_pretraga]` na stranicu, pa provjerite pretraživanje prema podacima koji su stvarno uvezeni.

## Ograničenja početne verzije

- `TVRTKE_IMPORT_ROW_LIMIT=10000` **po tablici** sprečava nekontrolirani rast; postavka se može povisiti do 5 000 000 tek uz provjeru prostora i izvođenja; to je ograničena demonstracijska baza, ne potpuni registar. Mijenjajte tek nakon mjerenja performansi/diska i ponovnog planiranja preuzimanja.
- Jedan `sync.php` poziv uvozi nekoliko ograničenih stranica (`TVRTKE_SYNC_PAGES_PER_RUN`, zadano 3; maksimalno 8), uz meki vremenski budžet (`TVRTKE_SYNC_BUDGET_SECONDS`, zadano 15). Za dovršetak su potrebna ponavljana cron izvršavanja.
- Podaci u javnom Sudreg API-ju imaju vlastiti ritam osvježavanja. U ovoj verziji se ponovno preuzimaju pet odabranih skupova; `promjene`/SCN i dodatni podaci nisu još implementirani.
- Svaka nova objavljena snimka prikazuje se atomarno, a oznaka djelomičnosti ostaje uz objavljenu snimku čak i tijekom idućeg uvoza. Podaci su **ograničen podskup**. Ne prikazivati broj rezultata kao broj svih hrvatskih subjekata.
- `prune.php` čisti maksimalno 500 redaka po tablici i izvođenju; pratite prostor baze te raspored cron zadataka.
- API trenutno nema zaseban sustav korisničkih računa ni production-grade rate limiting: prije javnog prometa dodajte WAF/Cloudflare ili poslužiteljsko rate limiting rješenje i nadzor opterećenja.

## Sigurnost

- Admin API token neka bude slučajno generiran, dug najmanje 32 bajta; ne koristiti WordPress login lozinku.
- Onemogućiti directory listing. Web server smije izlagati samo `backend/public/`.
- Ograničiti pristup konfiguraciji i logovima; uključiti HTTPS, 2FA za hosting i WordPress, redovite sigurnosne kopije.
- Prije produkcije: provjeriti pravnu osnovu pohrane/objave osobnih podataka, izvor i licencu podataka, uvjete korištenja, politiku privatnosti.

## Dodatni registri u v0.6.0

- Pokrenite uvoz u malim serijama uz `backend/bin/import-registry.php` samo za odobrene izvore. Potrebna je stvarna CSV datoteka i potvrđena licencija; nisu uključeni nikakvi osobni podaci ili preuzete pune nacionalne baze.
- Uvoz s izvora (`obrt`, `udruga`, `zaklada`, `ostalo`) sprema se u zasebnu seriju, a novo izdanje izvora postaje javno tek nakon potpune obrade datoteke.
- Dokumenti [REGISTRY-IMPORT.md](REGISTRY-IMPORT.md) i [REGISTRY-SOURCES.md](REGISTRY-SOURCES.md) opisuju provjeru izvora, mapiranje, osobne podatke i uvjete potpunosti.
- Putanja `TVRTKE_PUBLIC_ROOT` u konfiguraciji definira javni direktorij: importer odbija čitanje osjetljivih CSV izvoza iz njega.
- Nova javna ruta `GET /v1/directory` koristi odobrene i objavljene izvore; `GET /v1/sources` vraća njihovo podrijetlo, vrijeme i status pokrivenosti.
- KNOX L ne može biti unaprijed proglašen dostatnim za **kompletnu nacionalnu bazu svih registara**. Mjerite veličinu indeksa, prostor diska i trajanje uvoza nakon testnih serija.
