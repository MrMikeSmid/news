# AGENTS.md — Stijlgids voor de interface

Dit document beschrijft het visuele ontwerp van de app zo compleet dat een andere AI (of ontwikkelaar) een
nieuwe pagina, component of hele app **in exact dezelfde stijl** kan bouwen. Het gaat over *uiterlijk en gedrag
van de interface*, niet over wat de app doet. Alle waarden hieronder zijn de echte waarden uit `css/style.css`,
`js/icons.js` en `index.html`; neem ze letterlijk over.

**De stijl in één zin:** warm, rustig en ruim: crèmekleurige achtergronden, één terracotta accentkleur, een
serif-koptype naast een neutraal UI-lettertype, dunne lijn-icoontjes, zachte afgeronde kaarten met een haarlijntje
als rand en nauwelijks schaduw, in de geest van Claude's eigen interface.

---

## 1. Principes

1. **Warm en rustig, niet "techy".** Geen felle kleuren, geen harde zwarte/witte vlakken, geen blauw. Alles heeft een warme,
   licht gele/bruine ondertoon (crème, zand, steenbruin).
2. **Eén accentkleur.** Terracotta (`--accent`) is het enige "merkkleur"-element: primaire knop, actieve navigatie,
   focusring, schakelaar aan, logo-blokje. Semantische kleuren (groen/rood/amber) worden alleen gebruikt voor betekenis
   (goed/slecht/let op), nooit als decoratie.
3. **Ruimte en hiërarchie in plaats van decoratie.** Structuur komt uit witruimte, dunne randen, kleine
   hoofdletter-labels en het contrast tussen serif-cijfers en sans-serif tekst; niet uit schaduwen, verlopen of vette kleurvlakken.
4. **Mobile-first.** De app wordt vooral op een telefoon (ook als geïnstalleerde PWA) gebruikt. Ontwerp eerst voor ±360–430 px breed,
   duim-bediening met een vaste onderbalk; het desktopontwerp is de uitbreiding (zijbalk vanaf 1024 px).
5. **Licht én donker.** Beide thema's zijn volledig uitgewerkt en volgen automatisch de systeeminstelling (`prefers-color-scheme`).
   Gebruik daarom **altijd CSS-variabelen**, nooit vaste kleurwaarden in componenten.
6. **Geen frameworks of libraries.** Plain HTML + CSS + vanilla JS. Geen UI-kit, geen icon-font, geen chart-library:
   icoontjes en grafieken zijn zelf geschreven inline SVG.
7. **Nederlands, vriendelijk en duidelijk** (zie §12).

---

## 2. Kleurtokens

Alles staat als CSS custom properties op `:root`. Gebruik uitsluitend deze namen.

### Licht (standaard)

| Token | Waarde | Gebruik |
|---|---|---|
| `--bg` | `#FAF9F6` | pagina-achtergrond (warm gebroken wit) |
| `--bg-alt` | `#F0EEE6` | zijbalk, tweede vlak |
| `--surface` | `#FFFFFF` | kaarten, topbalk, onderbalk, invoervelden |
| `--surface-sunken` | `#F5F3EC` | "ingedrukte" vlakken: tabelkop, logregels, lege grafiek, segmented-control-achtergrond, hover |
| `--border` | `#E6E2D8` | haarlijn-randen van kaarten, tabellen, scheidingslijnen |
| `--border-strong` | `#D8D3C4` | randen van invoervelden en secundaire knoppen, uit-stand van schakelaar |
| `--text` | `#30291F` | hoofdtekst (warm donkerbruin, nooit puur zwart) |
| `--text-muted` | `#6F6A5C` | labels, secundaire tekst, inactieve navigatie |
| `--text-faint` | `#98937F` | hints, ondertitels, tijdstempels, grafiek-labels |
| `--accent` | `#D97757` | terracotta: primaire knop, actief icoon, focus, schakelaar aan |
| `--accent-hover` | `#C4643F` | hover van de primaire knop |
| `--accent-soft` | `#F3E3D8` | zachte accentachtergrond: actieve nav, badge "in bezit", focusring |
| `--accent-text` | `#7A3B1F` | tekst op `--accent-soft` en links |
| `--success` | `#4F7942` | positief (winst, actief, stijgend): gedempt mosgroen |
| `--success-soft` | `#E6EFDF` | achtergrond van succes-badges/-meldingen |
| `--danger` | `#C0392B` | negatief (verlies, fout, dalend) |
| `--danger-soft` | `#F8E3E0` | achtergrond van fout-meldingen / destructieve knop |
| `--warn` | `#9A6B14` | let op / in afwachting: amber |
| `--warn-soft` | `#F5EBD3` | achtergrond van waarschuwingen |

