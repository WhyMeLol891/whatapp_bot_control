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

function is_production(): bool
{
    return app_config()['environment'] === 'production';
}
