<?php

declare(strict_types=1);

use ZBateson\MailMimeParser\Message;

final class Pop3Mailbox
{
    private $stream;
    public function __construct(private array $account)
    {
        $verify=!empty($account['imap_verify_cert']);
        $context=stream_context_create(['ssl'=>['verify_peer'=>$verify,'verify_peer_name'=>$verify,'allow_self_signed'=>!$verify,'peer_name'=>$account['imap_host']]]);
        $remote=($account['imap_security']==='ssl'?'ssl':'tcp').'://'.$account['imap_host'].':'.$account['imap_port'];
        $this->stream=@stream_socket_client($remote,$errno,$error,15,STREAM_CLIENT_CONNECT,$context);
        if(!$this->stream)throw new RuntimeException('POP3-verbinding mislukt. Controleer server, poort, SSL-instelling en certificaten.');
        stream_set_timeout($this->stream,20);
        try {
            $this->response();
            if($account['imap_security']==='tls'){
                $this->command('STLS');
                if(!@stream_socket_enable_crypto($this->stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('POP3 STARTTLS mislukt.');
            }
            $password=unseal($account['imap_password']);
            if(preg_match('/[\r\n]/',$password))throw new InvalidArgumentException('Het POP3-wachtwoord mag geen regeleinden bevatten.');
            $this->command('USER '.$account['imap_user']);$this->command('PASS '.$password);
        }catch(Throwable $e){$this->close();throw $e;}
    }
    private function line(): string
    {
        $line=fgets($this->stream,65536);
        if($line===false)throw new RuntimeException('De POP3-server reageert niet. Probeer opnieuw.');
        return rtrim($line,"\r\n");
    }
    private function response(): string
    {
        $line=$this->line();
        if(!str_starts_with($line,'+OK'))throw new RuntimeException('De POP3-server weigert deze opdracht. Controleer je accountinstellingen.');
        return $line;
    }
    public function command(string $command,bool $multiline=false): string
    {
        if(fwrite($this->stream,$command."\r\n")!==strlen($command)+2)throw new RuntimeException('POP3-opdracht kon niet worden verstuurd.');
        $reply=$this->response();
        if(!$multiline)return $reply;
        $data='';
        while(($line=$this->line())!=='.'){
            if(str_starts_with($line,'..'))$line=substr($line,1);
            $data.=$line."\r\n";
            if(strlen($data)>20*1024*1024)throw new RuntimeException('Dit POP3-bericht is te groot (maximaal 20 MB).');
        }
        return $data;
    }
    public function ids(): array
    {
        $ids=[];$response=trim($this->command('UIDL',true));
        if($response==='')return [];
        foreach(explode("\r\n",$response) as $line){
            if(!preg_match('/^(\d+) ([!-~]{1,300})$/D',$line,$m))throw new RuntimeException('De POP3-server geeft geen geldige, vaste bericht-ID’s.');
            $ids[(int)$m[1]]=$m[2];
        }
        return $ids;
    }
    public function raw(int $number,bool $headersOnly=false): string
    {
        if($headersOnly)try{return $this->command('TOP '.$number.' 0',true);}catch(RuntimeException){}
        return $this->command('RETR '.$number,true);
    }
    public function close(): void
    {
        if(is_resource($this->stream)){@fwrite($this->stream,"QUIT\r\n");fclose($this->stream);}
    }
    public function __destruct(){$this->close();}
}
function pop_validity(array $a): int
{
    return max(1,(int)sprintf('%u',crc32($a['imap_host'].':'.$a['imap_port'].':'.$a['imap_user'])));
}
function with_pop(int $accountId,string $folder,callable $callback): mixed
{
    if(!in_array($folder,['INBOX','Trash'],true))throw new InvalidArgumentException('POP3 heeft alleen Inbox en de lokale prullenbak.');
    $a=account_get($accountId,true);$pop=new Pop3Mailbox($a);
    try{return $callback($pop,$a);}finally{$pop->close();}
}
function pop_index(Pop3Mailbox $pop,array $a): array
{
    $index=[];$validity=pop_validity($a);
    foreach($pop->ids() as $number=>$uidl){
        query('INSERT INTO mail_pop_ids(account_id,uidvalidity,uidl) VALUES(?,?,?) ON CONFLICT(account_id,uidvalidity,uidl) DO NOTHING',[$a['id'],$validity,$uidl]);
        $uid=(int)query('SELECT id FROM mail_pop_ids WHERE account_id=? AND uidvalidity=? AND uidl=?',[$a['id'],$validity,$uidl])->fetchColumn();
        $index[$uid]=$number;
    }
    return $index;
}
function pop_number(Pop3Mailbox $pop,array $a,string $folder,int $uid,int $validity): int
{
    if(pop_validity($a)!==$validity)throw new InvalidArgumentException('De accountinstellingen zijn veranderd. Zoek het bericht opnieuw op.');
    $index=pop_index($pop,$a);
    if(!isset($index[$uid])||(bool)pop_state((int)$a['id'],$validity,$uid)['trashed']!==($folder==='Trash'))throw new InvalidArgumentException('Dit bericht bestaat niet meer in deze map.');
    return $index[$uid];
}
function pop_addresses(Message $message,string $header): array
{
    $h=$message->getHeader($header);
    if(!$h || !method_exists($h,'getAddresses'))return [];
    return array_values(array_filter(array_map(fn($a)=>$a->getEmail(),$h->getAddresses()),fn($e)=>filter_var($e,FILTER_VALIDATE_EMAIL)));
}
function pop_parsed(string $raw,array $a,string $folder,int $uid): array
{
    $m=Message::from($raw,false);$text=$m->getTextContent();
    if($text===null)$text=html_entity_decode(strip_tags(preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is','',$m->getHtmlContent()??'')??''),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $attachments=[];
    foreach($m->getAllAttachmentParts() as $i=>$part)$attachments[]=['part'=>(string)$i,'filename'=>$part->getFilename()?:'bijlage','size'=>strlen($part->getContent())];
    $validity=pop_validity($a);$state=pop_state((int)$a['id'],$validity,$uid);
    return ['account_id'=>(int)$a['id'],'uid'=>$uid,'uidvalidity'=>$validity,'folder'=>$folder,'subject'=>$m->getHeaderValue('subject')??'','from'=>$m->getHeaderValue('from')??'','to'=>$m->getHeaderValue('to')??'','date'=>$m->getHeaderValue('date')??'','reply_to'=>pop_addresses($m,'reply-to')?:pop_addresses($m,'from'),'to_addresses'=>pop_addresses($m,'to'),'cc_addresses'=>pop_addresses($m,'cc'),'message_id'=>$m->getHeaderValue('message-id'),'references'=>$m->getHeaderValue('references'),'body'=>mb_substr($text,0,100000),'truncated'=>mb_strlen($text)>100000,'attachments'=>$attachments,'seen'=>(bool)$state['seen'],'flagged'=>(bool)$state['flagged'],'size'=>strlen($raw)];
}
function pop_search(int $accountId,string $folder,string $text,bool $unread,int $limit,int $offset): array
{
    return with_pop($accountId,$folder,function($pop,$a)use($folder,$text,$unread,$limit,$offset){
        $index=pop_index($pop,$a);arsort($index,SORT_NUMERIC);$validity=pop_validity($a);
        $index=array_filter($index,function($number,$uid)use($a,$validity,$folder,$unread){$s=pop_state((int)$a['id'],$validity,$uid);return (bool)$s['trashed']===($folder==='Trash')&&(!$unread||!$s['seen']);},ARRAY_FILTER_USE_BOTH);
        $total=count($index);$limited=false;$messages=[];
        if($text==='')$index=array_slice($index,$offset,$limit,true);
        else{ $limited=count($index)>500;$index=array_slice($index,0,500,true); }
        foreach($index as $uid=>$number){
            $message=pop_parsed($pop->raw($number,$text===''),$a,$folder,(int)$uid);
            if($text!==''&&!str_contains(mb_strtolower($message['subject'].' '.$message['from'].' '.$message['to'].' '.$message['body']),mb_strtolower($text)))continue;
            unset($message['body'],$message['attachments']);$messages[]=$message;
        }
        if($text!==''){$total=count($messages);$messages=array_slice($messages,$offset,$limit);}
        return ['total'=>$total,'offset'=>$offset,'messages'=>$messages,'search_limited'=>$limited];
    });
}
function pop_read(int $accountId,string $folder,int $uid,int $validity): array
{
    return with_pop($accountId,$folder,fn($pop,$a)=>pop_parsed($pop->raw(pop_number($pop,$a,$folder,$uid,$validity)),$a,$folder,$uid));
}
function pop_attachment(int $accountId,string $folder,int $uid,int $validity,string $section): array
{
    return with_pop($accountId,$folder,function($pop,$a)use($folder,$uid,$validity,$section){
        $raw=$pop->raw(pop_number($pop,$a,$folder,$uid,$validity));$m=Message::from($raw,false);
        foreach($m->getAllAttachmentParts() as $i=>$part)if((string)$i===$section){$content=$part->getContent();if(strlen($content)>5*1024*1024)throw new RuntimeException('Bijlage is te groot (maximaal 5 MB).');return ['filename'=>$part->getFilename()?:'bijlage','content_base64'=>base64_encode($content),'size'=>strlen($content)];}
        throw new InvalidArgumentException('Bijlage niet gevonden.');
    });
}
function pop_move(int $accountId,string $folder,int $uid,int $validity,string $target): array
{
    if(!in_array($target,['INBOX','Trash'],true)||$target===$folder)throw new InvalidArgumentException('Kies Inbox of de lokale prullenbak.');
    return with_pop($accountId,$folder,function($pop,$a)use($folder,$uid,$validity,$target){pop_number($pop,$a,$folder,$uid,$validity);pop_update((int)$a['id'],$validity,$uid,['trashed'=>$target==='Trash'?1:0]);return ['moved'=>true,'target'=>$target,'local'=>true];});
}
function pop_mark(int $accountId,string $folder,int $uid,int $validity,bool $read,bool $flagged): array
{
    return with_pop($accountId,$folder,function($pop,$a)use($folder,$uid,$validity,$read,$flagged){pop_number($pop,$a,$folder,$uid,$validity);pop_update((int)$a['id'],$validity,$uid,['seen'=>$read?1:0,'flagged'=>$flagged?1:0]);return ['uid'=>$uid,'seen'=>$read,'flagged'=>$flagged,'local'=>true];});
}