### Donker (`@media (prefers-color-scheme: dark)`)

Alleen de tokens die veranderen; `--success`, `--danger`, `--warn` blijven gelijk.

| Token | Waarde |
|---|---|
| `--bg` | `#21201C` |
| `--bg-alt` / `--surface` | `#2A2822` |
| `--surface-sunken` | `#24231F` |
| `--border` | `#3B382F` |
| `--border-strong` | `#4A4638` |
| `--text` | `#EDE9DD` |
| `--text-muted` | `#B0AA97` |
| `--text-faint` | `#837D6A` |
| `--accent` | `#E28B6D` |
| `--accent-hover` | `#EDA486` |
| `--accent-soft` | `#3D2A21` |
| `--accent-text` | `#F0B79A` |
| `--success-soft` | `#26301F` |
| `--danger-soft` | `#3A211E` |
| `--warn-soft` | `#362D19` |
| `--shadow-sm` | `0 1px 2px rgba(0,0,0,.3)` |
| `--shadow` | `0 4px 20px rgba(0,0,0,.35)` |

### Vormtokens

```css
--radius-sm: 8px;   /* knoppen, invoervelden, meldingen, logregels, navigatie-items */
--radius:   14px;   /* kaarten, tabellen, banners */
--radius-lg: 20px;  /* grote panelen, de login-kaart */
/* pillen (badges, segmented control): border-radius: 100px */
--shadow-sm: 0 1px 2px rgba(48, 41, 31, 0.06);   /* kaarten in rust */
--shadow:    0 4px 16px rgba(48, 41, 31, 0.08);  /* zwevende elementen, hover, toasts, login-kaart */
```

**Betekenis van kleur (houd dit consistent):**
groen = winst/actief/stijgend · rood = verlies/fout/dalend · amber = wachten/let op · terracotta = merk, selectie, "in bezit"/eigen actie ·
grijs/zand = neutraal, inactief, zijwaarts. Positieve en negatieve bedragen worden gekleurd met `--success` / `--danger`,
een nul of onbekende waarde met `--text-faint`.

---

## 3. Typografie

Twee lettertypes, geladen via Google Fonts (`preconnect` + één `<link>`):

```html
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
```

```css
--font-ui:      'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
--font-display: 'Fraunces', Georgia, serif;
```

- **Fraunces (serif, gewicht 600)** = "display": alle koppen (`h1`–`h3`), het merknaam-/logo-label, en **alle grote cijfers**
  (`.stat-value`). Dit serif-contrast met de neutrale Inter is het belangrijkste typografische kenmerk. `letter-spacing: -0.01em`, marge onder koppen 4 px.
- **Inter (sans)** = al het andere: lopende tekst, labels, knoppen, tabellen, navigatie.
- Body: `15px / 1.5`, `-webkit-font-smoothing: antialiased`, kleur `--text`, `overflow-x: hidden`.

