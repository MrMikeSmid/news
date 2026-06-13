# Sportweek ’t Zand

Een uitbreidbare PHP 8/SQLite-webapplicatie om lokaal draagvlak te meten voor een mogelijke Sportweek in ’t Zand. Versie 1 richt zich op interesse, vrijwilligers, sponsoren en ideeën; de site communiceert nadrukkelijk niet dat het evenement al definitief doorgaat.

## Functionaliteit

- Responsive, mobiel-eerste landingspagina met actuele database-statistieken.
- Interesse-, vrijwilligers-, sponsor- en ideeënformulieren.
- CSRF-beveiliging, server-side validatie, output escaping en PDO prepared statements.
- Beveiligde beheeromgeving met dashboard en inzendingenoverzichten.
- Sponsorstatussen en interne sponsornotities.
- CSV-export per inzendingstype (UTF-8 met puntkomma als scheidingsteken).
- Eenmalige setup voor SQLite en het eerste, veilig gehashte beheerwachtwoord.
- Subdirectory-ondersteuning, bijvoorbeeld `/sportweek-t-zand`.

## Vereisten

- PHP 8.0 of hoger.
- PHP-extensies `pdo` en `pdo_sqlite`.
- Apache met `mod_rewrite` en toegestane `.htaccess`-overrides, of een vergelijkbare webserverconfiguratie.
- Schrijfrechten voor de webserver op de map `storage/`.

## Lokale installatie

1. Kopieer het project naar de document root.
2. Start lokaal vanuit de projectmap:

   ```bash
   php -S localhost:8000
   ```

3. Open `http://localhost:8000/setup.php`.
4. Kies een beheerdersnaam en een sterk wachtwoord van minimaal 12 tekens.
5. Verwijder of hernoem `setup.php` na een geslaagde installatie.
6. Open `http://localhost:8000/admin/login`.

De SQLite-database wordt aangemaakt als `storage/database.sqlite`.

## Installatie op mikesmid.nl/sportweek-t-zand

1. Upload alle bestanden naar de map `sportweek-t-zand` onder de document root.
2. Het installatiepad `/sportweek-t-zand` wordt automatisch afgeleid van de URL van `index.php`. Alleen wanneer de webserver dit pad niet correct doorgeeft, stel je de omgevingsvariabele in:

   ```bash
   APP_BASE_PATH=/sportweek-t-zand
   ```

   Een ingestelde `APP_BASE_PATH` heeft altijd voorrang op de automatische detectie.

3. Geef `storage/` schrijfrechten voor de PHP/webservergebruiker. Gebruik bij voorkeur `775`; gebruik alleen ruimere rechten als de hostingprovider dat vereist.
4. Controleer dat Apache `mod_rewrite` actief is en `.htaccess` mag worden toegepast.
5. Open `https://mikesmid.nl/sportweek-t-zand/setup.php`.
6. Maak het eerste beheeraccount aan.
7. Verwijder of hernoem `setup.php` direct na installatie.
8. Log in via `https://mikesmid.nl/sportweek-t-zand/admin/login`.

Alle links, assets, redirects en formulieracties gebruiken de ingestelde `base_path`.

## Instellingen aanpassen

Pas `app/config.php` aan voor:

- `base_path`: installatiepad onder het domein.
- `contact_email`: zichtbaar contactadres.
- `socials`: vul per platform een volledige URL in. Een lege waarde toont voorlopig een niet-actieve placeholder.
- `database_path`: locatie van het SQLite-bestand.

## Beveiliging en productie

- Verwijder `setup.php` na installatie.
- Forceer HTTPS via de hostingconfiguratie.
- Maak regelmatig een back-up van `storage/database.sqlite`.
- Houd PHP bijgewerkt.
- De meegeleverde `.htaccess` blokkeert directe toegang tot app-, database- en storagebestanden.
- Ruwe foutmeldingen worden gelogd, maar niet aan bezoekers getoond.

## Structuur en uitbreiden

```text
app/
  controllers/    Publieke en beheeracties
  views/          Herbruikbare PHP-views
  auth.php        Beheerauthenticatie
  csrf.php        CSRF-bescherming
  database.php    PDO/SQLite-verbinding
database/
  schema.sql      Databaseschema
public/
  assets/         CSS en vanilla JavaScript
  index.php       Front controller/router
storage/          SQLite-database (niet in Git)
setup.php         Eenmalige installatie
```

Nieuwe onderdelen zoals teams, verenigingen, programma, puntentelling, nieuws en betalingen kunnen als eigen tabellen, controllers en views worden toegevoegd. Houd de front controller klein en plaats domeinlogica bij voorkeur in nieuwe services of repositories onder `app/`.
