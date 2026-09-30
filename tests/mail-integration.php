<?php
// Alleen uitvoeren tegen de lokale mail-fixture, nooit tegen echte accounts.
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/mcp.php';
function expect(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message); }
function reject(callable $fn): void { try {$fn();}catch(InvalidArgumentException|RuntimeException){return;}throw new RuntimeException('Expected action rejection'); }
$alpha=account_get(1);$beta=account_get(2);
if($alpha['smtp_host']!=='mail-fixture'||$beta['imap_host']!=='mail-fixture')throw new RuntimeException('Refusing integration test against real servers.');
connection_test(1);connection_test(2);
$a=search_mail(1,'INBOX','Factuur',false,25);$b=search_mail(2,'INBOX','Factuur',false,25);
expect(count($a['messages'])>=1&&count($b['messages'])>=1,'Missing fixture messages');
$m=$a['messages'][0];$read=read_mail(1,'INBOX',$m['uid'],$m['uidvalidity']);
expect(str_contains($read['body'],'Hallo alpha'),'Wrong mailbox read');
expect(!str_contains($read['body'],'Hallo beta'),'Cross-account leak');
reject(fn()=>read_mail(1,'INBOX',$m['uid'],$m['uidvalidity']+1));
expect(count($read['attachments'])===1,'Missing attachment');
$attachment=mail_attachment(1,'INBOX',$m['uid'],$m['uidvalidity'],$read['attachments'][0]['part']);
expect(base64_decode($attachment['content_base64'])==='fixture attachment','Attachment decode failed');
mark_mail(1,'INBOX',$m['uid'],$m['uidvalidity'],true,true);
$r=read_mail(1,'INBOX',$m['uid'],$m['uidvalidity']);expect($r['seen']&&$r['flagged'],'Flags not saved');
$reference=['account_id'=>1,'folder'=>'INBOX','uid'=>$m['uid'],'uidvalidity'=>$m['uidvalidity']];
$reply=mcp_execute('mail_reply_draft',$reference+['body'=>'Bedankt voor de factuur.']);
expect($reply['recipients']===['supplier@example.test']&&$reply['reply_id']==='<fixture-alpha@example.test>','Reply references wrong');
$forward=mcp_execute('mail_forward_draft',$reference+['to'=>['recipient@example.test'],'include_attachments'=>true]);
expect(count($forward['attachments'])===1,'Forward lost attachment');
$draft=create_draft(1,['recipient@example.test'],'Lokale SMTP-test','Alleen een fixturetest.',['cc@example.test'],['private-bcc@example.test'],[$attachment]);
$edited=mcp_execute('mail_update_draft',['draft_id'=>(int)$draft['id'],'body'=>'Gewijzigde lokale fixturetest.']);
reject(fn()=>send_draft((int)$draft['id'],(int)$draft['revision']));
$sent=send_draft((int)$draft['id'],(int)$edited['revision']);expect($sent['sent']&&$sent['sent_copy'],'SMTP or Sent append failed');
reject(fn()=>send_draft((int)$draft['id'],(int)$edited['revision']));
$sentMessages=search_mail(1,'Sent','Lokale SMTP-test');expect($sentMessages['total']===1,'Sent copy missing or duplicate');
delete_mail(1,'INBOX',$m['uid'],$m['uidvalidity']);
expect(search_mail(1,'INBOX','Factuur')['total']===count($a['messages'])-1,'Deleted message still visible');
$trash=search_mail(1,'Trash','Factuur');expect($trash['total']>=1,'Message not in Trash');
expect(search_mail(2,'INBOX','Factuur')['total']===count($b['messages']),'Other account was modified');
$t=$trash['messages'][0];move_mail(1,'Trash',$t['uid'],$t['uidvalidity'],'INBOX');
expect(search_mail(1,'INBOX','Factuur')['total']===count($a['messages']),'Restore failed');
echo "TLS SMTP/IMAP, isolation, search, MIME, attachments, reply/forward, revision checks, send once, Sent, mark, trash and restore: OK\n";