| Element | Grootte / gewicht | Stijl |
|---|---|---|
| Paginatitel `h1` (`.page-header h1`) | 24 px, Fraunces 600 | met ondertitel `.sub` 13.5 px `--text-muted` eronder |
| Login-titel | 22 px Fraunces | |
| Kaarttitel `.card h2` | 15 px Fraunces | (muntkaart: 16 px) |
| Grote waarde `.stat-value` | 26 px Fraunces 600 (hero-waarde 32 px) | kleur `--text`, of `.positive`/`.negative` |
| Stat-label `.stat-label` | 12.5 px Inter 600 | **HOOFDLETTERS**, `letter-spacing: .04em`, `--text-muted` |
| Sectietitel `.section-title` | 13 px Inter 700 | **HOOFDLETTERS**, `letter-spacing: .05em`, `--text-muted`, marge `28px 0 12px` |
| Sub-tekst `.stat-sub` | 12.5 px | `--text-faint`, marge-top 4 px |
| Formulierlabel | 13 px Inter 600 | `--text-muted`, 6 px onder |
| Hint onder veld | 12 px | `--text-faint` |
| Knop | 14 px Inter 600 (`.btn-sm`: 13 px) | |
| Tabelcel | 13.5 px | kop: 12 px 600 hoofdletters, `letter-spacing: .03em` |
| Badge | 12 px Inter 600 | |
| Navigatie (zijbalk) | 14 px Inter 500 | onderbalk: 11 px |
| Kleine grafiektekst | 9 px (SVG) | `--text-faint` |
| Logregel / metrics-rij | 13 – 13.5 px | tijd: 11.5 px `--text-faint` |

Links: `color: var(--accent-text)`. Monospace (`monospace`, 13.5 px) alleen voor sleutels/codes.

**Getalweergave:** bedragen in euro's met Nederlandse notatie (`Intl.NumberFormat('nl-NL', {style:'currency', currency:'EUR'})`
→ `€ 1.234,56`), prijzen per stuk met meer decimalen naarmate ze kleiner zijn (≥ 1 → 2, ≥ 0,01 → 4, ≥ 0,0001 → 6, anders 8),
percentages met teken (`+1,25%`), datums kort (`toLocaleString('nl-NL', {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'})`).
Tekst die uit data komt wordt altijd ge-escaped vóór het in `innerHTML` gaat.

---

## 4. Spacing, vorm en algemene regels

- **Schaal:** 4 / 6 / 8 / 10 / 12 / 14 / 16 / 18 / 20 / 22 / 28 px. Standaard gat tussen kaarten: **14 px** (grid) of **20 px** (verticaal tussen blokken).
- **Randen:** altijd `1px solid var(--border)` (kaarten, tabellen, scheidingslijnen) of `--border-strong` (invoer, secundaire knop).
- **Schaduw:** kaarten `--shadow-sm`; alleen zwevende dingen (toasts, banners, login-kaart, hover) `--shadow`.
- **Hoeken:** zie vormtokens. Nooit scherpe hoeken, nooit volledig cirkelvormige knoppen (behalve pillen).
- **Overgangen:** `.15s` op kleur/rand/schaduw/achtergrond; in- en uitschuiven van meldingen `.25s`. Geen bouncy animaties.
- **Geen** verlopen behalve: (a) de zachte radiale gloed op de login-achtergrond, (b) de trendmeter (§7).
- Scheidingslijn tussen blokken: `.divider { height:1px; background: var(--border); margin: 22px 0; }`.

---

## 5. Lay-out en navigatie (app-shell)

```
┌ .layout (flex, min-height 100vh) ─────────────────────────────┐
│ .sidebar (alleen ≥1024px)  │ .main                             │
│  brand (logo + naam)       │  .topbar (alleen <1024px, sticky) │
│  nav-links                 │  #page-content.page               │
│  (spacer)                  │                                   │
│  user-box + uitloggen      │                                   │
└────────────────────────────┴───────────────────────────────────┘
 .bottom-nav (fixed, alleen <1024px)
```

**Breekpunt:** `@media (min-width: 1024px)` — daarboven zijbalk, geen topbalk, geen onderbalk. Daaronder topbalk + onderbalk.

**Merk-blokje (logo):** afgerond vierkant in `--accent` met een witte serif "B" (Fraunces 600). Maten: 36 px / radius 10 (login), 30 px / radius 9
(zijbalk), 26 px / radius 8 (topbalk mobiel), 56 px / radius 16 (laadscherm, pulseert: `opacity .7`, `scale .94`, 1.4 s). Erna de merknaam in Fraunces 600.

