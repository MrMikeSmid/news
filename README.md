# WordPress met SQLite

Deze repository bevat **WordPress 7.1.3** en de officiële **SQLite Database Integration 3.1.1** plugin. De database-drop-in staat al in `wp-content/db.php`. De installatie gebruikt SQLite; een MySQL- of MariaDB-server is niet nodig.

## Vereisten op de webserver

- PHP 7.4 of nieuwer, met `PDO` en `pdo_sqlite`.
- SQLite 3.37.0 of nieuwer.
- Schrijfrechten voor PHP op `wp-content/` en voor FTP op de WordPress-map.
- Apache die `.htaccess` toepast, of gelijkwaardige webserverregels die **alle** toegang tot `wp-content/database/` blokkeren. De database en de WordPress-geheimen staan standaard in deze map. Stel bij voorkeur `WORDPRESS_SQLITE_DB_PATH` in op een absoluut pad buiten de webroot; die map moet ook beschrijfbaar zijn voor PHP.

## Eerste installatie

Na de FTP-deploy open je `/news/wp-admin/install.php` en voltooi je de WordPress-installatiewizard. De URL gaat uit van de bestaande deploylocatie `/news`; gebruik het overeenkomstige pad als `FTP_SERVER_DIR` anders is ingesteld. Maak tijdens de wizard zelf de beheerdersaccount aan.

`wp-config.php` is al ingesteld voor SQLite. Acht unieke authenticatiegeheimen worden bij het eerste verzoek gegenereerd in `wp-content/database/wp-salts.php`. Dat bestand hoort bij de serverinstallatie en staat niet in Git. De SQLite-plugin maakt daarnaast een database op een willekeurig pad onder `wp-content/database/`, tenzij `WORDPRESS_SQLITE_DB_PATH` is ingesteld.

## Deploy en blijvende gegevens

Een push naar `main` start `.github/workflows/deploy-ftp.yml`. De workflow synchroniseert WordPress-bestanden met FTP. Voor `wp-content/` gebruikt hij geen verwijderoptie: de SQLite-database, authenticatiegeheimen, uploads en plugins of thema's die via WordPress zijn toegevoegd blijven op de server staan. Maak desondanks aparte back-ups van de database en uploads. De workflow verwijdert oude bestanden buiten `wp-content/` uit de doelmap.

WordPress en de SQLite-plugin zijn afkomstig van de officiële downloads van WordPress.org. Updates van deze twee onderdelen horen in de repository te worden verwerkt; een update via het WordPress-dashboard kan bij een volgende deploy door de repositoryversie worden overschreven.
