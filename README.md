# Publish Hub

PHP 8/SQLite-platform om berichten voor meerdere apps via verschillende kanalen te verspreiden.

## Concept

- **App**: een lijst waaraan je publish-kanalen koppelt.
- **Kanaal**: een platform (X, Facebook, LinkedIn, Reddit, Truth Social, Bluesky, Threads, Mastodon, Instagram) met optioneel account en webhook.
- **Post**: één bericht met basistekst, titel, link en afbeelding. Kies de kanalen; per kanaal kun je titel/tekst aanpassen.

## Platformregels (automatisch)

Gedefinieerd in `app/platforms.php`. Per platform worden tekstlimiet, verplichte titel, verplichte afbeelding, subreddit en hashtaglimiet gecontroleerd, live in de editor (tekenteller) én server-side bij opslaan/publiceren. Voorbeelden: X max 280 tekens (een link telt als 23), Reddit titel + subreddit verplicht, Instagram afbeelding verplicht en max 30 hashtags. Een platform toevoegen = één regel in `platforms()`.

## Publiceren

Per kanaal stel je optioneel een **webhook-URL** in (bijv. Zapier, Make, n8n of een eigen API). Bij publiceren wordt de kant-en-klare post als JSON gepost (`platform`, `title`, `text`, `link`, `image_url`, `subreddit`, ...). Kanalen zonder webhook blijven op "klaar" staan; met "Kopieer tekst" plaats je handmatig. Directe koppelingen met de platform-API's (OAuth) zijn de logische volgende stap.

## Gebruik

Standaardlogin `admin` / `admin123!` (wijzig direct bij Instellingen). Database en account worden automatisch aangemaakt; een oude database wordt bij de eerste request gemigreerd (de oude wall/bron/blog-tabellen worden verwijderd).

```bash
php -S localhost:8000
```

Vereisten: PHP 8+, `pdo_sqlite`, bij voorkeur `curl`; Apache met `mod_rewrite`; schrijfrechten op `storage/`. Deploy via `.github/workflows/deploy-ftp.yml` (push naar `main`).
