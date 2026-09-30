"""Local-only SMTP/IMAP fixture. Never connects to real providers."""
import os, ssl, socketserver, subprocess, email.message, pathlib, uuid, base64
users = {'alpha': 'fixture-alpha-password', 'beta': 'fixture-beta-password'}
pathlib.Path('/etc/dovecot/test-users').write_text(''.join(f'{u}:{{PLAIN}}{p}\n' for u,p in users.items()))
for user in users:
    home=pathlib.Path('/mail')/user/'Maildir'
    for sub in ['new','cur','tmp','.Sent/new','.Sent/cur','.Sent/tmp','.Trash/new','.Trash/cur','.Trash/tmp']:
        (home/sub).mkdir(parents=True,exist_ok=True)
    msg=email.message.EmailMessage();msg['From']='Leverancier <supplier@example.test>';msg['To']=f'{user}@example.test';msg['Subject']=f'Factuur voor {user}';msg['Message-ID']=f'<fixture-{user}@example.test>';msg['Date']='Wed, 30 Sep 2026 12:00:00 +0000'
    msg.set_content(f'Hallo {user},\nDit is een testfactuur.\nGeen echte mail.');msg.add_attachment(b'fixture attachment',maintype='text',subtype='plain',filename='factuur.txt')
    (home/'new'/uuid.uuid4().hex).write_bytes(msg.as_bytes())
subprocess.run(['chown','-R','1000:1000','/mail'],check=True)
subprocess.Popen(['dovecot','-F'])
context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.load_cert_chain('/certs/server.pem','/certs/server.key')
class SMTP(socketserver.StreamRequestHandler):
    def reply(self,text): self.wfile.write((text+'\r\n').encode());self.wfile.flush()
    def handle(self):
        self.reply('220 mail-fixture ESMTP');authenticated=False;tls=False
        while True:
            raw=self.rfile.readline()
            if not raw:break
            command=raw.decode(errors='replace').strip();verb=command.split(' ')[0].upper()
            if verb in ['EHLO','HELO']:
                self.reply('250-mail-fixture');self.reply('250-STARTTLS' if not tls else '250-AUTH LOGIN PLAIN');self.reply('250 SIZE 10000000')
            elif verb=='STARTTLS':
                self.reply('220 Ready');self.connection=context.wrap_socket(self.connection,server_side=True);self.rfile=self.connection.makefile('rb');self.wfile=self.connection.makefile('wb');tls=True
            elif verb=='AUTH' and tls:
                parts=command.split(' ')
                if parts[1].upper()=='LOGIN':
                    self.reply('334 VXNlcm5hbWU6');username=base64.b64decode(self.rfile.readline()).decode();self.reply('334 UGFzc3dvcmQ6');password=base64.b64decode(self.rfile.readline()).decode()
                else:
                    if len(parts)<3:self.reply('334 ');payload=self.rfile.readline().strip()
                    else:payload=parts[2]
                    _,username,password=base64.b64decode(payload).decode().split('\x00')
                authenticated=users.get(username.split('@')[0])==password;self.reply('235 Authenticated' if authenticated else '535 Invalid credentials')
            elif verb in ['MAIL','RCPT'] and authenticated:self.reply('250 OK')
            elif verb=='DATA' and authenticated:
                self.reply('354 End with dot');lines=[]
                while True:
                    line=self.rfile.readline()
                    if line==b'.\r\n':break
                    lines.append(line[1:] if line.startswith(b'..') else line)
                pathlib.Path('/mail/delivery-'+uuid.uuid4().hex+'.eml').write_bytes(b''.join(lines));self.reply('250 Queued')
            elif verb=='RSET':self.reply('250 Reset')
            elif verb=='QUIT':self.reply('221 Bye');break
            else:self.reply('530 Authentication required')
class Server(socketserver.ThreadingTCPServer):allow_reuse_address=True
Server(('0.0.0.0',587),SMTP).serve_forever()
