"""Lokale nepservers (IMAP, POP3, CalDAV) voor tests. Verbindt nooit met echte providers."""
import base64, datetime, zoneinfo, email.message, email.utils, http.server, re, socketserver, ssl, sys, threading

CERT, KEY = sys.argv[1], sys.argv[2]
NOW = datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0)


def make(subject, sender, to, hours_ago, text=None, html=None, attach=None, msgid=None, reply_to=None, bulk=False, charset='utf-8'):
    m = email.message.EmailMessage()
    m['From'] = sender
    m['To'] = to
    m['Subject'] = subject
    m['Date'] = email.utils.format_datetime(NOW - datetime.timedelta(hours=hours_ago))
    m['Message-ID'] = msgid or '<%s@fixture.test>' % abs(hash(subject))
    if reply_to:
        m['In-Reply-To'] = reply_to
    if bulk:
        m['List-Unsubscribe'] = '<mailto:unsub@fixture.test>'
    if text is not None:
        m.set_content(text, charset=charset) if charset != 'utf-8' else m.set_content(text)
    if html is not None:
        if text is None:
            m.set_content(html, subtype='html')
        else:
            m.add_alternative(html, subtype='html')
    if attach:
        m.add_attachment(b'%PDF-fake', maintype='application', subtype='pdf', filename=attach)
    return m.as_bytes().replace(b'\r\n', b'\n').replace(b'\n', b'\r\n'), NOW - datetime.timedelta(hours=hours_ago)


MESSAGES = [
    # uid 1: te oud (3 dagen)
    (make('Oude mail', 'Oud <oud@fixture.test>', 'me@fixture.test', 72, text='Heel oud.'), ['\\Seen']),
    # uid 2: gisteravond, wacht op reactie
    (make('Offerte bekijken?', 'Jan de Vries <jan@bedrijf.test>', 'me@fixture.test', 10, text='Hoi,\nKun je morgen de offerte bekijken en reageren?\nGroet Jan', attach='offerte.pdf'), []),
    # uid 3: html only met umlaut + nieuwsbrief
    (make('Nieuwsbrief oktober über', 'Shop <news@shop.test>', 'me@fixture.test', 5, html='<html><body><h1>Aanbieding</h1><p>Alles <b>30%</b> korting&nbsp;nu</p><script>x()</script></body></html>', bulk=True), ['\\Seen']),
    # uid 4: beantwoord
    (make('Re: Planning', 'Marie <marie@werk.test>', 'me@fixture.test', 2, text='Dank je, tot dinsdag!', reply_to='<planning@fixture.test>'), ['\\Seen', '\\Answered']),
    # uid 5: iso-8859-1
    (make('Café afspraak', 'Els <els@fixture.test>', 'me@fixture.test', 1, text='Zullen we bij het café afspreken? Prêts €5', charset='iso-8859-15'), []),
]


def tls_ctx():
    c = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    c.load_cert_chain(CERT, KEY)
    return c


class Base(socketserver.StreamRequestHandler):
    def send(self, data):
        self.wfile.write(data if isinstance(data, bytes) else data.encode())
        self.wfile.flush()

    def upgrade(self):
        self.connection = tls_ctx().wrap_socket(self.connection, server_side=True)
        self.rfile = self.connection.makefile('rb')
        self.wfile = self.connection.makefile('wb')


class Imap(Base):
    tls = False

    def handle(self):
        if self.tls:
            self.upgrade()
        self.send('* OK fixture IMAP ready\r\n')
        auth = False
        while True:
            line = self.rfile.readline()
            if not line:
                return
            tag, _, rest = line.decode().strip().partition(' ')
            verb = rest.split(' ')[0].upper()
            if verb == 'STARTTLS':
                self.send('%s OK Begin TLS\r\n' % tag)
                self.upgrade()
            elif verb == 'LOGIN':
                ok = 'fixture-pass' in rest and 'me@fixture.test' in rest
                auth = ok
                self.send('%s %s\r\n' % (tag, 'OK Logged in' if ok else 'NO [AUTHENTICATIONFAILED] Invalid credentials'))
            elif not auth and verb != 'LOGOUT':
                self.send('%s BAD login first\r\n' % tag)
            elif verb == 'LIST':
                self.send('* LIST (\\HasNoChildren) "/" "INBOX"\r\n* LIST (\\HasNoChildren \\Sent) "/" "Sent Messages"\r\n%s OK done\r\n' % tag)
            elif verb in ('EXAMINE', 'SELECT'):
                self.send('* %d EXISTS\r\n%s OK [READ-ONLY] done\r\n' % (len(MESSAGES), tag))
            elif verb == 'UID' and rest.upper().startswith('UID SEARCH'):
                self.send('* SEARCH %s\r\n%s OK done\r\n' % (' '.join(str(i + 1) for i in range(len(MESSAGES))), tag))
            elif verb == 'UID' and rest.upper().startswith('UID FETCH'):
                m = re.match(r'UID FETCH ([\d,]+) \((.*)\)$', rest, re.I)
                for uid in [int(x) for x in m.group(1).split(',')]:
                    (raw, date), flags = MESSAGES[uid - 1]
                    d = date.strftime('%d-%b-%Y %H:%M:%S +0000')
                    head = '* %d FETCH (UID %d FLAGS (%s) INTERNALDATE "%s"' % (uid, uid, ' '.join(flags), d)
                    if 'BODY' in m.group(2).upper():
                        part = raw
                        pm = re.search(r'<0\.(\d+)>', m.group(2))
                        if pm:
                            part = raw[:int(pm.group(1))]
                        self.send(head.encode() + (' BODY[]<0> {%d}\r\n' % len(part)).encode() + part + b')\r\n')
                    else:
                        self.send(head + ')\r\n')
                self.send('%s OK done\r\n' % tag)
            elif verb == 'LOGOUT':
                self.send('* BYE\r\n%s OK bye\r\n' % tag)
                return
            else:
                self.send('%s BAD unknown\r\n' % tag)


