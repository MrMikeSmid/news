<?php

declare(strict_types=1);

require_once __DIR__ . '/socket.php';

class Pop3Client extends MailSocket
{
    public function connect(): void
    {
        $this->open();
        $this->expectOk($this->readLine());
        if (($this->opts['security'] ?? 'ssl') === 'starttls') {
            $this->command('STLS');
            $this->startTls();
        }
    }

    private function expectOk(string $line): string
    {
        if (!str_starts_with($line, '+OK')) {
            throw new RuntimeException('POP3-fout: ' . trim($line));
        }
        return trim(substr($line, 3));
    }

    public function command(string $command): string
    {
        $this->write($command . "\r\n");
        return $this->expectOk($this->readLine());
    }

    private function multiline(): string
    {
        $data = '';
        while (true) {
            $line = $this->readLine();
            if ($line === ".\r\n" || $line === ".\n") {
                break;
            }
            $data .= str_starts_with($line, '..') ? substr($line, 1) : $line;
        }
        return $data;
    }

    public function login(string $user, string $password): void
    {
        $this->write('USER ' . $user . "\r\n");
        $line = $this->readLine();
        if (!str_starts_with($line, '+OK')) {
            throw new RuntimeException('Inloggen mislukt: ' . trim($line));
        }
        $this->write('PASS ' . $password . "\r\n");
        $line = $this->readLine();
        if (!str_starts_with($line, '+OK')) {
            throw new RuntimeException('Inloggen mislukt: ' . trim($line));
        }
    }

    public function count(): int
    {
        return (int) explode(' ', $this->command('STAT'))[0];
    }

    /** @return array<int,int> berichtnummer => grootte */
    public function sizes(): array
    {
        $this->command('LIST');
        $sizes = [];
        foreach (preg_split('/\r?\n/', $this->multiline()) ?: [] as $line) {
            if (preg_match('/^(\d+)\s+(\d+)/', $line, $m)) {
                $sizes[(int) $m[1]] = (int) $m[2];
            }
        }
        return $sizes;
    }

    public function top(int $n, int $lines = 0): string
    {
        $this->command('TOP ' . $n . ' ' . $lines);
        return $this->multiline();
    }

    public function retrieve(int $n): string
    {
        $this->command('RETR ' . $n);
        return $this->multiline();
    }

    public function quit(): void
    {
        try {
            if ($this->stream) {
                $this->write("QUIT\r\n");
            }
        } catch (Throwable) {
        }
        $this->close();
    }
}
