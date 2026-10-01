<div class="page-header"><div><h1>Instellingen</h1><div class="sub">Toegang, koppelingen en bronnen voor je verslagen.</div></div></div>
<?= $tabs ?>

<div class="section-title" style="margin-top:0">Sleutel-link voor het dashboard</div>
<div class="card form-card">
  <p class="sub" style="margin-top:0">Met deze link open je de verslagen zonder in te loggen (alleen lezen, geen toegang tot instellingen). Bewaar hem als bladwijzer of gebruik hem in pushmeldingen.</p>
  <div class="field"><div class="secret"><input type="text" id="view-link" value="<?= e($viewLink) ?>" readonly><button class="btn btn-secondary" type="button" data-copy="view-link">Kopieer</button></div></div>
  <form method="post" action="<?= e(url('settings/regenerate/view')) ?>" data-confirm="Nieuwe sleutel maken? Je oude link werkt dan niet meer."><?= csrf_field() ?>
    <button class="btn btn-secondary btn-sm" type="submit"><?= icon('refresh') ?> Nieuwe sleutel</button></form>
</div>

<div class="section-title">Koppeling voor je AI (MCP en rapporteren)</div>
<div class="card form-card">
  <div class="field"><label for="mcp-url">MCP-server (URL met token)</label>
    <div class="secret"><input type="text" id="mcp-url" value="<?= e($mcpUrl . '?token=' . $agentToken) ?>" readonly><button class="btn btn-secondary" type="button" data-copy="mcp-url">Kopieer</button></div>
    <div class="hint">Voeg dit toe als remote MCP-server. Tools: briefing-plan, mail lezen, agenda, nieuwsbronnen, pagina ophalen en verslag-onderdeel opslaan.</div></div>
  <div class="field"><label for="agent-token">Agent-token (Bearer)</label>
    <div class="secret"><input type="text" id="agent-token" value="<?= e($agentToken) ?>" readonly><button class="btn btn-secondary" type="button" data-copy="agent-token">Kopieer</button></div>
    <div class="hint">Of stuur <code>Authorization: Bearer …</code> mee. Verslagen opslaan kan ook met een <code>POST</code> naar <code><?= e($apiUrl) ?></code>.</div></div>
  <form method="post" action="<?= e(url('settings/regenerate/agent')) ?>" data-confirm="Nieuwe token maken? Je MCP-koppeling moet daarna worden aangepast."><?= csrf_field() ?>
    <button class="btn btn-secondary btn-sm" type="submit"><?= icon('refresh') ?> Nieuwe token</button></form>
</div>

<div class="section-title">Wachtwoord wijzigen</div>
<div class="card form-card">
  <form method="post" action="<?= e(url('settings/password')) ?>"><?= csrf_field() ?>
    <div class="field"><label for="cp">Huidig wachtwoord</label><input type="password" id="cp" name="current_password" autocomplete="current-password" required></div>
    <div class="form-row">
      <div class="field"><label for="np">Nieuw wachtwoord</label><input type="password" id="np" name="new_password" autocomplete="new-password" minlength="10" required></div>
      <div class="field"><label for="npc">Herhaal</label><input type="password" id="npc" name="new_password_confirmation" autocomplete="new-password" required></div>
    </div>
    <button class="btn btn-secondary" type="submit">Wachtwoord wijzigen</button>
  </form>
</div>
