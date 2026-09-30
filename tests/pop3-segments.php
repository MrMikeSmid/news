<?php
// Run after tests/fixtures/seed-pop3.py inside the disposable mail-fixture.
declare(strict_types=1);
putenv('APP_STORAGE_PATH=/tmp/pop-segments-'.bin2hex(random_bytes(4)));
require dirname(__DIR__).'/app/bootstrap.php';
set_exception_handler(function(Throwable $e):void {fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);});
function segment_check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$id=account_save(null,['incoming_protocol'=>'pop3','label'=>'Segment fixture','email'=>'alpha@example.test','from_name'=>'Test','smtp_host'=>'mail-fixture','smtp_port'=>587,'smtp_security'=>'tls','smtp_user'=>'alpha','smtp_password'=>'fixture-alpha-password','imap_host'=>'mail-fixture','imap_port'=>995,'imap_security'=>'ssl','imap_user'=>'alpha','imap_password'=>'fixture-alpha-password','sent_folder'=>'Sent','trash_folder'=>'Trash','active'=>true,'smtp_verify_cert'=>false,'imap_verify_cert'=>false]);
$start=microtime(true);
$first=search_mail($id,'INBOX');
segment_check($first['total']>=10000,'Large fixture not seeded');
segment_check(count($first['messages'])===10 && $first['has_more'] && $first['next_offset']===10,'First segment is not bounded');
segment_check((int)query('SELECT COUNT(*) FROM mail_pop_ids')->fetchColumn()===10,'Whole mailbox was registered');
segment_check((int)query('SELECT COUNT(*) FROM mail_pop_headers')->fetchColumn()===10,'Unexpected overview cache size');
segment_check((int)query("SELECT COUNT(*) FROM mail_pop_headers WHERE headers LIKE '%segment-body-%'")->fetchColumn()===0,'Overview cached message bodies');
$seconds=microtime(true)-$start;
$repeat=search_mail($id,'INBOX');
segment_check(array_column($repeat['messages'],'uid')===array_column($first['messages'],'uid'),'UIDs changed on reload');
segment_check((int)query('SELECT COUNT(*) FROM mail_pop_headers')->fetchColumn()===10,'Reload duplicated cache');
$second=search_mail($id,'INBOX','',false,100,$first['next_offset']);
segment_check(count($second['messages'])===10 && $second['next_offset']===20,'POP segment limit was bypassed');
segment_check(!array_intersect(array_column($first['messages'],'uid'),array_column($second['messages'],'uid')),'Segments overlap');
segment_check((int)query('SELECT COUNT(*) FROM mail_pop_ids')->fetchColumn()===20,'Second segment registered extra mail');
$m=$first['messages'][0];
$read=read_mail($id,'INBOX',$m['uid'],$m['uidvalidity']);
segment_check(str_contains($read['body'],'segment-body-'),'Full message did not load when opened');
segment_check((int)query('SELECT COUNT(*) FROM mail_pop_ids')->fetchColumn()===20,'Opening a message indexed the whole mailbox');
mark_mail($id,'INBOX',$m['uid'],$m['uidvalidity'],true,true);
segment_check(search_mail($id,'INBOX','',true)['total']===$first['total']-1,'Unread filter total is wrong');
delete_mail($id,'INBOX',$m['uid'],$m['uidvalidity']);
$trash=search_mail($id,'Trash');
segment_check($trash['total']===1 && $trash['messages'][0]['uid']===$m['uid'],'Local trash no longer works');
move_mail($id,'Trash',$m['uid'],$m['uidvalidity'],'INBOX');
$empty=search_mail($id,'INBOX','no-such-fixture-term');
segment_check($empty['messages']===[] && $empty['total']===null && $empty['has_more'] && $empty['next_offset']===10,'Empty search segment cannot continue');
$end=search_mail($id,'INBOX','no-such-fixture-term',false,10,$first['total']-1);
segment_check(!$end['has_more'] && $end['next_offset']===$first['total'],'Last search segment does not end');
printf("10,000-message POP3: bounded segments, cache, stable UIDs, lazy content, filters and search continuation: OK (first segment %.2fs)\n",$seconds);
