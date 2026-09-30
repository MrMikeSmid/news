# Homebase

Lege PHP-basis voor een nieuw appdoel, met de bestaande huisstijl behouden.

De stijlgids in `AGENTS.md` is leidend voor alle nieuwe interfacewijzigingen.
De basis bevat alleen de startpagina; navigatie naar toekomstige functies wordt
toegevoegd zodra het nieuwe appdoel bekend is.

## Huisstijl

- Inter voor lopende tekst en bediening; Fraunces voor koppen.
- Warme achtergrond (`#FAF9F6`), zachte randen en terracotta accent (`#D97757`).
- Kaarten met afgeronde hoeken, rustige typografie en SVG-iconen.
- Vaste onderbalk op mobiel en zijbalk op desktop vanaf 1024px.
- Automatische donkere modus via de systeemvoorkeur.
- CSS-componenten volgen de meegeleverde stijlgids in `AGENTS.md`.

De stijlen staan in `public/assets/css/style.css`, de paginabasis in
`app/views/layout.php` en de startpagina in `app/views/home.php`.

## Gebruik

Vereiste: PHP 8.1+ (de helpers gebruiken het type `never`). Geen database,
login, sessies of externe PHP-dependencies nodig.

```bash
php -S localhost:8000
```

Alleen de startpagina is beschikbaar. De eerdere routes en functionaliteit voor
apps, kanalen, posts, publiceren, media en instellingen zijn verwijderd.
De app leest of migreert geen bestaande database en verandert geen opgeslagen
bestanden in `storage/`.

Voor Apache: `mod_rewrite` en de meegeleverde `.htaccess` gebruiken.
De bestaande FTP-deployment staat in `.github/workflows/deploy-ftp.yml` en start
bij een push naar `main` of `master`. `storage/` is uitgesloten van deployment.
