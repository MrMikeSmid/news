<?php

declare(strict_types=1);

function login_page(bool $setup, string $method): void
{
    if($setup && owner_exists()) redirect_to('login');
    if(!$setup && !owner_exists()) redirect_to('setup');
    if(owner_logged_in())redirect_to('');
    $error=null;
    if($setup) { mail_db(); secret_file('setup-token',32); }
    if($method==='POST') {
        check_csrf();
        try {
            rate_limit(($setup?'setup:':'login:').($_SERVER['REMOTE_ADDR']??''));
            $username=post_string('username');$password=$_POST['password']??'';
            if(!is_string($password))throw new InvalidArgumentException('Ongeldige invoer.');
            if($setup) {
                $expected=getenv('APP_SETUP_TOKEN')?:trim(file_get_contents(storage_path('setup-token')));
                if(!hash_equals($expected,post_string('setup_token')))throw new InvalidArgumentException('De installatiecode klopt niet.');
                if(strlen($username)<3||strlen($username)>100||strlen($password)<12||$password!==($_POST['confirmation']??''))throw new InvalidArgumentException('Gebruik een naam van 3–100 tekens en tweemaal hetzelfde wachtwoord van minstens 12 tekens.');
                query('INSERT INTO mail_admin(id,username,password_hash) VALUES(1,?,?)',[$username,password_hash($password,PASSWORD_DEFAULT)]);
                @unlink(storage_path('setup-token'));
            } else {
                $owner=query('SELECT * FROM mail_admin WHERE id=1')->fetch();
                if(!$owner||!hash_equals($owner['username'],$username)||!password_verify($password,$owner['password_hash']))throw new InvalidArgumentException('Je gebruikersnaam of wachtwoord klopt niet.');
            }
            session_regenerate_id(true);$_SESSION['mail_owner']=true;$_SESSION['mail_login_time']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
            $back=$_SESSION['return_path']??'/';unset($_SESSION['return_path']);
            redirect_to(ltrim($back,'/'));
        }catch(Throwable $e) {$error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'Inloggen is niet gelukt.';http_response_code(400);}
    }
    render('mail/login',['title'=>$setup?'Welkom bij Homebase':'Inloggen','setup'=>$setup,'error'=>$error]);
}
function accounts_page(?int $edit=null,bool $new=false): void
{
    $account=$edit?account_get($edit):null;
    render($new||$edit?'mail/account-form':'mail/accounts',['title'=>$new?'Mailaccount toevoegen':($edit?'Mailaccount bewerken':'Mailaccounts'),'account'=>$account,'accounts'=>account_list(),'missing'=>mail_requirements(),'error'=>null,'input'=>[]]);
}
function account_action(?int $id,string $action): void
{
    check_csrf();
    try {
        if($action==='save') {
            $data=[];foreach(['incoming_protocol','label','email','from_name','smtp_host','smtp_port','smtp_security','smtp_user','imap_host','imap_port','imap_security','imap_user','sent_folder','trash_folder'] as $key)$data[$key]=post_string($key);
            foreach(['smtp_password','imap_password'] as $key){$data[$key]=$_POST[$key]??'';if(!is_string($data[$key]))throw new InvalidArgumentException('Ongeldig wachtwoord.');}
            $data['smtp_verify_cert']=!isset($_POST['smtp_accept_all']);$data['imap_verify_cert']=!isset($_POST['imap_accept_all']);
            $data['active']=isset($_POST['active']);
            account_save($id,$data);flash_message('Mailaccount opgeslagen.');
        } elseif($action==='test') { connection_test((int)$id);flash_message('SMTP en '.strtoupper(account_get((int)$id)['incoming_protocol']).' zijn verbonden. Er is geen testmail verstuurd.'); }
        elseif($action==='delete') {
            account_get((int)$id);
            if(($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Bevestig dat je dit account wilt ontkoppelen.');
            query('DELETE FROM mail_accounts WHERE id=?',[$id]);audit_mail('account_removed',$id);flash_message('Account ontkoppeld. De berichten bij je mailprovider blijven bewaard.');
        }
    }catch(Throwable $e) {
        $error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'De actie is mislukt.';
        if($action==='save') {
            http_response_code(422);
            $input=$_POST;unset($input['smtp_password'],$input['imap_password']);
            render('mail/account-form',['title'=>$id?'Mailaccount bewerken':'Mailaccount toevoegen','account'=>$id?account_get($id):null,'input'=>$input,'error'=>$error]);return;
        }
        flash_message($error,true);
    }
    redirect_to('mail/accounts');
}
function inbox_page(): void
{
    $accounts=array_filter(account_list(),fn($a)=>$a['active']);
    $id=(int)($_GET['account']??(reset($accounts)['id']??0));$folder=(string)($_GET['folder']??'INBOX');$term=(string)($_GET['q']??'');$offset=max(0,(int)($_GET['offset']??0));
    $error=null;$data=['messages'=>[],'total'=>0];$folders=[];
    if($id)try { $data=search_mail($id,$folder,$term,!empty($_GET['unread']),25,$offset);$folders=list_folders($id); }
    catch(Throwable $e) {$error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'De mailbox kon niet worden geladen.';}
    render('mail/inbox',['title'=>'Mail','accounts'=>$accounts,'accountId'=>$id,'folder'=>$folder,'term'=>$term,'mailbox'=>$data,'folders'=>$folders,'error'=>$error,'offset'=>$offset]);
}
function message_page(): void
{
    $id=(int)($_GET['account']??0);$folder=(string)($_GET['folder']??'INBOX');$uid=(int)($_GET['uid']??0);$validity=(int)($_GET['validity']??0);
    $message=read_mail($id,$folder,$uid,$validity);
    if(isset($_GET['part'])) {
        $attachment=mail_attachment($id,$folder,$uid,$validity,(string)$_GET['part']);
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode(basename($attachment['filename'])));
        echo base64_decode($attachment['content_base64']);exit;
    }
    render('mail/message',['title'=>$message['subject']?:'Bericht','message'=>$message,'account'=>account_get($id),'folders'=>list_folders($id)]);
}
function message_action(string $action): void
{
    check_csrf();
    $id=(int)post_string('account_id');$folder=post_string('folder');$uid=(int)post_string('uid');$validity=(int)post_string('uidvalidity');
    try {
        if($action==='delete') {if(($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Bevestig het verplaatsen naar de prullenbak.');delete_mail($id,$folder,$uid,$validity);}
        elseif($action==='move')move_mail($id,$folder,$uid,$validity,post_string('target'));
        elseif($action==='mark')mark_mail($id,$folder,$uid,$validity,!empty($_POST['read']),!empty($_POST['flagged']));
        flash_message('Bericht bijgewerkt.');
    }catch(Throwable $e){flash_message($e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'De actie is mislukt.',true);}
    redirect_to('mail?'.http_build_query(['account'=>$id,'folder'=>$folder]));
}
function compose_page(?int $draftId=null, bool $edit=false): void
{
    $draft=$draftId?draft_get($draftId):null;
    $input=[];
    if($edit && $draft) {
        if($draft['status']!=='draft')throw new InvalidArgumentException('Alleen een onverstuurd concept kan worden aangepast.');
        $input=['draft_id'=>$draft['id'],'account_id'=>$draft['account_id'],'to'=>implode(', ',$draft['recipients']),'cc'=>implode(', ',$draft['cc']),'bcc'=>implode(', ',$draft['bcc']),'subject'=>$draft['subject'],'body'=>$draft['body']];
        $draft=null;
    }
    $reply=null;
    if(isset($_GET['reply']))$reply=read_mail((int)($_GET['account']??0),(string)($_GET['folder']??'INBOX'),(int)$_GET['reply'],(int)($_GET['validity']??0));
    render('mail/compose',['title'=>$edit?'Concept bewerken':($draft?'Concept bekijken':($reply?'Antwoord schrijven':'Mail schrijven')),'draft'=>$draft,'reply'=>$reply,'accounts'=>array_filter(account_list(),fn($a)=>$a['active']),'error'=>null,'input'=>$input]);
}
function compose_action(): void
{
    check_csrf();
    try {
        $to=array_values(array_filter(array_map('trim',explode(',',post_string('to')))));
        $cc=array_values(array_filter(array_map('trim',explode(',',post_string('cc')))));
        $bcc=array_values(array_filter(array_map('trim',explode(',',post_string('bcc')))));
        $attachments=[];
        if(!empty($_FILES['attachment']) && $_FILES['attachment']['error']!==UPLOAD_ERR_NO_FILE) {
            $file=$_FILES['attachment'];
            if($file['error']!==UPLOAD_ERR_OK||$file['size']>5*1024*1024)throw new InvalidArgumentException('Upload een bijlage van maximaal 5 MB.');
            $attachments[]=['filename'=>$file['name'],'content_base64'=>base64_encode(file_get_contents($file['tmp_name']))];
        }
        $replyId=null;$refs=null;
        if(post_string('reply_uid')!=='') {
            $reply=read_mail((int)post_string('account_id'),post_string('reply_folder'),(int)post_string('reply_uid'),(int)post_string('reply_validity'));
            $replyId=$reply['message_id'];$refs=trim(($reply['references']??'').' '.($replyId??''));
        }
        if(post_string('draft_id')!=='') {
            $old=draft_get((int)post_string('draft_id'));
            $args=['draft_id'=>(int)$old['id'],'to'=>$to,'cc'=>$cc,'bcc'=>$bcc,'subject'=>post_string('subject'),'body'=>post_string('body')];
            if($attachments) $args['attachments']=[...$old['attachments'],...$attachments];
            $draft=mcp_execute('mail_update_draft',$args);
        } else $draft=create_draft((int)post_string('account_id'),$to,post_string('subject'),post_string('body'),$cc,$bcc,$attachments,$replyId,$refs);
        flash_message('Concept opgeslagen. Controleer het voordat je het verstuurt.');redirect_to('mail/drafts/'.$draft['id']);
    }catch(Throwable $e) {
        http_response_code(422);
        render('mail/compose',['title'=>'Mail schrijven','draft'=>null,'reply'=>null,'accounts'=>array_filter(account_list(),fn($a)=>$a['active']),'input'=>$_POST,'error'=>$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'Opslaan is mislukt.']);
    }
}
function draft_action(int $id,string $action): void
{
    check_csrf();
    try {
        if(($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Bevestig de actie.');
        if($action==='send')flash_message(send_draft($id,(int)post_string('expected_revision'))['message']);
        else { mcp_execute('mail_delete_draft',['draft_id'=>$id]);flash_message('Concept verwijderd.');redirect_to('mail/drafts'); }
    }catch(Throwable $e){flash_message($e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'De actie is mislukt.',true);}
    redirect_to('mail/drafts/'.$id);
}
function connections_page(string $method): void
{
    $newToken=null;$error=null;
    if($method==='POST') {
        check_csrf();
        try {
            $action=post_string('action');
            if($action==='url')save_mail_setting('public_url',valid_public_url(post_string('public_url')));
            elseif($action==='token') {
                $scope=scopes(implode(' ',array_keys(array_filter(['mail.read'=>isset($_POST['read']),'mail.write'=>isset($_POST['write']),'mail.delete'=>isset($_POST['delete'])]))));
                $newToken=issue_access_token(post_string('name')?:'Eigen client',$scope,90*86400);
            } elseif($action==='revoke') {
                $name=post_string('name');query('DELETE FROM mail_tokens WHERE name=?',[$name]);
                if(str_starts_with($name,'OAuth: '))query('DELETE FROM mail_refresh_tokens WHERE client_id=?',[substr($name,7)]);
            }
            if(!$newToken) {flash_message('Chatkoppeling bijgewerkt.');redirect_to('connections');}
        }catch(Throwable $e){$error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'Opslaan is mislukt.';}
    }
    render('mail/connections',['title'=>'Chatkoppeling','publicUrl'=>getenv('APP_URL')?:mail_setting('public_url'),'tokens'=>query('SELECT name,scope,MAX(expires) AS expires FROM mail_tokens WHERE expires>? GROUP BY name',[time()])->fetchAll(),'newToken'=>$newToken,'error'=>$error]);
}
function security_page(string $method): void
{
    $error=null;
    if($method==='POST') {
        check_csrf();
        try {
            $owner=query('SELECT * FROM mail_admin WHERE id=1')->fetch();
            if(!password_verify($_POST['current_password']??'',$owner['password_hash']))throw new InvalidArgumentException('Je huidige wachtwoord klopt niet.');
            $password=$_POST['password']??'';
            if(!is_string($password)||strlen($password)<12||$password!==($_POST['confirmation']??''))throw new InvalidArgumentException('Gebruik tweemaal hetzelfde wachtwoord van minstens 12 tekens.');
            query('UPDATE mail_admin SET password_hash=? WHERE id=1',[password_hash($password,PASSWORD_DEFAULT)]);
            session_regenerate_id(true);$_SESSION['mail_login_time']=time();flash_message('Wachtwoord gewijzigd.');redirect_to('security');
        }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Wijzigen is mislukt.';}
    }
    render('mail/security',['title'=>'Beveiliging','error'=>$error]);
}
