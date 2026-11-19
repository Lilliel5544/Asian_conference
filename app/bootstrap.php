<?php
declare(strict_types=1);

const ROOT_PATH = __DIR__ . '/..';

function load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

load_env(ROOT_PATH . '/.env');
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Ho_Chi_Minh');

require_once __DIR__ . '/Security.php';
Security::startSession();
Security::sendHeaders();

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/View.php';

