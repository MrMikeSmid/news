<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    global $config;
    return rtrim((string) $config['base_path'], '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('public/assets/' . ltrim($path, '/'));
}

function absolute_url(string $path = ''): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function request_path(): string
{
    global $config;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = rtrim((string) $config['base_path'], '/');
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    return '/' . trim($path, '/');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function clean(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function old(string $key, string $default = ''): string
{
    return e((string) ($_POST[$key] ?? $default));
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        abort(419, 'Deze sessie is verlopen. Ga terug en probeer het opnieuw.');
    }
}

function render(string $view, array $data = [], string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    $content = (string) ob_get_clean();
    require __DIR__ . '/views/' . $layout . '.php';
}

function abort(int $status, string $message): never
{
    http_response_code($status);
    render('simple', ['title' => 'Oeps', 'message' => $message]);
    exit;
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function now_local(): DateTimeImmutable
{
    global $config;
    return new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
}

function parse_dt(string $value): DateTimeImmutable
{
    global $config;
    try {
        return new DateTimeImmutable($value, new DateTimeZone($config['timezone']));
    } catch (Throwable) {
        throw new InvalidArgumentException('Ongeldige datum/tijd: ' . $value);
    }
}

function format_dt(string $iso, string $format = 'short'): string
{
    global $config;
    try {
        $dt = (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone($config['timezone']));
    } catch (Throwable) {
        return $iso;
    }
    $months = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    $days = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];
    $date = $dt->format('j') . ' ' . $months[(int) $dt->format('n') - 1];
    return match ($format) {
        'time' => $dt->format('H:i'),
        'date' => $days[(int) $dt->format('w')] . ' ' . $date,
        default => $date . ', ' . $dt->format('H:i'),
    };
}

/** Veilig, beperkt "markdown": **vet**, regels met "- " als lijst, lege regel = nieuwe alinea. */
function text_html(string $text): string
{
    $blocks = preg_split('/\n{2,}/', trim(str_replace("\r", '', $text))) ?: [];
    $html = '';
    foreach ($blocks as $block) {
        $lines = explode("\n", $block);
        $isList = count($lines) > 0 && !array_diff(array_map(fn ($l) => str_starts_with(ltrim($l), '- ') ? 'x' : 'y', $lines), ['x']);
        $fmt = static fn (string $s): string => preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e($s)) ?? e($s);
        if ($isList) {
            $html .= '<ul class="text-list">';
            foreach ($lines as $line) {
                $html .= '<li>' . $fmt(substr(ltrim($line), 2)) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>' . implode('<br>', array_map($fmt, $lines)) . '</p>';
        }
    }
    return $html;
}

function safe_url(?string $url): ?string
{
    $url = trim((string) $url);
    return preg_match('#^https?://#i', $url) ? $url : null;
}
