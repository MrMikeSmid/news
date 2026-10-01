<?php

declare(strict_types=1);

$GLOBALS['failures'] = 0;
$GLOBALS['checks'] = 0;

function check(bool $ok, string $label, string $detail = ''): void
{
    $GLOBALS['checks']++;
    if (!$ok) {
        $GLOBALS['failures']++;
    }
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($ok || $detail === '' ? '' : ' -> ' . $detail) . "\n";
}

function finish(): never
{
    echo "\n" . $GLOBALS['checks'] . ' controles, ' . $GLOBALS['failures'] . " mislukt\n";
    exit($GLOBALS['failures'] ? 1 : 0);
}

function test_boot(): void
{
    $dir = sys_get_temp_dir() . '/dagstart-test-' . getmypid();
    @mkdir($dir);
    putenv('DAGSTART_DB=' . $dir . '/db.sqlite');
    putenv('DAGSTART_KEY=' . $dir . '/app.key');
    global $config;
    $_SERVER['HTTP_HOST'] = 'test.local';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require_once dirname(__DIR__) . '/app/bootstrap.php';
    require_once dirname(__DIR__) . '/app/reports.php';
}