class ImapTls(Imap):
    tls = True


class Pop3(Base):
    tls = False

    def handle(self):
        if self.tls:
            self.upgrade()
        self.send('+OK fixture POP3 ready\r\n')
        user = None
        auth = False
        msgs = [m[0][0] for m in MESSAGES]
        while True:
            line = self.rfile.readline()
            if not line:
                return
            parts = line.decode().strip().split(' ')
            verb = parts[0].upper()
            if verb == 'STLS':
                self.send('+OK go\r\n')
                self.upgrade()
            elif verb == 'USER':
                user = parts[1]
                self.send('+OK\r\n')
            elif verb == 'PASS':
                auth = user == 'me@fixture.test' and parts[1] == 'fixture-pass'
                self.send('+OK in\r\n' if auth else '-ERR invalid\r\n')
            elif not auth and verb != 'QUIT':
                self.send('-ERR login first\r\n')
            elif verb == 'STAT':
                self.send('+OK %d %d\r\n' % (len(msgs), sum(len(m) for m in msgs)))
            elif verb == 'LIST':
                self.send('+OK\r\n' + ''.join('%d %d\r\n' % (i + 1, len(m)) for i, m in enumerate(msgs)) + '.\r\n')
            elif verb in ('TOP', 'RETR'):
                raw = msgs[int(parts[1]) - 1]
                if verb == 'TOP':
                    raw = raw.split(b'\r\n\r\n', 1)[0] + b'\r\n\r\n' + b'\r\n'.join(raw.split(b'\r\n\r\n', 1)[1].split(b'\r\n')[:int(parts[2])])
                stuffed = b'\r\n'.join((b'.' + l if l.startswith(b'.') else l) for l in raw.split(b'\r\n'))
                self.send(b'+OK\r\n' + stuffed + b'\r\n.\r\n')
            elif verb == 'QUIT':
                self.send('+OK bye\r\n')
                return
            else:
                self.send('-ERR unknown\r\n')


class Pop3Tls(Pop3):
    tls = True


# ---------- CalDAV ----------
def ics(uid, summary, start, end, extra=''):
    return ('BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//fixture//EN\r\nBEGIN:VEVENT\r\nUID:%s\r\nSUMMARY:%s\r\nDTSTART%s\r\nDTEND%s\r\n%sEND:VEVENT\r\nEND:VCALENDAR\r\n'
            % (uid, summary, start, end, extra))


def today_str(days=0, h=0, m=0):
    d = datetime.datetime.now(zoneinfo.ZoneInfo('Europe/Amsterdam')).date() + datetime.timedelta(days=days)
    return '%s%02d%02d00' % (d.strftime('%Y%m%d'), h, m)