**Zijbalk (desktop):** breedte `232px`, achtergrond `--bg-alt`, rechterrand `1px solid var(--border)`, padding `20px 14px`, kolom.
Onderaan een `.user-box` (bovenrand, e-mail in 12.5 px `--text-faint`, eventueel badge "Beheerder", en een Uitloggen-navlink).

**Navigatie-item `.nav-link`:** `display:flex; gap:11px; padding:10px 12px; border-radius:8px; font-size:14px; font-weight:500;`
kleur `--text-muted`, geen rand/achtergrond. Hover: achtergrond `--surface-sunken`, tekst `--text`.
**Actief:** achtergrond `--accent-soft`, tekst `--accent-text`. Icoon (20 px) links van het label.

**Topbalk (mobiel):** `position: sticky; top: 0; z-index: 5`, achtergrond `--surface`, onderrand `1px solid var(--border)`, padding `16px 20px`;
links logo + naam, rechts alleen het uitlog-icoon.

**Onderbalk (mobiel, hoofdnavigatie):** `position: fixed; bottom:0; left:0; right:0; z-index:10`, achtergrond `--surface`, bovenrand
`1px solid var(--border)`, padding `6px 4px calc(6px + env(safe-area-inset-bottom))`. Items in een rij, gelijk verdeeld, **icoon boven label**
(`flex-direction: column; gap: 3px; font-size: 11px; text-align: center`). Actief item: géén achtergrond, tekst `--accent-text`, **icoon-stroke `--accent`**.
`.main` krijgt `padding-bottom: 76px` zodat inhoud niet achter de balk verdwijnt.
Items (in volgorde): Dashboard, Munten, Transacties, Instellingen, (Beheer alleen voor beheerders), elk met eigen icoon.

**Pagina-container `.page`:** `max-width: 980px; margin: 0 auto; padding: 22px 20px 40px` (≥1024px: `max-width 1080px; padding 34px 40px 50px`).

**Paginakop `.page-header`:** flex, `justify-content: space-between; align-items:center; gap:12px; flex-wrap: wrap; margin-bottom:20px`.
Links `h1` + `.sub`; rechts optioneel een actie of segmented control.

**Routing:** hash-routes (`#/dashboard`, `#/coins/12`), één `#page-content` die per route opnieuw wordt opgebouwd; terugknoppen zijn een
`.nav-link`-achtig "← Terug naar …" met het pijl-icoon.

---

## 6. Componenten

### Kaart
```css
.card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; box-shadow: var(--shadow-sm); }
.clickable-card { cursor: pointer; transition: border-color .15s, box-shadow .15s; }
.clickable-card:hover { border-color: var(--accent); box-shadow: var(--shadow); }
```
Grid: `.grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:20px }`
(muntkaarten `minmax(260px,1fr)`; twee statistiekkaarten naast elkaar op mobiel: `grid-template-columns: 1fr 1fr`).
Een "hero"-kaart staat 100 % breed bovenaan met grote serif-waarde (32 px), daaronder 12.5 px subregel en een grafiek.

**Statistiek-kaart:** `.stat-label` (klein hoofdletter-label) → `.stat-value` (grote serif) → `.stat-sub` (kleine grijze uitleg).

**Sleutel/waarde-rijen** (`.coin-metrics`): kolom; elke `.row` = flex `space-between`, `padding: 6px 0`, bovenrand `1px solid var(--border)`, 13.5 px;
label links `--text-muted`, waarde rechts `font-weight: 600`, rechts uitgelijnd.

**Twee waarden op één regel** (`.stat-sub-row`): flex `space-between`, 12.5 px `--text-faint`; rechterdeel rechts uitgelijnd.

