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
        'db_dsn' => getenv('WBC_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=whatsapp_bot;charset=utf8mb4',
        'db_user' => getenv('WBC_DB_USER') ?: 'root',
        'db_password' => getenv('WBC_DB_PASSWORD') ?: '',
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

function queue_whatsapp_message(int $contactId, string $body, string $label = 'Manual WhatsApp send', ?string $scheduledAt = null): array
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

    $availableAtUtc = null;
    if ($scheduledAt !== null && trim($scheduledAt) !== '') {
        $local = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $scheduledAt, new DateTimeZone(APP_TIMEZONE));
        if (!$local || $local < new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE))) {
            return ['ok' => false, 'status' => 'failed', 'message' => 'Choose a future date and time or send immediately.'];
        }
        $availableAtUtc = $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    $campaign = $pdo->prepare("INSERT INTO campaigns (name, body, status, created_by, scheduled_at) VALUES (?, ?, 'queued', NULL, ?)");
    $campaign->execute([$label, $body, $availableAtUtc]);
    $campaignId = (int) $pdo->lastInsertId();

    $job = $pdo->prepare('INSERT INTO message_jobs (campaign_id, contact_id, phone, rendered_body, available_at) VALUES (?, ?, ?, ?, ?)');
    $job->execute([$campaignId, (int) $contact['id'], $contact['phone'], $body, $availableAtUtc ?? gmdate('Y-m-d H:i:s')]);
    $jobId = (int) $pdo->lastInsertId();

    $result = $pdo->prepare('SELECT id, status, last_error, sent_at, updated_at FROM message_jobs WHERE id = ? LIMIT 1');
    $result->execute([$jobId]);
    $row = $result->fetch();

    $status = $row['status'] ?? 'pending';
    return [
        'ok' => true,
        'job_id' => $jobId,
        'status' => $status,
        'message' => $availableAtUtc ? 'Message scheduled successfully.' : 'Message queued. Waiting for the worker to confirm delivery.',
        'last_error' => $row['last_error'] ?? null,
    ];
}

function is_production(): bool
{
    return app_config()['environment'] === 'production';
}
