"""Seed a large mailbox inside the disposable local mail-fixture only."""
import os
from pathlib import Path

maildir = Path('/mail/alpha/Maildir/new')
if not Path('/etc/dovecot/test-users').exists() or not maildir.is_dir():
    raise SystemExit('Run this only in the local mail-fixture container')
for number in range(10000):
    path = maildir / f'segment-{number:05d}'
    path.write_bytes((
        f'From: sender@example.test\r\nTo: alpha@example.test\r\n'
        f'Subject: Segment {number}\r\nMessage-ID: <segment-{number}@example.test>\r\n'
        f'Content-Type: text/plain; charset=UTF-8\r\n\r\nsegment-body-{number}\r\n'
    ).encode())
    os.chown(path, 1000, 1000)
print('Seeded 10,000 local fixture messages')
