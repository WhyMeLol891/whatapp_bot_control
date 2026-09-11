<?php
declare(strict_types=1);

const APP_NAME = 'WhatsApp Bot Control';
const APP_TIMEZONE = 'Asia/Kuala_Lumpur';
const SESSION_LIFETIME = 3600;

function app_config(): array
{
    static $config;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'db_dsn' => getenv('WBC_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=synergy1_derricklim_whatapp_bot_control;charset=utf8mb4',
        'db_user' => getenv('WBC_DB_USER') ?: 'synergy1_yenping',
        'db_password' => getenv('WBC_DB_PASSWORD') ?: 'R.zb0ZwEuGZ}*fW2',
        'environment' => getenv('WBC_ENV') ?: 'development',
    ];
    date_default_timezone_set(APP_TIMEZONE);
    return $config;
}

function app_base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '/';
    $script = str_replace('\\', '/', $script);

    if (str_contains($script, '/web/')) {
        $position = strpos($script, '/web/');
        $base = substr($script, 0, $position);
    } else {
        $base = dirname($script);
    }

    $base = rtrim($base, '/');
    return $base === '' ? '/' : $base;
}

function app_redirect_path(string $path): string
{
    $base = app_base_path();
    $normalized = '/' . ltrim($path, '/');

    if ($base === '/') {
        return $normalized;
    }

    return $base . $normalized;
}

function is_production(): bool
{
    return app_config()['environment'] === 'production';
}
