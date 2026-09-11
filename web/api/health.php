<?php
declare(strict_types=1);
require_once __DIR__ . '/worker_auth.php';
require_worker_method('GET');
try {
    db()->query('SELECT 1');
    json_response(true, 'OK', ['service' => 'web-api', 'time_utc' => gmdate('c')]);
} catch (Throwable $error) {
    error_log($error->getMessage());
    json_response(false, 'Service unavailable', null, 503);
}