### Knoppen
```css
.btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 18px; border-radius:8px; border:1px solid transparent; font-weight:600; font-size:14px; cursor:pointer; transition: background .15s, border-color .15s, opacity .15s; }
.btn-primary   { background: var(--accent); color:#fff; }            /* hover: --accent-hover; disabled: opacity .55 */
.btn-secondary { background: var(--surface); border-color: var(--border-strong); color: var(--text); }  /* hover: --surface-sunken */
.btn-danger    { background: var(--danger-soft); color: var(--danger); }  /* hover: opacity .85 — bewust zacht, geen vol rood */
.btn-block { width:100%; }   .btn-sm { padding:6px 12px; font-size:13px; }
```
Per scherm maximaal één primaire knop. Een icoon (20 px) mag links van het label staan. Tijdens laden vervangt een `.spinner`
(16 px ring, 2 px, draait .6 s) het label (wit in primair, grijs in secundair/danger).

### Formulieren
- `.field { margin-bottom:16px }` met `label` (13 px 600 `--text-muted`) erboven en optioneel `.hint` (12 px `--text-faint`) eronder.
- Inputs/selects: `width:100%; padding:10px 12px; border:1px solid var(--border-strong); border-radius:8px; background:var(--surface); color:var(--text)`.
  **Focus:** `border-color: var(--accent)` + `box-shadow: 0 0 0 3px var(--accent-soft)`, `outline:none`.
- Checkbox: 18 × 18 px met `accent-color: var(--accent)`, in een `.checkbox-row` (flex, gap 10).
- **Schakelaar (toggle)** `.switch`: 44 × 26 px pil; uit = `--border-strong`, aan = `--accent`; witte knop 20 px (`top/left 3px`, verschuift 18 px, `.15s`, `--shadow-sm`).
  Gebruik met een tekstlabel links in een `.switch-row` (flex, `space-between`, gap 12).
- Formulierkaart: `.form-card { max-width: 520px }`.

### Badges (status-pillen)
`display:inline-flex; gap:5px; padding:3px 10px; border-radius:100px; font-size:12px; font-weight:600;` — kleurparen:
groen (`--success-soft` / `--success`) = actief/stijgend · terracotta (`--accent-soft` / `--accent-text`) = in bezit, beheerder ·
amber (`--warn-soft` / `--warn`) = wacht/in afwachting · neutraal (`--surface-sunken` / `--text-muted`) = inactief/zijwaarts ·
rood (`--danger-soft` / `--danger`) = dalend. Badges met richting krijgen een pijl-teken voor het label (`↗`, `↘`, `→`).

### Segmented control (periodekeuze)
```css
.range-toggle { display:inline-flex; background: var(--surface-sunken); border-radius:100px; padding:3px; gap:2px; }
.range-toggle button { border:0; background:transparent; color:var(--text-muted); font:inherit; font-size:12px; font-weight:600; padding:5px 12px; border-radius:100px; cursor:pointer; }
.range-toggle button.active { background: var(--surface); color: var(--text); box-shadow: var(--shadow-sm); }
```
Knoppen zijn kort ("1u", "6u", "12u", "24u"). De keuze wordt in `localStorage` onthouden (altijd in `try/catch`).

### Meldingen
- **Inline `.alert`** (`padding:10px 14px; border-radius:8px; font-size:13.5px; display:flex; gap:8px`): `.alert-error` (danger-soft/danger), `.alert-success`, `.alert-warn`, met icoon (`alert`, `check`).
- **Toast** (`.toast-wrap`: fixed, onderaan gecentreerd, `bottom:90px` boven de onderbalk, `24px` op desktop, breed `92%`/max 380 px): donkere pil
  (`background: var(--text); color: var(--bg)`), variant `.toast-success` (groen, wit) en `.toast-error` (rood, wit). Verdwijnt na 4 s.
- **Banner bovenin** (`.trade-banner`, fixed bovenaan met `env(safe-area-inset-top)`, max 460 px): kaartachtig met `--radius`, `--shadow`,
  bij "koop" `--accent-soft`/`--accent-text`, bij "verkoop" `--success-soft`/`--success`; icoon links, titel + 12.5 px detail, sluit-kruisje rechts;
  glijdt .25 s van boven in, klikbaar.
