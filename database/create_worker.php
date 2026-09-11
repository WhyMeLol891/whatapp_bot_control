<?php
declare(strict_types=1);
require_once __DIR__ . '/../web/includes/db.php';

if (PHP_SAPI !== 'cli' || $argc < 2) {
    fwrite(STDERR, "Usage: php database/create_worker.php WORKER_NAME\n");
    exit(1);
}
$name = trim($argv[1]);
if ($name === '' || strlen($name) > 120) {
    fwrite(STDERR, "Worker name must be between 1 and 120 characters.\n");
    exit(1);
}
$token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
$stmt = db()->prepare('INSERT INTO workers (name, token_hash) VALUES (?, ?)');
try {
    $stmt->execute([$name, hash('sha256', $token)]);
    fwrite(STDOUT, "Worker created. Store this token in worker/config.ini; it will not be shown again:\n{$token}\n");
} catch (PDOException $error) {
    fwrite(STDERR, "Unable to create worker.\n");
    exit(1);
}
