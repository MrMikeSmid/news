<?php

declare(strict_types=1);

$testDirectory = sys_get_temp_dir().'/homebase-test-'.bin2hex(random_bytes(6));
putenv('APP_STORAGE_PATH='.$testDirectory);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/mcp.php';
require dirname(__DIR__).'/app/oauth.php';
function check(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message); }
function rejects(callable $fn,string $message): void { try{$fn();}catch(InvalidArgumentException|RuntimeException){return;}throw new RuntimeException($message); }
try {
    mail_db();
    $password='test-only-not-a-real-password';
    $data=['label'=>'Werk','email'=>'alpha@example.test','from_name'=>'Mike','smtp_host'=>'smtp.example.test','smtp_port'=>587,'smtp_security'=>'tls','smtp_user'=>'alpha','smtp_password'=>$password,'imap_host'=>'imap.example.test','imap_port'=>993,'imap_security'=>'ssl','imap_user'=>'alpha','imap_password'=>$password,'sent_folder'=>'Sent','trash_folder'=>'Trash','active'=>true];
    $id=account_save(null,$data);
    $stored=account_get($id);
    check($stored['incoming_protocol']==='imap' && (int)$stored['imap_verify_cert']===1 && (int)$stored['smtp_verify_cert']===1,'Existing defaults changed');
    check($stored['smtp_password']!==$password,'Password stored in plaintext');
    check(unseal($stored['smtp_password'])===$password,'Encryption round trip failed');
    check(!str_contains(json_encode(public_account($stored)),'password'),'Secrets exposed in MCP');
    $data['smtp_password']='';$data['imap_password']='';$data['label']='Gewijzigd';account_save($id,$data);
    check(unseal(account_get($id)['imap_password'])===$password,'Blank edit erased password');
    rejects(fn()=>account_save(null,$data),'New account without passwords allowed');
    $data['smtp_host']='smtp.example.test/evil';rejects(fn()=>account_save($id,$data),'Host syntax allowed');
    rejects(fn()=>create_draft($id,["victim@example.test\r\nBcc: attacker@example.test"],'Hi','Body'),'Header injection allowed');
    $d=create_draft($id,['recipient@example.test'],'Hallo','Test',['cc@example.test'],['bcc@example.test']);
    check($d['status']==='draft','Draft was sent automatically');
    $updated=mcp_execute('mail_update_draft',['draft_id'=>(int)$d['id'],'body'=>'Bijgewerkt']);
    check($updated['id']===$d['id']&&$updated['body']==='Bijgewerkt','Draft update failed');
    query('UPDATE mail_drafts SET status="sent" WHERE id=?',[$d['id']]);
    rejects(fn()=>send_draft((int)$d['id']),'Sent mail can be duplicated');
    rejects(fn()=>mcp_execute('mail_update_draft',['draft_id'=>(int)$d['id'],'body'=>'Overwrite']),'Sent mail was edited');
    foreach(mcp_tools() as $tool) {
        check(isset($tool['annotations']['readOnlyHint']),'Missing tool annotations');
        if($tool['name']==='mail_send_draft')rejects(fn()=>mcp_validate($tool['inputSchema'],['draft_id'=>1,'expected_revision'=>1,'confirmed'=>false]),'Unconfirmed send allowed');
    }
    $token=issue_access_token('Test','mail.read',60);
    check(query('SELECT COUNT(*) FROM mail_tokens WHERE hash=?',[token_hash($token)])->fetchColumn()==1,'Token hash not stored');
    check(!str_contains(file_get_contents(storage_path('mail.sqlite')),$token),'Raw token stored');
    rejects(fn()=>valid_public_url('https://attacker@example.com'),'Userinfo URL allowed');
    rejects(fn()=>valid_public_url('http://example.com'),'Non-HTTPS URL allowed');
    $tampered=base64_decode(seal('test'));$tampered[25]=chr(ord($tampered[25])^1);rejects(fn()=>unseal(base64_encode($tampered)),'Tampered ciphertext accepted');
    echo "Account isolation, encryption, draft safety, input validation and token storage: OK\n";
} finally { foreach(glob($testDirectory.'/*')?:[] as $file)unlink($file);if(is_dir($testDirectory))rmdir($testDirectory); }
