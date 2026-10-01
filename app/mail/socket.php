<?php

declare(strict_types=1);

/** Gedeelde socketlaag voor IMAP en POP3, inclusief optioneel "alle certificaten accepteren". */
abstract class MailSocket
{
    /** @var resource|null */
    protected $stream = null;

    public function __construct(protected array $opts)
    {
    }

    protected function open(): void
    {
        $verify = !empty($this->opts['verify_cert']);
        $context = stream_context_create(['ssl' => [
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify,
            'peer_name' => $this->opts['host'],
            'SNI_enabled' => true,
        ]]);
        $scheme = ($this->opts['security'] ?? 'ssl') === 'ssl' ? 'ssl' : 'tcp';
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client(
            $scheme . '://' . $this->opts['host'] . ':' . (int) $this->opts['port'],
            $errno,
            $errstr,
            (float) ($this->opts['timeout'] ?? 20),
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$stream) {
            $detail = trim($errstr !== '' ? $errstr : (string) (error_get_last()['message'] ?? ''));
            $detail = preg_replace('/^stream_socket_client\(\): /', '', $detail) ?? $detail;
            throw new RuntimeException('Verbinden met ' . $this->opts['host'] . ':' . $this->opts['port'] . ' mislukt: ' . ($detail ?: 'onbekende fout') . $this->certHint($detail));
        }
        stream_set_timeout($stream, (int) ($this->opts['timeout'] ?? 20));
        $this->stream = $stream;
    }

    protected function certHint(string $error): string
    {
        return stripos($error, 'certificate') !== false || stripos($error, 'SSL') !== false
            ? ' (zet "Alle certificaten accepteren" aan als de server een zelfondertekend certificaat gebruikt)'
            : '';
    }

    protected function startTls(): void
    {
        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        $ok = @stream_socket_enable_crypto($this->stream, true, $method);
        if ($ok !== true) {
            throw new RuntimeException('TLS-verbinding mislukt' . $this->certHint('certificate'));
        }
    }

    protected function readLine(): string
    {
        $line = fgets($this->stream);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            throw new RuntimeException(!empty($meta['timed_out']) ? 'Time-out bij het lezen van de server.' : 'Verbinding met de server verbroken.');
        }
        return $line;
    }

    protected function readBytes(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->stream, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Verbinding verbroken tijdens het lezen.');
            }
            $data .= $chunk;
        }
        return $data;
    }

    protected function write(string $data): void
    {
        if (@fwrite($this->stream, $data) === false) {
            throw new RuntimeException('Schrijven naar de server mislukt.');
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }
        $this->stream = null;
    }
}
