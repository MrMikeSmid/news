# Homebase

Een persoonlijke, uitbreidbare PHP 8/SQLite-CMS: jouw eigen nieuws-, media- en bloghomebase. Versie 1 bevat een nieuwswall (RSS + handmatige/social berichten met afbeeldingen en video's) en een blogmodule die posts via een API-call naar een andere website kan versturen.

## Functionaliteit

- Login met sessie-authenticatie (standaard `admin` / `admin123!` — wijzig dit meteen na installatie bij Instellingen).
- **Wall**: een Facebook-achtige feed van al je bronnen, met filters per categorie.
- **Bronnen**: voeg zelf RSS-feeds toe (ook geschikt voor RSS-bridges van social media) of maak handmatige berichten met tekst, afbeeldingen, video's en links.
- **Blogs**: schrijf blogposts met een eenvoudige rich-text-editor, sla ze op als concept of gepubliceerd, en verstuur ze met één klik via een API-call (POST + JSON, optioneel met Bearer-token) naar een andere website van jezelf.
- Automatisch verversen via een beveiligde cron-URL (token in Instellingen), of handmatig met de "Verversen"-knoppen.
- Geüploade media wordt opgeslagen in `storage/uploads/` (blijft bewaard tussen deploys) en veilig geserveerd via `/media/...`.
- Consistente, warme "Claude-stijl" huisstijl: zelfde kleuren, typografie, kaarten en knoppen door de hele app, met automatische donkere modus.
- CSRF-beveiliging, server-side validatie, output-escaping en PDO prepared statements.
- Database en admin-account worden automatisch aangemaakt bij de eerste request (geen aparte setup-stap nodig).

## Vereisten

- PHP 8.0 of hoger.
- PHP-extensies `pdo`, `pdo_sqlite`, `dom`, bij voorkeur `curl`.
- Apache met `mod_rewrite` en toegestane `.htaccess`-overrides (of vergelijkbare configuratie).
- Schrijfrechten voor de webserver op de map `storage/`.

## Lokale installatie

```bash
php -S localhost:8000
```

Open `http://localhost:8000/`. De database en het admin-account (`admin` / `admin123!`) worden automatisch aangemaakt in `storage/database.sqlite`. Log in en wijzig het wachtwoord meteen bij **Instellingen**.

## Installatie op hosting (via de meegeleverde FTP-deploy)

De workflow `.github/workflows/deploy-ftp.yml` upload bij elke push naar `main` automatisch de hele app (behalve `.git`, `.github` en `storage/`) naar de FTP-server in de secret `FTP_SERVER_DIR`. Zorg voor de repository secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` en `FTP_SERVER_DIR`.

1. Zorg dat de doelmap op de hosting schrijfrechten heeft voor `storage/` (bij voorkeur `775`).
2. Push naar `main` — de deploy start automatisch (of trigger hem handmatig via *Run workflow*).
3. Open de site; database en admin-account worden automatisch aangemaakt.
4. Log in en wijzig direct het standaardwachtwoord bij **Instellingen**.
5. Stel eventueel `APP_BASE_PATH` in als de app in een submap draait en dit niet automatisch goed wordt gedetecteerd.

## RSS-bronnen automatisch verversen

Elke bron wordt handmatig ververst via de knop, maar je kunt dit ook automatiseren: kopieer de cron-URL van de pagina **Bronnen** of **Instellingen** (bevat een geheime token) en laat je hosting deze periodiek aanroepen, bijvoorbeeld via een cPanel cron job:

```bash
curl -fsS "https://jouw-domein.nl/cron/fetch?token=...."
```

## Blogs versturen naar een andere website

Stel bij **Instellingen** het API-endpoint en (optioneel) een API-sleutel in. De knop "Versturen naar site" op een blog stuurt een `POST` met JSON-body (`title`, `slug`, `excerpt`, `content`, `cover_image`, `published_at`) en, indien ingesteld, de header `Authorization: Bearer <sleutel>`.

## Structuur

```text
app/
  controllers/    Auth, Wall, Sources, Blogs, Settings, Media
  views/          Layout, wall, bronnen, blogs, instellingen
  fetcher.php     RSS/Atom-parser met afbeelding-/video-detectie
  migrate.php     Automatische installatie + seed-admin
  auth.php, csrf.php, database.php, helpers.php, bootstrap.php, config.php
database/
  schema.sql      Databaseschema
public/
  assets/         CSS (designsysteem) en JavaScript
  index.php       Front controller/router
storage/
  database.sqlite Wordt automatisch aangemaakt (niet in Git)
  uploads/        Geüploade afbeeldingen/video's (niet in Git)
```

Nieuwe onderdelen (extra socialintegraties, notificaties, categorieën, gebruikers) kunnen als eigen tabellen, controllers en views worden toegevoegd. Houd de front controller klein en plaats domeinlogica bij voorkeur in nieuwe services onder `app/`.
