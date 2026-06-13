<section class="hero">
    <div class="hero-glow"></div>
    <div class="container hero-grid">
        <div class="hero-copy reveal">
            <span class="eyebrow"><i></i> Een nieuw dorpsinitiatief</span>
            <h1>Komt er een <span>Sportweek</span> in ’t Zand?</h1>
            <p class="lead">Een week vol sport, teamgevoel, gezelligheid en ludieke spellen. Wij onderzoeken of er genoeg animo is om dit samen met inwoners, verenigingen, vrijwilligers en sponsoren mogelijk te maken.</p>
            <div class="button-row">
                <a class="button primary" href="<?= e(url('interesse')) ?>">Ik heb interesse <span>→</span></a>
                <a class="button secondary" href="<?= e(url('sponsoren')) ?>">Ik wil sponsor worden</a>
                <a class="text-link" href="<?= e(url('vrijwilligers')) ?>">Ik wil helpen →</a>
            </div>
            <p class="hero-note"><strong>100 geïnteresseerden</strong> is ons eerste doel. Dan zetten we de volgende stap.</p>
        </div>
        <div class="hero-visual reveal">
            <div class="sport-card card-main"><span class="number">01</span><div class="ball">●</div><strong>Samen in beweging</strong><small>Sport · Spel · Gezelligheid</small></div>
            <div class="sport-card card-float"><strong><?= $counts['interest'] ?></strong><span>mensen enthousiast</span></div>
            <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        </div>
    </div>
</section>

<section class="stats-section" aria-label="Actuele aanmeldingen">
    <div class="container stats-grid">
        <?php foreach ([
            ['interest', 'Geïnteresseerden', '↗'], ['teams', 'Mogelijke teams', '◉'],
            ['volunteers', 'Vrijwilligers', '✦'], ['sponsors', 'Sponsoraanvragen', '◆'],
        ] as [$key, $label, $icon]): ?>
        <article class="stat-card reveal"><span class="stat-icon"><?= $icon ?></span><strong data-count="<?= $counts[$key] ?>"><?= $counts[$key] ?></strong><span><?= $label ?></span></article>
        <?php endforeach; ?>
    </div>
</section>

<section id="concept" class="section">
    <div class="container split">
        <div><span class="eyebrow"><i></i> Het concept</span><h2>Een week die het hele dorp <span>verbindt</span></h2></div>
        <div class="section-copy"><p>Sportweken in omliggende dorpen zoals Waarland en Langedijk laten zien wat er ontstaat wanneer teams, verenigingen en vrijwilligers samen de schouders eronder zetten.</p><p>We denken aan sportieve avonden, ludieke spellen en een gezamenlijke finale. Maar eerst willen we weten: <strong>ziet ’t Zand dit zitten?</strong></p></div>
    </div>
    <div class="container feature-grid">
        <article class="feature-card reveal"><span>01</span><div class="feature-icon">⚡</div><h3>Sportief</h3><p>Voor fanatiekelingen én gezellige meedoeners. Presteren mag, plezier staat voorop.</p></article>
        <article class="feature-card reveal featured"><span>02</span><div class="feature-icon">◎</div><h3>Samen</h3><p>Inwoners, buurten, vriendengroepen, bedrijven en verenigingen bouwen dit samen op.</p></article>
        <article class="feature-card reveal"><span>03</span><div class="feature-icon">✦</div><h3>Dorps</h3><p>Herkenbaar, laagdrempelig en passend bij ’t Zand. Een initiatief van en voor het dorp.</p></article>
    </div>
</section>

<section id="activiteiten" class="section activities">
    <div class="container section-heading"><span class="eyebrow"><i></i> Mogelijke activiteiten</span><h2>Voor ieder team een <span>uitdaging</span></h2><p>Een eerste schets van wat er mogelijk is. Jouw idee kan hier straks ook tussen staan.</p></div>
    <div class="container activity-grid">
        <?php foreach ([
            ['⚽','Voetbalchallenge'], ['🎾','Tennisactiviteiten'], ['🏐','Volleybal'],
            ['💧','Waterspellen'], ['🏃','Hindernis- / stormbaan'], ['?','Pubquiz'],
            ['◎','Behendigheidsspellen'], ['🤝','Ludieke teamopdrachten'], ['🏆','Grote finale-zeskamp'],
        ] as $index => [$icon, $name]): ?>
            <article class="activity-card reveal"><span class="activity-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><div><?= $icon ?></div><h3><?= e($name) ?></h3></article>
        <?php endforeach; ?>
    </div>
    <div class="container notice"><span>💡</span><p><strong>Het programma maken we samen.</strong><br>Het definitieve programma wordt bepaald op basis van samenwerking met verenigingen en ideeën uit het dorp.</p><a href="<?= e(url('idee')) ?>">Deel jouw idee →</a></div>
</section>

<section class="section steps">
    <div class="container section-heading"><span class="eyebrow"><i></i> Van idee naar sportweek</span><h2>Hoe werkt het?</h2></div>
    <div class="container steps-list">
        <?php foreach ([
            ['Interesse peilen','We verzamelen reacties uit het dorp en kijken of we ons eerste doel van 100 geïnteresseerden halen.'],
            ['Vrijwilligers & sponsoren','We brengen mensen en lokale ondernemers samen die willen helpen of bijdragen.'],
            ['Verenigingen benaderen','Samen onderzoeken we welke sporten, locaties en activiteiten haalbaar zijn.'],
            ['Informatieavond','Bij voldoende interesse nodigen we iedereen uit om plannen en ideeën te bespreken.'],
            ['Besluit eerste editie','Pas daarna besluiten we samen of en hoe een eerste Sportweek ’t Zand doorgaat.'],
        ] as $index => [$heading, $copy]): ?>
        <article class="step reveal"><span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><div><h3><?= e($heading) ?></h3><p><?= e($copy) ?></p></div><b><?= $index < 4 ? '↓' : '✓' ?></b></article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section final-cta">
    <div class="container cta-panel reveal"><span class="eyebrow light"><i></i> Jouw stem telt</span><h2>Laat weten of jij Sportweek<br><span>’t Zand ziet zitten.</span></h2><p>Invullen duurt minder dan twee minuten. Geen definitieve inschrijving, wel een belangrijk signaal.</p><div class="button-row centered"><a class="button dark" href="<?= e(url('interesse')) ?>">Ja, ik heb interesse →</a><a class="button ghost" href="<?= e(url('vrijwilligers')) ?>">Ik wil meehelpen</a></div></div>
</section>
