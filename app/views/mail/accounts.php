<div class="page-header"><div><h1>Mailaccounts</h1><p class="sub">Je eigen accounts, elk met een aparte inbox en afzender.</p></div><a class="btn btn-primary" href="<?=e(url('mail/accounts/new'))?>"><?=icon('plus')?>Account toevoegen</a></div>
<?php if($missing):?><div class="alert alert-warn" role="status"><?=icon('alert')?><span>Je hosting mist: <?=e(implode(', ',$missing))?>. Je kunt accounts opslaan; mail gebruiken werkt zodra dit is ingeschakeld.</span></div><?php endif;?>
<?php if(!$accounts):?>
<div class="card empty-state"><div class="empty-state-icon"><?=icon('mail')?></div><h2>Nog geen mailaccounts</h2><p>Voeg je eerste account toe om mail te lezen en te versturen.</p></div>
<?php else: foreach($accounts as $a):?>
<article class="card account-card">
  <div class="card-heading"><div><h2><?=e($a['label'])?></h2><p class="sub"><?=e($a['email'])?></p></div><span class="badge <?=$a['active']?'badge-success':''?>"><?=$a['active']?'Actief':'Gepauzeerd'?></span></div>
  <dl class="detail-list"><div><dt>Versturen</dt><dd><?=e($a['smtp_host'])?> · <?=(int)$a['smtp_port']?> · <?=e(strtoupper($a['smtp_security']))?></dd></div><div><dt>Ontvangen</dt><dd><?=e($a['imap_host'])?> · <?=(int)$a['imap_port']?> · <?=e(strtoupper($a['imap_security']))?></dd></div></dl>
  <div class="actions"><a class="btn btn-secondary btn-sm" href="<?=e(url('mail/accounts/'.$a['id']))?>"><?=icon('edit')?>Bewerken</a><form method="post" action="<?=e(url('mail/accounts/'.$a['id'].'/test'))?>" data-loading><?=csrf_field()?><button class="btn btn-secondary btn-sm" type="submit"><?=icon('refresh')?>Verbinding testen</button></form><a class="btn btn-secondary btn-sm" href="<?=e(url('mail?account='.$a['id']))?>">Mailbox openen</a></div>
</article>
<?php endforeach;endif;?>

<div class="form-actions"><a class="nav-link" href="<?=e(url('security'))?>"><?=icon('lock')?>Beheerwachtwoord wijzigen</a></div>
