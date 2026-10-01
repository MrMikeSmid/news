<?php
/** @var array|null $acc */
$a = $acc ?? [];
$val = static fn (string $k, mixed $d = '') => e((string) ($a[$k] ?? $d));
$uid = 'c' . ($acc['id'] ?? 'new');
?>
<form method="post" action="<?= e($acc ? url('settings/calendar/' . $acc['id']) : url('settings/calendar')) ?>">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="field"><label for="<?= $uid ?>-label">Naam</label><input type="text" id="<?= $uid ?>-label" name="label" value="<?= $val('label') ?>" placeholder="Bijv. iCloud agenda" required></div>
    <div class="field"><label for="<?= $uid ?>-url">CalDAV-server</label><input type="url" id="<?= $uid ?>-url" name="server_url" value="<?= $val('server_url', 'https://caldav.icloud.com') ?>" required></div>
  </div>
  <div class="form-row">
    <div class="field"><label for="<?= $uid ?>-user">Apple ID (e-mailadres)</label><input type="text" id="<?= $uid ?>-user" name="username" value="<?= $val('username') ?>" autocomplete="off" required></div>
    <div class="field"><label for="<?= $uid ?>-pass">App-specifiek wachtwoord</label><input type="password" id="<?= $uid ?>-pass" name="password" autocomplete="new-password" <?= $acc ? 'placeholder="Leeg laten om te behouden"' : 'required' ?>></div>
  </div>
  <div class="field"><label for="<?= $uid ?>-filter">Alleen deze agenda's (optioneel)</label><input type="text" id="<?= $uid ?>-filter" name="calendar_filter" value="<?= $val('calendar_filter') ?>" placeholder="Thuis, Werk">
    <div class="hint">Namen gescheiden door komma's. Leeg = alle agenda's.</div></div>
  <div class="switch-row"><label for="<?= $uid ?>-certs">Alle certificaten accepteren</label>
    <span class="switch"><input type="checkbox" id="<?= $uid ?>-certs" name="accept_all_certs" value="1" <?= $acc && !$a['verify_cert'] ? 'checked' : '' ?>><span class="track"></span></span></div>
  <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $acc ? 'Opslaan' : 'Agenda toevoegen' ?></button>
</form>
