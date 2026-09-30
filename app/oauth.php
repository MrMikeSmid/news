<?php

declare(strict_types=1);

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function oauth_error(string $error, string $description, int $status=400): never { json_response(['error'=>$error,'error_description'=>$description],$status); }
function token_hash(string $token): string { return hash('sha256',$token); }
function bearer_token(): string
{
    $header=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
    return preg_match('/^Bearer ([A-Za-z0-9_-]{40,})$/D',$header,$m) ? $m[1] : '';
}
function random_token(): string { return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'='); }
function issue_access_token(string $name,string $scope,int $lifetime=3600): string
{
    $token=random_token();
    query('INSERT INTO mail_tokens(hash,name,scope,expires) VALUES(?,?,?,?)',[token_hash($token),$name,$scope,time()+$lifetime]);
    return $token;
}
function scopes(string $value): string
{
    $parts=array_values(array_unique(array_filter(explode(' ',$value))));
    if(!$parts || array_diff($parts,['mail.read','mail.write','mail.delete'])) throw new InvalidArgumentException('Ongeldige mailrechten.');
    return implode(' ',$parts);
}
function oauth_client(string $id): array
{
    $client=query('SELECT * FROM mail_oauth_clients WHERE id=?',[$id])->fetch();
    if(!$client) throw new InvalidArgumentException('De chatclient is onbekend.');
    return $client;
}
function authorize_request(array $input): array
{
    $client=oauth_client((string)($input['client_id']??''));
    $redirect=(string)($input['redirect_uri']??'');
    if(!in_array($redirect,json_decode($client['redirects'],true),true)) throw new InvalidArgumentException('Het terugkeeradres hoort niet bij deze chatclient.');
    $challenge=(string)($input['code_challenge']??'');
    if(($input['response_type']??'')!=='code' || ($input['code_challenge_method']??'')!=='S256' || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$challenge)) throw new InvalidArgumentException('Deze chatclient moet authorization code met PKCE S256 gebruiken.');
    $state=(string)($input['state']??'');
    if(strlen($state)>2000) throw new InvalidArgumentException('Ongeldige aanvraag.');
    return ['client'=>$client,'redirect'=>$redirect,'challenge'=>$challenge,'scope'=>scopes((string)($input['scope']??'mail.read mail.write mail.delete')),'state'=>$state];
}
function oauth_route(string $path,string $method): never
{
    try {
        $base=public_base_url();
        if($path==='/.well-known/oauth-protected-resource') {
            if($method!=='GET') oauth_error('invalid_request','Gebruik GET.',405);
            json_response(['resource'=>$base.'/mcp','authorization_servers'=>[$base],'scopes_supported'=>['mail.read','mail.write','mail.delete'],'bearer_methods_supported'=>['header']]);
        }
        if($path==='/.well-known/oauth-authorization-server') {
            if($method!=='GET') oauth_error('invalid_request','Gebruik GET.',405);
            json_response(['issuer'=>$base,'authorization_endpoint'=>$base.'/oauth/authorize','token_endpoint'=>$base.'/oauth/token','registration_endpoint'=>$base.'/oauth/register','revocation_endpoint'=>$base.'/oauth/revoke','response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],'code_challenge_methods_supported'=>['S256'],'token_endpoint_auth_methods_supported'=>['none'],'scopes_supported'=>['mail.read','mail.write','mail.delete']]);
        }
        if($path==='/oauth/register') {
            if($method!=='POST') oauth_error('invalid_request','Gebruik POST.',405);
            rate_limit('register:'.($_SERVER['REMOTE_ADDR']??''),50);
            $input=json_decode(file_get_contents('php://input',false,null,0,16385),true,32,JSON_THROW_ON_ERROR);
            $redirects=$input['redirect_uris']??[];
            if(!is_array($redirects)||count($redirects)<1||count($redirects)>5) oauth_error('invalid_client_metadata','Geef één tot vijf terugkeeradressen.');
            foreach($redirects as $redirect) {
                if(!is_string($redirect)||strlen($redirect)>2000) oauth_error('invalid_redirect_uri','Ongeldig terugkeeradres.');
                $url=parse_url($redirect);
                $loopback=in_array($url['host']??'',['127.0.0.1','localhost','[::1]'],true);
                if(!$url||empty($url['host'])||isset($url['fragment'])||isset($url['user'])||isset($url['pass'])||!((($url['scheme']??'')==='https')||(($url['scheme']??'')==='http'&&$loopback))) oauth_error('invalid_redirect_uri','Gebruik HTTPS of een lokaal terugkeeradres.');
            }
            $name=mb_substr(is_string($input['client_name']??null)?$input['client_name']:'Chatclient',0,100);
            $id=random_token(); query('INSERT INTO mail_oauth_clients(id,name,redirects) VALUES(?,?,?)',[$id,$name,json_encode($redirects)]);
            json_response(['client_id'=>$id,'client_name'=>$name,'redirect_uris'=>$redirects,'token_endpoint_auth_method'=>'none','grant_types'=>['authorization_code','refresh_token'],'response_types'=>['code']],201);
        }
        if($path==='/oauth/authorize') {
            start_mail_session(); require_owner();
            $input=$method==='POST'?$_POST:$_GET;
            $request=authorize_request($input);
            if($method==='GET') { render('mail/authorize',['title'=>'Chat toegang geven','request'=>$request,'input'=>$input]);exit; }
            if($method!=='POST') oauth_error('invalid_request','Gebruik GET of POST.',405);
            check_csrf();
            if(($_POST['decision']??'')!=='approve') { $params=['error'=>'access_denied']; }
            else {
                $code=random_token();
                query('INSERT INTO mail_oauth_codes(hash,client_id,redirect,challenge,scope,expires) VALUES(?,?,?,?,?,?)',[token_hash($code),$request['client']['id'],$request['redirect'],$request['challenge'],$request['scope'],time()+300]);
                $params=['code'=>$code];
            }
            if($request['state']!=='') $params['state']=$request['state'];
            header('Location: '.$request['redirect'].(str_contains($request['redirect'],'?')?'&':'?').http_build_query($params),true,303);exit;
        }
        if($path==='/oauth/token') {
            if($method!=='POST') oauth_error('invalid_request','Gebruik POST.',405);
            $grant=$_POST['grant_type']??'';
            $clientId=(string)($_POST['client_id']??''); oauth_client($clientId);
            $db=mail_db();$db->beginTransaction();
            try {
                if($grant==='authorization_code') {
                    $hash=token_hash((string)($_POST['code']??''));
                    $code=query('SELECT * FROM mail_oauth_codes WHERE hash=?',[$hash])->fetch();
                    $verifier=(string)($_POST['code_verifier']??'');
                    $challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');
                    if(!$code||$code['expires']<time()||$code['client_id']!==$clientId||$code['redirect']!==($_POST['redirect_uri']??'')||!preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',$verifier)||!hash_equals($code['challenge'],$challenge)) throw new InvalidArgumentException('Ongeldige of verlopen autorisatiecode.');
                    query('DELETE FROM mail_oauth_codes WHERE hash=?',[$hash]);$scope=$code['scope'];
                } elseif($grant==='refresh_token') {
                    $hash=token_hash((string)($_POST['refresh_token']??''));
                    $refresh=query('SELECT * FROM mail_refresh_tokens WHERE hash=?',[$hash])->fetch();
                    if(!$refresh||$refresh['expires']<time()||$refresh['client_id']!==$clientId) throw new InvalidArgumentException('Ongeldig of verlopen ververstoken.');
                    query('DELETE FROM mail_refresh_tokens WHERE hash=?',[$hash]);$scope=$refresh['scope'];
                } else throw new InvalidArgumentException('Gebruik authorization_code of refresh_token.');
                $access=issue_access_token('OAuth: '.$clientId,$scope);
                $refresh=random_token();query('INSERT INTO mail_refresh_tokens(hash,client_id,scope,expires) VALUES(?,?,?,?)',[token_hash($refresh),$clientId,$scope,time()+30*86400]);
                $db->commit();
                json_response(['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>3600,'refresh_token'=>$refresh,'scope'=>$scope]);
            } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
        }
        if($path==='/oauth/revoke') {
            if($method!=='POST') oauth_error('invalid_request','Gebruik POST.',405);
            $client=oauth_client((string)($_POST['client_id']??''));
            $hash=token_hash((string)($_POST['token']??''));
            query('DELETE FROM mail_tokens WHERE hash=? AND name=?',[$hash,'OAuth: '.$client['id']]);
            query('DELETE FROM mail_refresh_tokens WHERE hash=? AND client_id=?',[$hash,$client['id']]);
            json_response([]);
        }
        oauth_error('invalid_request','Onbekende OAuth-route.',404);
    } catch(Throwable $e) { oauth_error($path==='/oauth/token'?'invalid_grant':'invalid_request',$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'Ongeldige aanvraag.'); }
}
