<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function smtp_client(array $account): PHPMailer
{
    if (!class_exists(PHPMailer::class)) throw new RuntimeException('De mailbibliotheek ontbreekt. Voer composer install uit.');
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $account['smtp_host'];
    $mail->Port = (int) $account['smtp_port'];
    $mail->SMTPAuth = true;
    $mail->Username = $account['smtp_user'];
    $mail->Password = unseal($account['smtp_password']);
    $mail->SMTPSecure = $account['smtp_security'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout = 15;
    $mail->getSMTPInstance()->Timelimit = 30;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($account['email'], $account['from_name']);
    $mail->isHTML(false);
    return $mail;
}

function imap_path(array $account, string $folder): string
{
    $flags = $account['imap_security'] === 'ssl' ? '/imap/ssl' : '/imap/tls';
    return '{'.$account['imap_host'].':'.$account['imap_port'].$flags.'}'.imap_utf7_encode(mailbox_name($folder));
}
function imap_connect_account(array $account, string $folder = 'INBOX')
{
    if (!function_exists('imap_open')) throw new RuntimeException('Lezen en beheren vereist de PHP IMAP-extensie op je hosting.');
    imap_timeout(IMAP_OPENTIMEOUT, 15); imap_timeout(IMAP_READTIMEOUT, 20); imap_timeout(IMAP_WRITETIMEOUT, 20);
    $connection = @imap_open(imap_path($account, $folder), $account['imap_user'], unseal($account['imap_password']), 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
    if (!$connection) { imap_errors(); imap_alerts(); throw new RuntimeException('IMAP-verbinding mislukt. Controleer server, poort, beveiliging en appwachtwoord.'); }
    return $connection;
}
function with_mailbox(int $accountId, string $folder, callable $callback): mixed
{
    $account = account_get($accountId, true);
    $connection = imap_connect_account($account, $folder);
    try { return $callback($connection, $account); }
    finally { imap_errors(); imap_alerts(); imap_close($connection); }
}
function mailbox_validity($connection, array $account, string $folder): int
{
    $status = imap_status($connection, imap_path($account, $folder), SA_UIDVALIDITY);
    if (!$status) throw new RuntimeException('De mapstatus kon niet worden opgehaald.');
    return (int)$status->uidvalidity;
}
function require_message($connection, array $account, string $folder, int $uid, int $validity): void
{
    if ($uid < 1 || mailbox_validity($connection, $account, $folder) !== $validity) throw new InvalidArgumentException('De mailbox is veranderd. Zoek het bericht opnieuw op.');
    if (!imap_msgno($connection, $uid)) throw new InvalidArgumentException('Het bericht bestaat niet meer in deze map.');
}
function decode_header(string $value): string
{
    $text='';
    foreach (imap_mime_header_decode($value) as $part) {
        $charset = strtoupper($part->charset);
        $text .= in_array($charset, ['DEFAULT','UTF-8','US-ASCII'], true) ? $part->text : (@mb_convert_encoding($part->text, 'UTF-8', $part->charset) ?: $part->text);
    }
    return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
}
function list_folders(int $accountId): array
{
    return with_mailbox($accountId, 'INBOX', function($c,$a) {
        $prefix=substr(imap_path($a,'INBOX'),0,-strlen('INBOX'));
        $folders=imap_getmailboxes($c,$prefix,'*') ?: [];
        return array_map(fn($f)=>['name'=>imap_utf7_decode(substr($f->name,strlen($prefix))), 'attributes'=>$f->attributes],$folders);
    });
}
function search_mail(int $accountId, string $folder, string $text = '', bool $unread = false, int $limit = 25, int $offset = 0): array
{
    if ($limit < 1 || $limit > 100 || $offset < 0 || strlen($text)>500 || preg_match('/[\x00-\x1f]/',$text)) throw new InvalidArgumentException('Ongeldige zoekopdracht of paginagrootte.');
    return with_mailbox($accountId,$folder,function($c,$a) use($folder,$text,$unread,$limit,$offset) {
        $criteria=$unread ? 'UNSEEN UNDELETED' : 'UNDELETED';
        if ($text!=='') $criteria.=' TEXT "'.addcslashes($text,'"\\').'"';
        $uids=imap_search($c,$criteria,SE_UID,'UTF-8');
        if ($uids===false) { $errors=imap_errors(); if ($errors) throw new RuntimeException('Zoeken mislukt. Controleer de map en zoekterm.'); $uids=[]; }
        rsort($uids,SORT_NUMERIC);
        $validity=mailbox_validity($c,$a,$folder);
        $page=array_slice($uids,$offset,$limit);
        $overview=$page ? imap_fetch_overview($c,implode(',',$page),FT_UID) ?: [] : [];
        $messages=array_map(fn($o)=>['uid'=>(int)$o->uid,'uidvalidity'=>$validity,'folder'=>$folder,'subject'=>decode_header($o->subject??''),'from'=>decode_header($o->from??''),'to'=>decode_header($o->to??''),'date'=>$o->date??'','seen'=>(bool)($o->seen??false),'flagged'=>(bool)($o->flagged??false),'size'=>(int)($o->size??0)],$overview);
        usort($messages,fn($x,$y)=>$y['uid']<=>$x['uid']);
        return ['total'=>count($uids),'offset'=>$offset,'messages'=>$messages];
    });
}
function mime_parts(object $structure, string $section = ''): array
{
    if (!empty($structure->parts)) {
        $result=[];
        foreach ($structure->parts as $i=>$part) $result=[...$result,...mime_parts($part,$section==='' ? (string)($i+1) : $section.'.'.($i+1))];
        return $result;
    }
    $parameters=[];
    foreach ([...($structure->parameters??[]),...($structure->dparameters??[])] as $p) $parameters[strtolower($p->attribute)]=$p->value;
    $filename=$parameters['filename']??$parameters['name']??null;
    return [['section'=>$section ?: '1','type'=>(int)($structure->type??0),'subtype'=>strtoupper($structure->subtype??'PLAIN'),'encoding'=>(int)($structure->encoding??0),'charset'=>$parameters['charset']??'UTF-8','filename'=>$filename ? decode_header($filename) : null,'bytes'=>(int)($structure->bytes??0),'attachment'=> $filename!==null || strtoupper($structure->disposition??'')==='ATTACHMENT']];
}
function decoded_part($c, int $uid, array $part): string
{
    $body=imap_fetchbody($c,$uid,$part['section'],FT_UID|FT_PEEK);
    if ($body===false) throw new RuntimeException('Dit deel van het bericht kon niet worden gelezen.');
    return match($part['encoding']) { 3 => base64_decode($body,true) ?: '', 4 => quoted_printable_decode($body), default=>$body };
}
function read_mail(int $accountId,string $folder,int $uid,int $validity): array
{
    return with_mailbox($accountId,$folder,function($c,$a) use($folder,$uid,$validity) {
        require_message($c,$a,$folder,$uid,$validity);
        $overview=imap_fetch_overview($c,(string)$uid,FT_UID)[0]??null;
        $structure=imap_fetchstructure($c,$uid,FT_UID);
        if (!$overview || !$structure) throw new RuntimeException('Bericht kon niet worden gelezen.');
        if (($overview->size??0)>20*1024*1024) throw new RuntimeException('Dit bericht is te groot om in chat te lezen (maximaal 20 MB).');
        $plain=[]; $html=[]; $attachments=[];
        foreach(mime_parts($structure) as $part) {
            if($part['attachment']) { $attachments[]=['part'=>$part['section'],'filename'=>$part['filename']??'bijlage','size'=>$part['bytes']]; continue; }
            if($part['type']!==0) continue;
            $text=decoded_part($c,$uid,$part);
            try { $text=mb_convert_encoding($text,'UTF-8',$part['charset']); } catch(ValueError) { $text=mb_convert_encoding($text,'UTF-8','UTF-8'); }
            if($part['subtype']==='HTML') $html[]=$text; else $plain[]=$text;
        }
        $body=$plain ? implode("\n",$plain) : html_entity_decode(strip_tags(preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is','',implode("\n",$html))??''),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $header=imap_rfc822_parse_headers(imap_fetchheader($c,$uid,FT_UID));
        return ['account_id'=>(int)$a['id'],'uid'=>$uid,'uidvalidity'=>$validity,'folder'=>$folder,'subject'=>decode_header($overview->subject??''),'from'=>decode_header($overview->from??''),'to'=>decode_header($overview->to??''),'reply_to'=>address_list($header->reply_to??$header->from??[]),'to_addresses'=>address_list($header->to??[]),'cc_addresses'=>address_list($header->cc??[]),'date'=>$overview->date??'','message_id'=>$header->message_id??null,'references'=>isset($header->references)?preg_replace('/[\r\n]+[ \t]*/',' ',$header->references):null,'body'=>mb_substr($body,0,100000),'truncated'=>mb_strlen($body)>100000,'attachments'=>$attachments,'seen'=>(bool)($overview->seen??false),'flagged'=>(bool)($overview->flagged??false)];
    });
}
function address_list(array $addresses): array
{
    $result=[];
    foreach($addresses as $address) { $email=($address->mailbox??'').'@'.($address->host??''); if(filter_var($email,FILTER_VALIDATE_EMAIL)) $result[]=$email; }
    return $result;
}
function mail_attachment(int $accountId,string $folder,int $uid,int $validity,string $section): array
{
    return with_mailbox($accountId,$folder,function($c,$a) use($folder,$uid,$validity,$section) {
        require_message($c,$a,$folder,$uid,$validity);
        $structure=imap_fetchstructure($c,$uid,FT_UID);
        if (!$structure) throw new RuntimeException('Bericht kon niet worden gelezen.');
        foreach(mime_parts($structure) as $part) if($part['attachment'] && $part['section']===$section) {
            if($part['bytes']>5*1024*1024) throw new RuntimeException('Bijlage is te groot (maximaal 5 MB).');
            $data=decoded_part($c,$uid,$part);
            if(strlen($data)>5*1024*1024) throw new RuntimeException('Bijlage is te groot (maximaal 5 MB).');
            return ['filename'=>$part['filename']??'bijlage','content_base64'=>base64_encode($data),'size'=>strlen($data)];
        }
        throw new InvalidArgumentException('Bijlage niet gevonden.');
    });
}
function move_mail(int $accountId,string $folder,int $uid,int $validity,string $target): array
{
    mailbox_name($target);
    if ($folder===$target) throw new InvalidArgumentException('Kies een andere doelmap.');
    return with_mailbox($accountId,$folder,function($c,$a) use($folder,$uid,$validity,$target) {
        require_message($c,$a,$folder,$uid,$validity);
        if (!@imap_mail_move($c,(string)$uid,imap_utf7_encode($target),CP_UID)) throw new RuntimeException('Verplaatsen mislukt. Controleer of de doelmap bestaat.');
        // Geen algemene EXPUNGE: dat zou ook door andere mailclients verwijderde berichten wissen.
        audit_mail('message_moved',(int)$a['id'],(string)$uid);
        return ['moved'=>true,'target'=>$target,'note'=>'Zoek het bericht in de doelmap opnieuw op; daar heeft het een andere UID.'];
    });
}
function delete_mail(int $accountId,string $folder,int $uid,int $validity): array
{
    $a=account_get($accountId,true);
    if ($folder===$a['trash_folder']) throw new InvalidArgumentException('Dit bericht staat al in de prullenbak.');
    return move_mail($accountId,$folder,$uid,$validity,$a['trash_folder']);
}
function mark_mail(int $accountId,string $folder,int $uid,int $validity,bool $read,bool $flagged): array
{
    return with_mailbox($accountId,$folder,function($c,$a) use($folder,$uid,$validity,$read,$flagged) {
        require_message($c,$a,$folder,$uid,$validity);
        foreach(['\\Seen'=>$read,'\\Flagged'=>$flagged] as $flag=>$on) {
            $ok=$on ? imap_setflag_full($c,(string)$uid,$flag,ST_UID) : imap_clearflag_full($c,(string)$uid,$flag,ST_UID);
            if(!$ok) throw new RuntimeException('De status kon niet worden gewijzigd.');
        }
        return ['uid'=>$uid,'seen'=>$read,'flagged'=>$flagged];
    });
}
function email_addresses(array $input): array
{
    if(count($input)>50) throw new InvalidArgumentException('Maximaal 50 adressen per veld.');
    $addresses=[];
    foreach($input as $email) {
        if(!is_string($email)||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email)) throw new InvalidArgumentException('Een ontvangeradres is ongeldig.');
        $addresses[]=trim($email);
    }
    return array_values(array_unique($addresses));
}
function create_draft(int $accountId,array $to,string $subject,string $body,array $cc=[],array $bcc=[],array $attachments=[],?string $replyId=null,?string $refs=null): array
{
    account_get($accountId,true);
    $to=email_addresses($to);$cc=email_addresses($cc);$bcc=email_addresses($bcc);
    if(!$to || trim($subject)==='' || strlen($subject)>998 || preg_match('/[\r\n]/',$subject) || strlen($body)>1024*1024) throw new InvalidArgumentException('Vul ontvanger en onderwerp in; de tekst mag maximaal 1 MB zijn.');
    if(($replyId && preg_match('/[\r\n]/',$replyId)) || ($refs && preg_match('/[\r\n]/',$refs))) throw new InvalidArgumentException('Ongeldige berichtreferentie.');
    $total=0;
    foreach($attachments as &$attachment) {
        if(!is_array($attachment)||!isset($attachment['filename'],$attachment['content_base64'])||!is_string($attachment['filename'])||preg_match('/[\x00-\x1f]/',$attachment['filename'])) throw new InvalidArgumentException('Ongeldige bijlage.');
        $data=base64_decode($attachment['content_base64'],true);
        if($data===false || ($total+=strlen($data))>5*1024*1024) throw new InvalidArgumentException('Bijlagen mogen samen maximaal 5 MB zijn.');
        $attachment=['filename'=>basename($attachment['filename']),'content_base64'=>base64_encode($data)];
    } unset($attachment);
    query('INSERT INTO mail_drafts(account_id,recipients,cc,bcc,subject,body,attachments,reply_id,refs) VALUES(?,?,?,?,?,?,?,?,?)',[$accountId,json_encode($to),json_encode($cc),json_encode($bcc),$subject,$body,json_encode($attachments),$replyId,$refs]);
    return draft_get((int)mail_db()->lastInsertId());
}
function draft_get(int $id): array
{
    $d=query('SELECT * FROM mail_drafts WHERE id=?',[$id])->fetch();
    if(!$d) throw new InvalidArgumentException('Concept bestaat niet.');
    foreach(['recipients','cc','bcc','attachments'] as $key) $d[$key]=json_decode($d[$key],true,512,JSON_THROW_ON_ERROR);
    return $d;
}
function draft_preview(array $draft): array
{
    $draft['attachments']=array_map(fn($a)=>['filename'=>$a['filename']],$draft['attachments']);
    return $draft;
}
function send_draft(int $id, ?int $expectedRevision = null): array
{
    $draft=draft_get($id);
    if($expectedRevision!==null && (int)$draft['revision']!==$expectedRevision) throw new InvalidArgumentException('Dit concept is gewijzigd. Bekijk de nieuwe versie voor je verstuurt.');
    $account=account_get((int)$draft['account_id'],true);
    if($draft['status']!=='draft') throw new InvalidArgumentException('Dit concept is al verstuurd of wordt verwerkt. Maak bij twijfel geen automatische tweede verzending.');
    $mail=smtp_client($account);
    foreach($draft['recipients'] as $address) $mail->addAddress($address);
    foreach($draft['cc'] as $address) $mail->addCC($address);
    foreach($draft['bcc'] as $address) $mail->addBCC($address);
    $mail->Subject=$draft['subject'];$mail->Body=$draft['body'];
    if($draft['reply_id']) $mail->addCustomHeader('In-Reply-To',$draft['reply_id']);
    if($draft['refs']) $mail->addCustomHeader('References',$draft['refs']);
    foreach($draft['attachments'] as $a) $mail->addStringAttachment(base64_decode($a['content_base64']),$a['filename']);
    try { $mail->preSend(); } catch(Throwable) { throw new RuntimeException('De mail kon niet worden opgebouwd. Controleer het concept.'); }
    if(query('UPDATE mail_drafts SET status="sending" WHERE id=? AND status="draft" AND revision=?',[$id,$draft['revision']])->rowCount()!==1) throw new RuntimeException('Dit concept wordt al verwerkt.');
    try { $mail->postSend(); }
    catch(Throwable) { query('UPDATE mail_drafts SET status="uncertain" WHERE id=?',[$id]); audit_mail('send_uncertain',(int)$account['id'],(string)$id); throw new RuntimeException('Verzending is niet bevestigd. Controleer je mailbox voordat je een nieuw concept verstuurt.'); }
    finally { $mail->smtpClose(); }
    query('UPDATE mail_drafts SET status="sent" WHERE id=?',[$id]);
    $saved=false;
    try {
        $c=imap_connect_account($account);
        try { $saved=(bool)@imap_append($c,imap_path($account,$account['sent_folder']),$mail->getSentMIMEMessage(),'\\Seen'); }
        finally { imap_errors();imap_alerts();imap_close($c); }
    } catch(Throwable) {}
    query('UPDATE mail_drafts SET sent_copy=? WHERE id=?',[$saved?1:0,$id]);
    audit_mail('draft_sent',(int)$account['id'],(string)$id);
    return ['sent'=>true,'draft_id'=>$id,'sent_copy'=>$saved,'message'=>$saved ? 'Mail verstuurd en in Verzonden opgeslagen.' : 'Mail verstuurd. De kopie kon niet in de map Verzonden worden opgeslagen; het concept blijft als verzendbewijs bewaard.'];
}
function connection_test(int $accountId): array
{
    $account=account_get($accountId);
    $smtp=smtp_client($account);
    try { if(!$smtp->smtpConnect()) throw new RuntimeException(); }
    catch(Throwable) { throw new RuntimeException('SMTP-verbinding mislukt. Controleer server, poort, beveiliging en appwachtwoord.'); }
    finally { $smtp->smtpClose(); }
    $imap=imap_connect_account($account);
    imap_close($imap);
    return ['smtp'=>true,'imap'=>true];
}
