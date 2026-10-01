<?php

declare(strict_types=1);

require_once __DIR__ . '/socket.php';

class ImapClient extends MailSocket
{
    private int $tagCounter = 0;

    public function connect(): void
    {
        $this->open();
        $greeting = $this->readLine();
        if (!str_starts_with($greeting, '* OK') && !str_starts_with($greeting, '* PREAUTH')) {
            throw new RuntimeException('Onverwachte begroeting van de IMAP-server: ' . trim($greeting));
        }
        if (($this->opts['security'] ?? 'ssl') === 'starttls') {
            $this->command('STARTTLS');
            $this->startTls();
        }
    }

    public function login(string $user, string $password): void
    {
        $r = $this->command('LOGIN ' . $this->quote($user) . ' ' . $this->quote($password));
        if ($r['status'] !== 'OK') {
            throw new RuntimeException('Inloggen mislukt: ' . $r['text']);
        }
    }

    private function quote(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            throw new RuntimeException('Gebruikersnaam of wachtwoord bevat tekens die IMAP-inloggen niet ondersteunt.');
        }
        return '"' . addcslashes($value, '"\\') . '"';
    }

    /** @return array{status:string,text:string,untagged:array} */
    public function command(string $command): array
    {
        $tag = 'A' . str_pad((string) ++$this->tagCounter, 4, '0', STR_PAD_LEFT);
        $this->write($tag . ' ' . $command . "\r\n");
        $untagged = [];
        while (true) {
            $line = $this->readLine();
            $literals = [];
            while (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
                $literals[] = $this->readBytes((int) $m[1]);
                $line = substr($line, 0, -strlen($m[0])) . '§LIT' . (count($literals) - 1) . '§' . $this->readLine();
            }
            if (str_starts_with($line, $tag . ' ')) {
                preg_match('/^' . $tag . ' (OK|NO|BAD)\s*(.*)$/s', trim($line), $m);
                return ['status' => $m[1] ?? 'BAD', 'text' => $m[2] ?? trim($line), 'untagged' => $untagged];
            }
            $untagged[] = ['text' => rtrim($line), 'literals' => $literals];
        }
    }

    public function folders(): array
    {
        $r = $this->command('LIST "" "*"');
        $folders = [];
        foreach ($r['untagged'] as $entry) {
            if (preg_match('/^\* LIST \(([^)]*)\) (?:"(?:[^"\\\\]|\\\\.)*"|NIL) (.+)$/', $entry['text'], $m)) {
                $name = trim($m[2]);
                $name = str_starts_with($name, '"') ? stripcslashes(substr($name, 1, -1)) : $name;
                $folders[] = ['name' => $name, 'flags' => $m[1]];
            }
        }
        return $folders;
    }

    public function select(string $folder): int
    {
        $r = $this->command('EXAMINE ' . $this->quote($folder));
        if ($r['status'] !== 'OK') {
            throw new RuntimeException('Map "' . $folder . '" openen mislukt: ' . $r['text']);
        }
        foreach ($r['untagged'] as $entry) {
            if (preg_match('/^\* (\d+) EXISTS/', $entry['text'], $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    /** @return int[] UIDs van berichten sinds de gegeven dag (daggranulariteit, ruim genomen). */
    public function searchSince(DateTimeImmutable $since): array
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $day = $since->modify('-1 day');
        $date = $day->format('j') . '-' . $months[(int) $day->format('n') - 1] . '-' . $day->format('Y');
        $r = $this->command('UID SEARCH SINCE ' . $date);
        if ($r['status'] !== 'OK') {
            throw new RuntimeException('Zoeken mislukt: ' . $r['text']);
        }
        $uids = [];
        foreach ($r['untagged'] as $entry) {
            if (preg_match('/^\* SEARCH ?(.*)$/', $entry['text'], $m)) {
                $uids = array_merge($uids, array_map('intval', preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY) ?: []));
            }
        }
        return $uids;
    }

    /** @return array<int,array{uid:int,flags:array,date:?DateTimeImmutable}> */
    public function overview(array $uids): array
    {
        $out = [];
        foreach (array_chunk($uids, 100) as $chunk) {
            $r = $this->command('UID FETCH ' . implode(',', $chunk) . ' (UID FLAGS INTERNALDATE)');
            foreach ($r['untagged'] as $entry) {
                $info = $this->parseFetch($entry);
                if ($info['uid']) {
                    $out[$info['uid']] = $info;
                }
            }
        }
        return $out;
    }

    /** @return array{uid:int,flags:array,date:?DateTimeImmutable,raw:?string} */
    public function fetchMessage(int $uid, int $maxBytes = 400000): array
    {
        $r = $this->command('UID FETCH ' . $uid . ' (UID FLAGS INTERNALDATE BODY.PEEK[]<0.' . $maxBytes . '>)');
        foreach ($r['untagged'] as $entry) {
            $info = $this->parseFetch($entry);
            if ($info['uid'] === $uid) {
                return $info;
            }
        }
        throw new RuntimeException('Bericht ' . $uid . ' niet gevonden.');
    }

    private function parseFetch(array $entry): array
    {
        $text = $entry['text'];
        $info = ['uid' => 0, 'flags' => [], 'date' => null, 'raw' => null];
        if (!preg_match('/^\* \d+ FETCH /', $text)) {
            return $info;
        }
        if (preg_match('/\bUID (\d+)/', $text, $m)) {
            $info['uid'] = (int) $m[1];
        }
        if (preg_match('/FLAGS \(([^)]*)\)/', $text, $m)) {
            $info['flags'] = preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (preg_match('/INTERNALDATE "([^"]+)"/', $text, $m)) {
            $d = DateTimeImmutable::createFromFormat('j-M-Y H:i:s O', trim($m[1]));
            $info['date'] = $d ?: null;
        }
        if (preg_match('/BODY\[[^\]]*\](?:<\d+>)? §LIT(\d+)§/', $text, $m)) {
            $info['raw'] = $entry['literals'][(int) $m[1]] ?? null;
        }
        return $info;
    }

    public function logout(): void
    {
        try {
            if ($this->stream) {
                $this->command('LOGOUT');
            }
        } catch (Throwable) {
        }
        $this->close();
    }
}