- **Installatie-banner** (`.install-banner`): onderin boven de onderbalk, `--surface` met `--border-strong`, `--radius`, `--shadow`.

### Tabellen
In een `.table-wrap` (`overflow-x:auto; border:1px solid var(--border); border-radius:14px; background:var(--surface)`). `table` heeft `min-width:560px`
zodat hij op mobiel horizontaal scrolt. `th`: `--surface-sunken`, 12 px hoofdletters `--text-muted`; `th, td`: `padding: 11px 16px`, `border-bottom: 1px solid var(--border)`,
`white-space: nowrap`; laatste rij geen rand. Winst/verlies-cellen vet in groen/rood.

### Logregels
`.log-list` (kolom, gap 8, max-hoogte 420 px, scrollt). Elke `.log-item`: `--surface-sunken`, radius 8, padding `10px 12px`, 13 px; links een **8 px bolletje**
(succes groen, fout rood, waarschuwing amber, info `--text-faint`), tekst flexibel, rechts de tijd (11.5 px `--text-faint`).

### Lege toestand
`.empty-state`: gecentreerd, `padding:40px 20px`, `--text-muted`; erboven een 48 px vierkantje (`--surface-sunken`, radius 14) met een icoon; daaronder een korte zin en eventueel een primaire knop.
Een lege grafiek is een `.sparkline-empty` (84 px hoog, `--surface-sunken`, radius 8, 12 px `--text-faint`).

### Login/registratie
Gecentreerde kaart (`max-width:380px; padding:36px 32px; radius 20; --shadow`) op een achtergrond met
`radial-gradient(circle at 20% 0%, var(--accent-soft), transparent 55%), var(--bg)`. Boven het logo + naam, dan titel, korte ondertitel, velden,
een volbreed primair knop en onderaan een tekstknop ("Nog geen account? Registreren") in `--accent-text` 600.

---

## 7. Grafieken (zelf getekend, geen library)

Alle grafieken zijn inline SVG, gegenereerd met JavaScript.

**Sparkline (koers-/waardegrafiek):**
- `viewBox="0 0 300 84"`, `preserveAspectRatio="none"`, `width:100%; height:84px`, marges `padX 4`, `padY 20`.
- **Eén lijn**, `stroke: var(--success)`, `stroke-width: 2`, `linejoin/linecap: round`, `vector-effect: non-scaling-stroke`, **geen vulling, geen assen, geen rasterlijnen, geen tooltips**.
- Een **stip** (`r=3.5`, `fill: var(--success)`) op het laatste punt.
- Alleen twee tekstlabels: de **hoogste** en **laagste** waarde, 9 px `--text-faint` (Inter), boven de piek resp. onder het dal, uitgelijnd naar de kant
  waar ruimte is (`text-anchor` start/middle/end). Waardeformat hetzelfde als elders (`€`, nl-NL).
- De schaal loopt van min tot max van de zichtbare reeks. Bij weinig punten een nette lege-toestand ("Nog geen data").
- Plaatsing: binnen de kaart, `.sparkline-wrap { margin: 10px 0 2px }`.
- Periodekeuze via de segmented control (§6).

**Trendmeter (richting):** een 8 px hoge pil met `linear-gradient(90deg, var(--danger) 0%, var(--surface-sunken) 35%, var(--surface-sunken) 65%, var(--success) 100%)`
(`opacity .85`), een 4 × 16 px **markering** (`--text`, radius 2) op de score-positie, en 11 px labels eronder (links/midden/rechts, `--text-faint`).
Daaronder een opsomming (`padding-left:18px; font-size:14px; line-height:1.5`) met korte toelichtingen.

Gebruik voor nieuwe grafiektypen dezelfde principes: minimalistisch, één kleur uit de semantische set, dunne lijnen, geen overbodige labels.

---

## 8. Iconen

