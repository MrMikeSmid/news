<?php
$value=fn(string $key,string $default='')=>e((string)($input[$key]??$account[$key]??$default));
$active=$input?isset($input['active']):($account['active']??true);
?>
<div class="page-header"><div><h1><?=e($title)?></h1><p class="sub">SMTP verstuurt je mail. Kies POP3 of IMAP voor ontvangen.</p></div><a class="nav-link" href="<?=e(url('mail/accounts'))?>"><?=icon('arrowLeft')?>Mailaccounts</a></div>
<?php if($error):?><div class="alert alert-error" role="alert"><?=icon('alert')?><?=e($error)?></div><?php endif;?>
<form method="post" action="<?=e(url('mail/accounts'.($account?'/'.$account['id']:'')))?>" data-loading>
<?=csrf_field()?>
<div class="card">
<h2>Account</h2>
<div class="form-grid">
<div class="field"><label for="label">Accountnaam</label><input id="label" name="label" value="<?=$value('label')?>" placeholder="Bijvoorbeeld Werk" required maxlength="200"></div>
<div class="field"><label for="email">E-mailadres</label><input id="email" name="email" type="email" value="<?=$value('email')?>" required autocomplete="email"></div>
<div class="field"><label for="from_name">Afzendernaam</label><input id="from_name" name="from_name" value="<?=$value('from_name')?>" autocomplete="name" maxlength="200"></div>
</div>
<label class="checkbox-row"><input type="checkbox" name="active" value="1" <?=$active?'checked':''?>>Account actief</label>
</div>
<div class="card"><h2>Inkomende mail</h2><div class="field"><label for="incoming_protocol">Protocol</label><select id="incoming_protocol" name="incoming_protocol"><?php foreach(['pop3'=>'POP3','imap'=>'IMAP'] as $key=>$name):?><option value="<?=$key?>" <?=($input['incoming_protocol']??$account['incoming_protocol']??'pop3')===$key?'selected':''?>><?=$name?></option><?php endforeach;?></select></div><p class="hint">POP3 laat berichten op de server staan. Gelezen-status en prullenbak worden lokaal in deze app bijgehouden.</p></div>
<?php foreach(['smtp'=>'Versturen via SMTP','imap'=>'Ontvangen'] as $protocol=>$label):?>
<div class="card">
<h2><?=e($label)?></h2>
<div class="form-grid">
<div class="field"><label for="<?=$protocol?>_host">Server</label><input id="<?=$protocol?>_host" name="<?=$protocol?>_host" value="<?=$value($protocol.'_host')?>" placeholder="<?=$protocol?>.voorbeeld.nl" required autocapitalize="none" spellcheck="false"></div>
<div class="field"><label for="<?=$protocol?>_port">Poort</label><input id="<?=$protocol?>_port" name="<?=$protocol?>_port" type="number" value="<?=$value($protocol.'_port',$protocol==='smtp'?'465':(($input['incoming_protocol']??$account['incoming_protocol']??'pop3')==='pop3'?'995':'993'))?>" min="1" max="65535" required></div>
<div class="field"><label for="<?=$protocol?>_security">Beveiliging</label><select id="<?=$protocol?>_security" name="<?=$protocol?>_security"><?php foreach(['tls'=>'STARTTLS','ssl'=>'SSL/TLS'] as $key=>$label):?><option value="<?=$key?>" <?=($input[$protocol.'_security']??$account[$protocol.'_security']??'ssl')===$key?'selected':''?>><?=$label?></option><?php endforeach;?></select></div>
<div class="field"><label for="<?=$protocol?>_user">Gebruikersnaam</label><input id="<?=$protocol?>_user" name="<?=$protocol?>_user" value="<?=$value($protocol.'_user')?>" autocomplete="off" required autocapitalize="none" spellcheck="false"></div>
<div class="field"><label for="<?=$protocol?>_password"><?=$account?'Nieuw wachtwoord (optioneel)':'Wachtwoord of appwachtwoord'?></label><input id="<?=$protocol?>_password" name="<?=$protocol?>_password" type="password" autocomplete="new-password" <?=$account?'':'required'?>><?php if($account):?><p class="hint">Laat leeg om het opgeslagen wachtwoord te behouden.</p><?php endif;?></div>
</div>
<label class="checkbox-row"><input type="checkbox" name="<?=$protocol?>_accept_all" value="1" <?=($input?isset($input[$protocol.'_accept_all']):!($account[$protocol.'_verify_cert']??false))?'checked':''?>>Alle SSL-certificaten accepteren</label>
<p class="hint">De verbinding blijft versleuteld, maar de identiteit van de mailserver wordt niet gecontroleerd.</p>
</div>
<?php endforeach;?>
<div class="card"><h2>Mailboxmappen</h2><p class="sub">Gebruik de exacte mapnamen van je mailprovider.</p><div class="form-grid"><div class="field"><label for="sent_folder">Verzonden</label><input id="sent_folder" name="sent_folder" value="<?=$value('sent_folder','Sent')?>" required></div><div class="field"><label for="trash_folder">Prullenbak</label><input id="trash_folder" name="trash_folder" value="<?=$value('trash_folder','Trash')?>" required></div></div><p class="hint">Gebruik een appwachtwoord als je provider dat vereist. Wachtwoorden worden versleuteld opgeslagen en nooit aan de chatkoppeling getoond.</p></div>
<div class="actions form-actions"><button class="btn btn-primary" type="submit"><?=icon('check')?>Account opslaan</button><a class="btn btn-secondary" href="<?=e(url('mail/accounts'))?>">Annuleren</a></div>
</form>
<?php if($account):?><details class="card danger-details"><summary>Account ontkoppelen</summary><p>Dit verwijdert de opgeslagen verbinding en lokale concepten. Je mail bij de provider blijft bewaard.</p><form method="post" action="<?=e(url('mail/accounts/'.$account['id'].'/delete'))?>" data-loading><?=csrf_field()?><label class="checkbox-row"><input type="checkbox" name="confirmed" value="1" required>Ik wil dit account ontkoppelen</label><button class="btn btn-danger" type="submit"><?=icon('trash')?>Ontkoppelen</button></form></details><?php endif;?>
