<?php
/** @var array|null $acc */
$a = $acc ?? [];
$val = static fn (string $k, mixed $d = '') => e((string) ($a[$k] ?? $d));
$sec = static fn (string $k, string $d, string $v) => (($a[$k] ?? $d) === $v) ? 'selected' : '';
$action = $acc ? url('settings/mail/' . $acc['id']) : url('settings/mail');
$uid = 'm' . ($acc['id'] ?? 'new');
?>
<form method="post" action="<?= e($action) ?>" data-mail-form>
  <?= csrf_field() ?>
  <?php if (!$acc): ?>
  <div class="field"><label for="<?= $uid ?>-preset">Voorinstelling</label>
    <select id="<?= $uid ?>-preset" data-preset>
      <option value="">Zelf invullen</option>
      <option value="icloud">iCloud Mail</option>
    </select>
    <div class="hint">iCloud: gebruik je volledige iCloud-adres en een app-specifiek wachtwoord (appleid.apple.com → Aanmelden en beveiliging).</div></div>
  <?php endif; ?>
  <div class="form-row">
    <div class="field"><label for="<?= $uid ?>-label">Naam</label><input type="text" id="<?= $uid ?>-label" name="label" value="<?= $val('label') ?>" placeholder="Bijv. iCloud privé" required></div>
    <div class="field"><label for="<?= $uid ?>-protocol">Soort</label>
      <select id="<?= $uid ?>-protocol" name="protocol"><option value="imap" <?= $sec('protocol', 'imap', 'imap') ?>>IMAP</option><option value="pop3" <?= $sec('protocol', 'imap', 'pop3') ?>>POP3</option></select></div>
  </div>
  <div class="form-row">
    <div class="field" style="grid-column: span 2"><label for="<?= $uid ?>-host">Server</label><input type="text" id="<?= $uid ?>-host" name="host" value="<?= $val('host') ?>" placeholder="imap.voorbeeld.nl" required></div>
    <div class="field"><label for="<?= $uid ?>-port">Poort</label><input type="number" id="<?= $uid ?>-port" name="port" value="<?= $val('port', 993) ?>" min="1" max="65535" required></div>
    <div class="field"><label for="<?= $uid ?>-security">Beveiliging</label>
      <select id="<?= $uid ?>-security" name="security"><option value="ssl" <?= $sec('security', 'ssl', 'ssl') ?>>SSL/TLS</option><option value="starttls" <?= $sec('security', 'ssl', 'starttls') ?>>STARTTLS</option><option value="none" <?= $sec('security', 'ssl', 'none') ?>>Geen</option></select></div>
  </div>
  <div class="switch-row"><label for="<?= $uid ?>-certs">Alle certificaten accepteren</label>
    <span class="switch"><input type="checkbox" id="<?= $uid ?>-certs" name="accept_all_certs" value="1" <?= $acc && !$a['verify_cert'] ? 'checked' : '' ?>><span class="track"></span></span></div>
  <div class="hint" style="margin:-8px 0 14px">Alleen aanzetten voor servers met een zelfondertekend of verlopen certificaat.</div>
  <div class="form-row">
    <div class="field"><label for="<?= $uid ?>-user">Gebruikersnaam</label><input type="text" id="<?= $uid ?>-user" name="username" value="<?= $val('username') ?>" autocomplete="off" required></div>
    <div class="field"><label for="<?= $uid ?>-pass">Wachtwoord</label><input type="password" id="<?= $uid ?>-pass" name="password" autocomplete="new-password" <?= $acc ? 'placeholder="Leeg laten om te behouden"' : 'required' ?>></div>
  </div>
  <div class="field"><label for="<?= $uid ?>-folder">Map (alleen IMAP)</label><input type="text" id="<?= $uid ?>-folder" name="folder" value="<?= $val('folder', 'INBOX') ?>"></div>

  <div class="section-title" style="margin-top:8px">Uitgaande mail (SMTP, optioneel)</div>
  <div class="form-row">
    <div class="field" style="grid-column: span 2"><label for="<?= $uid ?>-sh">SMTP-server</label><input type="text" id="<?= $uid ?>-sh" name="smtp_host" value="<?= $val('smtp_host') ?>" placeholder="smtp.voorbeeld.nl"></div>
    <div class="field"><label for="<?= $uid ?>-sp">Poort</label><input type="number" id="<?= $uid ?>-sp" name="smtp_port" value="<?= $val('smtp_port', 587) ?>" min="1" max="65535"></div>
    <div class="field"><label for="<?= $uid ?>-ss">Beveiliging</label>
      <select id="<?= $uid ?>-ss" name="smtp_security"><option value="starttls" <?= $sec('smtp_security', 'starttls', 'starttls') ?>>STARTTLS</option><option value="ssl" <?= $sec('smtp_security', 'starttls', 'ssl') ?>>SSL/TLS</option><option value="none" <?= $sec('smtp_security', 'starttls', 'none') ?>>Geen</option></select></div>
  </div>
  <div class="switch-row"><label for="<?= $uid ?>-sc">Alle certificaten accepteren (SMTP)</label>
    <span class="switch"><input type="checkbox" id="<?= $uid ?>-sc" name="smtp_accept_all_certs" value="1" <?= $acc && !$a['smtp_verify_cert'] ? 'checked' : '' ?>><span class="track"></span></span></div>
  <div class="form-row">
    <div class="field"><label for="<?= $uid ?>-su">SMTP-gebruikersnaam</label><input type="text" id="<?= $uid ?>-su" name="smtp_username" value="<?= $val('smtp_username') ?>" autocomplete="off"></div>
    <div class="field"><label for="<?= $uid ?>-spw">SMTP-wachtwoord</label><input type="password" id="<?= $uid ?>-spw" name="smtp_password" autocomplete="new-password" <?= $acc && !empty($a['smtp_password_enc']) ? 'placeholder="Leeg laten om te behouden"' : '' ?>></div>
  </div>
  <div class="hint" style="margin:-6px 0 14px">De SMTP-gegevens worden alvast bewaard. Dagstart leest nu alleen mail; verzenden doet het (nog) niet.</div>
  <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $acc ? 'Opslaan' : 'Account toevoegen' ?></button>
</form>