- **Bron:** een eigen, handgeschreven set **inline SVG-lijnicoontjes** in `js/icons.js` (object `ICONS`, opgehaald met `icon('naam')`).
  Geen externe icon-font, geen CDN, geen emoji's als icoon. De stijl komt overeen met **Feather/Lucide** (rustige, dunne outline-iconen);
  een ontbrekend icoon maak je in dezelfde stijl (of neem het uit Lucide, ISC-licentie, en pas het aan onderstaande regels aan).
- **Uitvoering:** `viewBox="0 0 24 24"`, `class="icon"`, CSS:
  ```css
  svg.icon { width:20px; height:20px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
  ```
  Dus: **alleen contouren, geen vulling**, lijndikte 1.8, afgeronde uiteinden, kleur erft van de tekst (`currentColor`), standaard 20 px.
  Kleur wordt gestuurd via de omliggende tekstkleur (bv. actief icoon in onderbalk: `stroke: var(--accent)`).
- **Aanwezige iconen:** `dashboard` (vier blokjes), `settings` (tandwiel), `trades` (staafjes met lijn), `admin` (schild met vinkje), `logout` (deur met pijl),
  `key` (sleutel), `coin` (cirkel met €/$-teken), `trendUp`/`trendDown` (pijl omhoog/omlaag), `check`, `x`, `plus`, `refresh`, `mail`, `lock`, `alert` (waarschuwingsdriehoek),
  `box` (lege-toestand), `wallet`, `arrowLeft`, `bell`.
- **Gebruik:** navigatie-items (icoon + label), knoppen (icoon links van het label, gap 8), lege toestanden (in 48 px vierkantje), meldingen (alert/check), banners.
  Verder bewust spaarzaam: tekst gaat voor icoon. Nooit twee verschillende icoonstijlen mengen.

**App-icoon / PWA-icoon:** terracotta (`#D97757`) afgerond vierkant met een witte, vette "B" in het midden. Bestanden: `icons/icon-192.png`, `icons/icon-512.png`,
`icons/icon-maskable-512.png` (met extra veilige marge), `icons/apple-touch-icon.png`, plus een SVG-data-URI als favicon.
**Meldingsicoon (Android-statusbalk) `icons/badge-96.png`** moet **monochroom met transparante achtergrond** zijn (alleen alfakanaal telt; een gekleurd icoon wordt een wit blok).

---

## 9. PWA / "native" gevoel

- `manifest.json`: `display: standalone`, `background_color` en `theme_color` = `#FAF9F6`, korte naam, bovenstaande iconen.
- `<meta name="theme-color" content="#FAF9F6">`, `viewport-fit=cover`, en de Apple-meta's (`apple-mobile-web-app-capable`, `status-bar-style: default`, `apple-touch-icon`).
- Respecteer **safe-area-insets** (onderbalk: `env(safe-area-inset-bottom)`, banner bovenin: `env(safe-area-inset-top)`).
- Service worker cachet de app-shell (netwerk eerst, cache als terugval); verhoog de cachenaam (`…-vN`) bij elke stijl-/scriptwijziging zodat gebruikers de nieuwe versie krijgen.
- Zelf-verversende pagina's bewaren de scrollpositie bij het bijwerken (geen "springen" naar boven).

---

## 10. Interactie en beweging

- Alles wat klikbaar is, heeft `cursor: pointer` en een merkbare hover/focus (rand naar `--accent`, achtergrond naar `--surface-sunken`, of opacity).
- Focus is altijd zichtbaar (accent-rand + 3 px zachte ring bij invoervelden).
- Tijdens een actie: knop uitzetten (`disabled`, opacity .55) + spinner + tekst als "Inloggen…". Fouten verschijnen als inline `.alert-error` of toast, nooit als `alert()`.
- Laden: eerst de kaarten met "Laden…" of lege grafiek, daarna invullen; laadscherm is het pulserende logo-blokje.
- Beweging is subtiel: `.15s` overgangen, meldingen `.25s` in/uit, spinner en pulse. Geen parallax, geen grote animaties.
- Kleine voorkeuren (gekozen periode, geluid aan/uit) in `localStorage`, altijd met `try/catch` en met een werkende standaard als opslag ontbreekt.

