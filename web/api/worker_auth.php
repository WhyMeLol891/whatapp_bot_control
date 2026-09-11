<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';

function json_response(bool $success, string $message, mixed $data = null, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_THROW_ON_ERROR);
    exit;
}

function require_worker_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        json_response(false, 'Method not allowed', null, 405);
    }
}

function worker_request(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (!preg_match('/^Bearer\s+([A-Za-z0-9._-]{32,})$/', $header, $matches)) {
        json_response(false, 'Worker authentication required', null, 401);
    }
    $tokenHash = hash('sha256', $matches[1]);
    $stmt = db()->prepare('SELECT * FROM workers WHERE token_hash = ? AND is_enabled = 1 LIMIT 1');
    $stmt->execute([$tokenHash]);
    $worker = $stmt->fetch();
    if (!$worker) {
        json_response(false, 'Invalid worker credentials', null, 401);
    }
    return $worker;
}
