<?php
// Alleen de lokale POP3-fixture: geen echte mailboxen gebruiken.
declare(strict_types=1);
putenv('APP_STORAGE_PATH=/tmp/pop3-test-'.bin2hex(random_bytes(4)));
require dirname(__DIR__).'/app/bootstrap.php';
set_exception_handler(function(Throwable $e):void {fwrite(STDERR,$e->getMessage().'\n');exit(1);});
function pop_check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$id=account_save(null,['incoming_protocol'=>'pop3','label'=>'POP test','email'=>'alpha@example.test','from_name'=>'Test','smtp_host'=>'mail-fixture','smtp_port'=>587,'smtp_security'=>'tls','smtp_user'=>'alpha','smtp_password'=>'fixture-alpha-password','imap_host'=>'mail-fixture','imap_port'=>995,'imap_security'=>'ssl','imap_user'=>'alpha','imap_password'=>'fixture-alpha-password','sent_folder'=>'Sent','trash_folder'=>'Trash','active'=>true,'smtp_verify_cert'=>false,'imap_verify_cert'=>false]);
$a=account_get($id);$strict=$a;$strict['imap_verify_cert']=1;
try {$c=new Pop3Mailbox($strict);$c->close();throw new LogicException('Untrusted certificate accepted without opt-in');}catch(RuntimeException){}
connection_test($id);
$inbox=search_mail($id,'INBOX');pop_check($inbox['total']>=1,'No POP3 messages');
$m=$inbox['messages'][0];$read=read_mail($id,'INBOX',$m['uid'],$m['uidvalidity']);pop_check(str_contains($read['body'],'Hallo alpha'),'Wrong POP body');
$again=search_mail($id,'INBOX');pop_check($again['messages'][0]['uid']===$m['uid']&&$again['messages'][0]['uidvalidity']===$m['uidvalidity'],'POP UIDs change across sessions');
mark_mail($id,'INBOX',$m['uid'],$m['uidvalidity'],true,true);
$read=read_mail($id,'INBOX',$m['uid'],$m['uidvalidity']);pop_check($read['seen']&&$read['flagged'],'Local POP flags missing');
$att=mail_attachment($id,'INBOX',$m['uid'],$m['uidvalidity'],$read['attachments'][0]['part']);pop_check(base64_decode($att['content_base64'])==='fixture attachment','POP attachment wrong');
delete_mail($id,'INBOX',$m['uid'],$m['uidvalidity']);pop_check(search_mail($id,'INBOX')['total']===$inbox['total']-1,'Local POP delete failed');
$trash=search_mail($id,'Trash');pop_check($trash['total']===1,'POP trash empty');
move_mail($id,'Trash',$m['uid'],$m['uidvalidity'],'INBOX');pop_check(search_mail($id,'INBOX')['total']===$inbox['total'],'POP restore failed');
$strict['smtp_verify_cert']=1;$smtp=smtp_client($strict);pop_check($smtp->SMTPOptions===[],'SMTP verification not preserved');
$smtp=smtp_client($a);pop_check($smtp->SMTPOptions['ssl']['verify_peer']===false,'SMTP accept-all missing');
echo "POP3 TLS, explicit accept-all, persistent UIDs, read, attachments, local flags/trash/restore and server retention: OK\n";
