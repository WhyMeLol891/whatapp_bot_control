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

function queue_whatsapp_message(int $contactId, string $body, string $label = 'Manual WhatsApp send'): array
{
    $body = trim($body);
    if ($body === '') {
        return ['ok' => false, 'status' => 'failed', 'message' => 'Message body is required.'];
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name, phone, consent_status, is_active FROM contacts WHERE id = ? LIMIT 1');
    $stmt->execute([$contactId]);
    $contact = $stmt->fetch();
    if (!$contact || !(int) $contact['is_active']) {
        return ['ok' => false, 'status' => 'failed', 'message' => 'This contact is inactive or missing.'];
    }
    if (($contact['consent_status'] ?? '') !== 'opt_in') {
        return ['ok' => false, 'status' => 'failed', 'message' => 'This contact has not opted in.'];
    }

    $campaign = $pdo->prepare("INSERT INTO campaigns (name, body, status, created_by) VALUES (?, ?, 'queued', NULL)");
    $campaign->execute([$label, $body]);
    $campaignId = (int) $pdo->lastInsertId();

    $job = $pdo->prepare('INSERT INTO message_jobs (campaign_id, contact_id, phone, rendered_body, available_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
    $job->execute([$campaignId, (int) $contact['id'], $contact['phone'], $body]);
    $jobId = (int) $pdo->lastInsertId();

    $result = $pdo->prepare('SELECT id, status, last_error, sent_at, updated_at FROM message_jobs WHERE id = ? LIMIT 1');
    $result->execute([$jobId]);
    $row = $result->fetch();

    $status = $row['status'] ?? 'pending';
    return [
        'ok' => true,
        'job_id' => $jobId,
        'status' => $status,
        'message' => 'Message queued. Waiting for the worker to confirm delivery.',
        'last_error' => $row['last_error'] ?? null,
    ];
}

function is_production(): bool
{
    return app_config()['environment'] === 'production';
}
