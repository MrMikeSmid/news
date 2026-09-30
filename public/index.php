<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/oauth.php';
require_once dirname(__DIR__) . '/app/mcp.php';
require_once dirname(__DIR__) . '/app/controllers/MailController.php';

$path=request_path();$method=$_SERVER['REQUEST_METHOD']??'GET';
if($path==='/favicon.ico'){http_response_code(404);exit;}
if($path==='/mcp')mcp_route($method);
if(str_starts_with($path,'/oauth/')||str_starts_with($path,'/.well-known/'))oauth_route($path,$method);
start_mail_session();
if(!in_array($method,['GET','HEAD','POST'],true)){header('Allow: GET, HEAD, POST');abort(405,'Deze aanvraag wordt niet ondersteund.');}
if(in_array($path,['/setup','/login'],true)){login_page($path==='/setup',$method);exit;}
require_owner();
try {
    if($path==='/logout'&&$method==='POST'){check_csrf();$_SESSION=[];session_regenerate_id(true);redirect_to('login');}
    if($path==='/'&&$method!=='POST'){render('home',['title'=>'Start','accounts'=>account_list(),'draftCount'=>(int)query('SELECT COUNT(*) FROM mail_drafts WHERE status="draft"')->fetchColumn()]);exit;}
    if($path==='/mail/accounts'){$method==='POST'?account_action(null,'save'):accounts_page();exit;}
    if($path==='/mail/accounts/new'&&$method!=='POST'){accounts_page(null,true);exit;}
    if(preg_match('#^/mail/accounts/(\d+)(?:/(test|delete))?$#',$path,$match)){$method==='POST'?account_action((int)$match[1],$match[2]??'save'):accounts_page((int)$match[1]);exit;}
    if($path==='/mail'&&$method!=='POST'){inbox_page();exit;}
    if($path==='/mail/message'&&$method!=='POST'){message_page();exit;}
    if(preg_match('#^/mail/message/(delete|move|mark)$#',$path,$match)&&$method==='POST'){message_action($match[1]);exit;}
    if($path==='/mail/compose'){$method==='POST'?compose_action():compose_page();exit;}
    if(preg_match('#^/mail/drafts/(\d+)/edit$#',$path,$match)&&$method!=='POST'){compose_page((int)$match[1],true);exit;}
    if($path==='/mail/drafts'&&$method!=='POST'){render('mail/drafts',['title'=>'Concepten','drafts'=>query('SELECT mail_drafts.*,mail_accounts.label FROM mail_drafts JOIN mail_accounts ON mail_accounts.id=mail_drafts.account_id ORDER BY mail_drafts.id DESC LIMIT 100')->fetchAll()]);exit;}
    if(preg_match('#^/mail/drafts/(\d+)(?:/(send|delete))?$#',$path,$match)) {if($method==='POST'&&isset($match[2]))draft_action((int)$match[1],$match[2]);elseif($method!=='POST'&&!isset($match[2]))compose_page((int)$match[1]);else abort(405,'Deze aanvraag wordt niet ondersteund.');exit;}
    if($path==='/connections'){connections_page($method);exit;}
    if($path==='/security'){security_page($method);exit;}
    abort(404,'Deze pagina bestaat niet. Ga terug naar start.');
}catch(Throwable $e){abort($e instanceof InvalidArgumentException?400:503,$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'De pagina kon niet worden geladen. Probeer opnieuw.');}
