# Razvojni plan TVRTKE

## Faza 1 – provedena u početnom kodu (v0.1.0)

- [x] Zajednička verzionirana MySQL shema i PHP API.
- [x] OAuth2 Sudreg klijent za četiri poznata endpointa (uključuje `snapshots`).
- [x] Resumabilni cron uvoz triju osnovnih skupova s fiksnim `snapshot_id`.
- [x] Ograničenje veličine uvoza, transakcijski napredak i postupno čišćenje.
- [x] WordPress shortcode i administracijska postavka API-ja.
- [x] Početni Windows WPF klijent za pretragu i osnovni prikaz.
- [x] PHP statička sintaktička provjera i test identifikatora.

## Faza 2 – potrebno za stabilnu produkciju

- [ ] Test stvarnog Sudreg OAuth2 + end-to-end uvoza s autoriziranim korisničkim podacima.
- [ ] Test MySQL-a i cron ograničenja na konkretnom KNOX L računu, odrediti stvarnu raspoloživu kvotu.
- [ ] Integracijski i performansni testovi, SQL EXPLAIN, napredni rate limiting, WAF.
- [ ] Poboljšati cache, UI/UX, dostupnost i lokalizaciju WP i Windows aplikacije.
- [ ] Detalji subjekata (djelatnosti, pravni oblik, podružnice) iz novih API tablica.
- [ ] Detekcija promjena putem `promjene`/SCN te selektivno/inkrementalno ažuriranje gdje dopušta API.
- [ ] Nadzirani uvoz prema odabranom opsegu, kontrola povijesti i oporavak od djelomičnih pogrešaka.
- [ ] WordPress Gutenberg blok, SEO metapodaci, uredni javni URL-ovi.
- [ ] Windows .msi/.exe potpisana ili jasno označena nepotpisana razvojna izdanja.
- [ ] Automatski testovi sa stvarnom bazom i end-to-end WP testovi.
- [ ] Pravna i GDPR provjera prije javne objave.

## Faza 3 – dodatne mogućnosti

- Napredna analitika, izvoz CSV/XLSX/PDF, korisnički favoriti i bilješke uz kontrole privatnosti.
- NKD i županijski filtri, dodatni registri samo uz dopušten pristup.
- Verzije, automatska ažuriranja i dokumentacija za administratore.

**Napomena:** Windows je u početnoj fazi implementiran u .NET WPF-u, ne u WinUI 3. Odluka smanjuje ovisnosti za prvi funkcionalni prototip; migracija na WinUI 3 može biti zaseban korak.