class DavHandler(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def reply(self, code, body, ctype='application/xml; charset=utf-8'):
        data = body.encode()
        self.send_response(code)
        self.send_header('Content-Type', ctype)
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def authed(self):
        auth = self.headers.get('Authorization', '')
        if auth == 'Basic ' + base64.b64encode(b'me@icloud.test:app-pass').decode():
            return True
        self.send_response(401)
        self.send_header('WWW-Authenticate', 'Basic realm="fixture"')
        self.send_header('Content-Length', '0')
        self.end_headers()
        return False

    def do_PROPFIND(self):
        if not self.authed():
            return
        n = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(n).decode()
        if self.path in ('/', '/.well-known/caldav'):
            self.reply(207, '<d:multistatus xmlns:d="DAV:"><d:response><d:href>/</d:href><d:propstat><d:prop><d:current-user-principal><d:href>/123/principal/</d:href></d:current-user-principal></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response></d:multistatus>')
        elif self.path == '/123/principal/':
            self.reply(207, '<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:response><d:href>/123/principal/</d:href><d:propstat><d:prop><c:calendar-home-set><d:href>/123/calendars/</d:href></c:calendar-home-set></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response></d:multistatus>')
        elif self.path == '/123/calendars/':
            self.reply(207, '''<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
<d:response><d:href>/123/calendars/</d:href><d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>
<d:response><d:href>/123/calendars/home/</d:href><d:propstat><d:prop><d:displayname>Thuis</d:displayname><d:resourcetype><d:collection/><c:calendar/></d:resourcetype><c:supported-calendar-component-set><c:comp name="VEVENT"/></c:supported-calendar-component-set></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>
<d:response><d:href>/123/calendars/work/</d:href><d:propstat><d:prop><d:displayname>Werk</d:displayname><d:resourcetype><d:collection/><c:calendar/></d:resourcetype><c:supported-calendar-component-set><c:comp name="VEVENT"/></c:supported-calendar-component-set></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>
<d:response><d:href>/123/calendars/reminders/</d:href><d:propstat><d:prop><d:displayname>Herinneringen</d:displayname><d:resourcetype><d:collection/><c:calendar/></d:resourcetype><c:supported-calendar-component-set><c:comp name="VTODO"/></c:supported-calendar-component-set></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>
</d:multistatus>''')
        else:
            self.reply(404, '')

    def do_REPORT(self):
        if not self.authed():
            return
        self.rfile.read(int(self.headers.get('Content-Length', 0)))
        if self.path == '/123/calendars/home/':
            events = [
                ics('e1', 'Tandarts', ';TZID=Europe/Amsterdam:' + today_str(0)[:8] + 'T093000', ';TZID=Europe/Amsterdam:' + today_str(0)[:8] + 'T101500', 'LOCATION:Praktijk Zuid\r\n'),
                ics('e2', 'Verjaardag Opa', ';VALUE=DATE:' + today_str(0)[:8], ';VALUE=DATE:' + today_str(1)[:8]),
                ics('e3', 'Dagelijkse standup', ';TZID=Europe/Amsterdam:' + today_str(-10, 8, 0)[:8] + 'T080000', ';TZID=Europe/Amsterdam:' + today_str(-10, 8, 15)[:8] + 'T081500', 'RRULE:FREQ=DAILY;INTERVAL=1\r\nEXDATE;TZID=Europe/Amsterdam:' + today_str(-9, 8, 0)[:8] + 'T080000\r\n'),
            ]
        elif self.path == '/123/calendars/work/':
            events = [ics('e4', 'Sprintreview', ':' + today_str(0, 14, 0)[:8] + 'T140000Z', ':' + today_str(0, 15, 0)[:8] + 'T150000Z', 'DESCRIPTION:Demo voor het team\\, met taart\r\n')]
        else:
            events = []
        responses = ''.join('<d:response><d:href>%s%s.ics</d:href><d:propstat><d:prop><c:calendar-data>%s</c:calendar-data></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>' % (self.path, i, e.replace('&', '&amp;').replace('<', '&lt;')) for i, e in enumerate(events))
        self.reply(207, '<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">%s</d:multistatus>' % responses)

    def do_GET(self):
        if self.path == '/feed.xml':
            fresh = (NOW - datetime.timedelta(hours=2)).strftime('%a, %d %b %Y %H:%M:%S +0000')
            old = (NOW - datetime.timedelta(hours=60)).strftime('%a, %d %b %Y %H:%M:%S +0000')
            self.reply(200, '<?xml version="1.0"?><rss version="2.0"><channel><title>Fixture nieuws</title>'
                       '<item><title>Nieuwe &amp; verse koppen</title><link>http://localhost:9880/a1</link><pubDate>%s</pubDate><description>&lt;p&gt;Het &lt;b&gt;nieuws&lt;/b&gt; van vandaag.&lt;/p&gt;</description></item>'
                       '<item><title>Oud bericht</title><link>http://localhost:9880/a2</link><pubDate>%s</pubDate><description>Oud.</description></item>'
                       '</channel></rss>' % (fresh, old), 'application/rss+xml')
        elif self.path == '/page.html':
            self.reply(200, '<html><head><title>Voorpagina</title><style>x{}</style></head><body><h1>Kop van de dag</h1><p>Tekst van de pagina.</p>'
                       '<a href="/artikel-1">Een lang genoeg artikel over iets</a><a href="/x">kort</a><script>evil()</script></body></html>', 'text/html; charset=utf-8')
        elif self.path == '/redirect':
            self.send_response(302)
            self.send_header('Location', '/feed.xml')
            self.send_header('Content-Length', '0')
            self.end_headers()
        else:
            self.reply(200, 'ok', 'text/plain')


class Srv(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


if __name__ == '__main__':
    for port, handler in [(9143, Imap), (9993, ImapTls), (9110, Pop3), (9995, Pop3Tls)]:
        threading.Thread(target=Srv(('127.0.0.1', port), handler).serve_forever, daemon=True).start()
    http.server.ThreadingHTTPServer.allow_reuse_address = True
    dav = http.server.ThreadingHTTPServer(('127.0.0.1', 9880), DavHandler)
    davs = http.server.ThreadingHTTPServer(('127.0.0.1', 9881), DavHandler)
    davs.socket = tls_ctx().wrap_socket(davs.socket, server_side=True)
    threading.Thread(target=davs.serve_forever, daemon=True).start()
    print('ready', flush=True)
    dav.serve_forever()
