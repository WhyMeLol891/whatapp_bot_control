<?php
declare(strict_types=1);
require_once __DIR__ . '/../web/includes/db.php';

if (PHP_SAPI !== 'cli' || $argc < 3) {
    fwrite(STDERR, "Usage: php database/create_admin.php USERNAME PASSWORD\n");
    exit(1);
}
$username = trim($argv[1]);
$password = $argv[2];
if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username) || strlen($password) < 12) {
    fwrite(STDERR, "Username must be 3-100 safe characters and password must be at least 12 characters.\n");
    exit(1);
}
$stmt = db()->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
try {
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Administrator created.\n");
} catch (PDOException $error) {
    fwrite(STDERR, $error->getCode() === '23000' ? "Username already exists.\n" : "Unable to create administrator.\n");
    exit(1);
}