---

## 11. Toegankelijkheid

- Tekstcontrast volgens de tokens (hoofdtekst `--text` op `--bg`/`--surface` ruim boven 4.5:1). Gebruik `--text-faint` alleen voor bijzaken (hints, tijden), niet voor belangrijke informatie.
- Betekenis nooit alleen met kleur: badges hebben tekst (en een pijl), bedragen een teken (+/−), logregels een tekstbericht.
- Segmented controls en schakelaars hebben `aria-pressed` / echte `<input type=checkbox>` / `aria-label`.
- Aanraakdoelen ≥ 40 px hoog op mobiel (knoppen `padding:10px 18px`, navigatie-items `padding:10px 12px`).
- Pagina's mogen niet horizontaal scrollen (alleen `.table-wrap` mag dat); gebruik `min-width:0`, `overflow-wrap:anywhere` bij lange strings.

---

## 12. Taal en toon (microcopy)

- **Taal:** Nederlands, `<html lang="nl">`. Aanspreekvorm **"je/jouw"**, nooit "u".
- **Toon:** vriendelijk, rustig, concreet; legt uit in gewone woorden wat er gebeurt en waarom. Liever een korte uitleg (`.hint` of `.stat-sub`)
  dan vakjargon. Geen uitroeptekens-overload, geen emoji.
- **Labels:** kort en zelfstandig naamwoord ("Budget", "Koop-dip", "Verkoopwinst"); acties als werkwoord ("Munt toevoegen", "Nu controleren", "Verwijderen", "Bewerken").
- **Lege/leeg-states en fouten** zeggen wat er aan de hand is én wat je kunt doen ("Koppel eerst je API-sleutels… Ga naar Instellingen").
- **Informatief vs. advies:** wat alleen ter informatie is, zegt dat letterlijk ("Alleen ter informatie…").
- Hoofdletters alleen aan het begin van een zin; alleen kleine labels (`.stat-label`, `.section-title`, tabelkop) staan in CAPS via CSS.

---

## 13. Niet doen

- Geen blauw, paars of felle kleuren; geen puur zwart (`#000`) of puur wit als pagina-achtergrond (wit is alleen `--surface`).
- Geen harde schaduwen, geen dikke randen, geen verlopen (behalve de twee genoemde), geen glassmorphism.
- Geen icon-fonts, emoji-iconen, UI-frameworks of chart-libraries; geen vaste hex-kleuren in componenten (gebruik de tokens, anders breekt donkere modus).
- Geen tweede accentkleur; groen/rood niet decoratief gebruiken.
- Geen serif voor lopende tekst en geen sans-serif voor koppen of grote cijfers.
- Geen pagina's die alleen op desktop werken; test altijd op ±390 px breed, licht én donker.

---

## 14. Startpunt: minimale basis om een nieuwe app in deze stijl te beginnen

```html
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#FAF9F6">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">   <!-- tokens uit §2–§4 + componenten uit §5–§7 -->
</head>
<body>
  <div class="layout">
    <aside class="sidebar">…</aside>
    <div class="main">
      <div class="topbar">…</div>
      <div id="page-content" class="page">
        <div class="page-header"><div><h1>Titel</h1><div class="sub">Korte omschrijving</div></div></div>
        <div class="card">
          <div class="stat-label">Totaal</div>
          <div class="stat-value">€ 1.234,56</div>
          <div class="stat-sub">Uitleg in gewone taal</div>
        </div>
      </div>
    </div>
    <nav class="bottom-nav">…</nav>
  </div>
</body>
</html>
```

Checklist voor elke nieuwe pagina: tokens gebruikt (geen losse kleuren) · werkt op 390 px · donker thema getest · één primaire knop ·
koppen/cijfers in Fraunces, rest in Inter · iconen uit de eigen SVG-set (20 px, 1.8 stroke, `currentColor`) · Nederlandse, vriendelijke teksten · lege en laad-toestand aanwezig.
