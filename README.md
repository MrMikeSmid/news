# Dagstart

PHP 8 + SQLite-app die twee keer per dag een verslag toont: **ochtend 07:45** en **avond 21:00**, met per onderwerp een eigen venster: agenda, taken, e-mail en nieuws. Een AI verzamelt de gegevens via MCP-tools en slaat het verslag op in deze app; de app is het dashboard. Draait op `mikesmid.nl/news` (subpad wordt automatisch herkend, of zet `APP_BASE_PATH`).

Stijl: zie `AGENTS.md` (stijlgids, tokens en componenten).

## Wat de AI per moment moet doen

| Onderwerp | Ochtend 07:45 | Avond 21:00 | Bron |
|---|---|---|---|
| E-mail | alles ontvangen sinds gisteren 21:00 | alles ontvangen sinds vandaag 07:45 | MCP `mail_fetch` (dit project) |
| Nieuws | gepubliceerd sinds gisteren 21:00 | gepubliceerd sinds vandaag 07:45 | MCP `news_list_sources` + `fetch_url`, plus eigen zoekfunctie |
| Agenda | afspraken van vandaag | afspraken van morgen | MCP `calendar_events` (CalDAV/iCloud) |
| Taken | taken voor vandaag | nog openstaande taken | bestaande Hubs ai-MCP |

`get_briefing_plan` geeft de exacte tijdvensters terug, dus de AI hoeft ze niet zelf uit te rekenen. Elk onderwerp wordt apart opgeslagen met `post_report_section` (MCP) of `POST /api/sections`, zodat bijvoorbeeld de Hubs ai-taak los van de rest kan rapporteren.

## Toegang

- **Dashboard lezen:** open de sleutel-link uit *Instellingen → Toegang* (`/news/?k=SLEUTEL`). Dat zet een langlevende cookie en stuurt door naar een schone URL; de link werkt ook in pushmeldingen. De sleutel geeft **alleen lezen**: geen toegang tot instellingen en niet om in te loggen. Een nieuwe sleutel maken maakt oude links ongeldig.
- **Instellingen:** inloggen met je account. De eerste keer maak je dit zelf aan op `/setup` (geen standaardwachtwoord).
- **AI/MCP:** aparte agent-token (niet dezelfde als de sleutel), als `?token=…` of `Authorization: Bearer …`.

## MCP-server

`POST /news/mcp?token=AGENT_TOKEN` (JSON-RPC, stateless streamable HTTP). Tools:

`get_briefing_plan`, `mail_list_accounts`, `mail_list_folders`, `mail_fetch`, `calendar_list_calendars`, `calendar_events`, `news_list_sources`, `fetch_url`, `post_report_section`.

- **Mail** is alleen-lezen: IMAP (SSL of STARTTLS) en POP3, meerdere accounts, per account optioneel "alle certificaten accepteren". Per mail: afzender, ontvangers, datum, onderwerp, volledige tekst, bijlagen, gelezen/beantwoord, `In-Reply-To` en een bulk-vlag (voor latere spam-/reclamefilters). SMTP-instellingen kun je alvast bewaren; verzenden doet de app nog niet.
- **Agenda:** CalDAV (iCloud: `https://caldav.icloud.com`) met een app-specifiek wachtwoord. Herhalingen, tijdzones en hele dagen worden verwerkt.
- **Nieuws:** de bronnen zijn instellingen; `fetch_url` leest pagina's en RSS/Atom (interne adressen worden geweigerd).
- Wachtwoorden staan AES-256-GCM-versleuteld in SQLite; de sleutel staat in `storage/app.key` (niet in Git, niet via het web bereikbaar).

## Voorbeeldopdracht voor de geplande AI-taken

> Roep `get_briefing_plan` aan met slot `morning` (of `evening`). Haal mail op met `mail_fetch` (venster uit het plan), nieuws uit de bronnen van `news_list_sources` (venster uit het plan), agenda met `calendar_events` en de taken uit Hubs ai. Schrijf per onderwerp een kort verslag in gewone Nederlandse taal met duidelijke afzenders en vermeld of iemand op een reactie wacht. Sla elk onderwerp op met `post_report_section` en stuur daarna een pushmelding met de sleutel-link naar het dashboard.

## Installatie en deploy

Plain PHP, geen composer. Vereist PHP 8.0+ met `pdo_sqlite`, `curl`, `openssl`, `mbstring`, `iconv` en `dom`; Apache met `mod_rewrite` en schrijfrechten op `storage/`. `.github/workflows/deploy-ftp.yml` uploadt bij elke push naar `main` (zonder `storage/`, dus database en sleutels blijven bewaard).

## Tests

`bash tests/run.sh` start nep-IMAP/POP3/CalDAV-servers (ook met zelfondertekend certificaat) en de app en draait alle tests. Er wordt nooit met echte providers verbonden.
