<?php
/**
 * Accepts a Cloudflare Turnstile token from the visitor check and, when
 * Cloudflare accepts it, sets the clearance cookie.
 */

declare(strict_types=1);

require __DIR__ . '/../config/app.cfg.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'The visitor check could not be confirmed.']);
    exit;
}

if (!visitorCheck::sameOrigin()) {
    http_response_code(403);
    echo json_encode(['error' => 'The visitor check could not be confirmed.']);
    exit;
}

$address = $_SERVER['REMOTE_ADDR'] ?? '';
if (!is_string($address)) {
    $address = '';
}
if (visitorCheck::tooManyAttempts($address)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many checks were submitted. Please wait a few minutes and try again.']);
    exit;
}

$raw = file_get_contents('php://input', false, null, 0, 4096);
$payload = json_decode(is_string($raw) ? $raw : '', true);
$token = is_array($payload) && isset($payload['token']) ? (string) $payload['token'] : '';

if (!visitorCheck::acceptToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'The visitor check was not accepted. Please try again.']);
    exit;
}

echo json_encode(['ok' => true]);
