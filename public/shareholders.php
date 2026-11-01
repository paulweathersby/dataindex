<?php
/**
 * JSON endpoint for the company-page Shareholders tab.
 *
 * Reads share filings already stored for the company, and newer filings from
 * Companies House, then returns the stored shareholders unless a relevant
 * document is new.
 */

declare(strict_types=1);

require __DIR__ . '/../config/app.cfg.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Use the shareholders button on the company page.']);
    exit;
}

if (!requestIsSameOrigin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Shareholders can only be requested from the company page.']);
    exit;
}

visitorCheck::guardApi();

if (visitorCheck::cacheOnly()) {
    http_response_code(403);
    echo json_encode(['error' => 'Only a confirmed visitor can refresh this.']);
    exit;
}

$raw = file_get_contents('php://input', false, null, 0, 2000);
$payload = json_decode(is_string($raw) ? $raw : '', true);
$companyNumber = '';
if (is_array($payload) && isset($payload['company_number'])) {
    $companyNumber = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $payload['company_number']) ?? '');
}

if ($companyNumber === '' || strlen($companyNumber) > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid company number is required.']);
    exit;
}

if (shareholderIsRateLimited(clientAddress())) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many shareholder requests. Please wait a few minutes and try again.']);
    exit;
}

try {
    $company = $app->query(
        'SELECT CompanyNumber FROM companies WHERE CompanyNumber = :cn LIMIT 1',
        [':cn' => $companyNumber]
    )->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('shareholders lookup: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'The company index is unavailable.']);
    exit;
}

if (!$company) {
    http_response_code(404);
    echo json_encode(['error' => 'That company is not in the index.']);
    exit;
}

set_time_limit(180);
@ini_set('memory_limit', '256M');

try {
    $analysis = new shareholderAnalysis($app, new apiRequest($app));
    $result = $analysis->analyse($companyNumber, true);
} catch (Throwable $exception) {
    error_log('shareholders: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'The shareholders could not be read.']);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

function requestIsSameOrigin(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' || !is_string($origin)) {
        return false;
    }

    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $siteHost = strtolower((string) parse_url(SITE_URL, PHP_URL_HOST));
    $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $requestHost = preg_replace('/:\d+$/', '', $requestHost) ?? $requestHost;

    return $originHost !== '' && ($originHost === $siteHost || $originHost === $requestHost);
}

function clientAddress(): string
{
    $address = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($address) ? $address : '';
}

function shareholderIsRateLimited(string $ip): bool
{
    if ($ip === '') {
        return false;
    }

    $dir = sys_get_temp_dir() . '/dataindex-sh-limit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return false;
    }

    $file = $dir . '/' . hash('sha256', $ip);
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            foreach ($decoded as $stamp) {
                if (is_int($stamp) && $stamp > $now - 600) {
                    $hits[] = $stamp;
                }
            }
        }
    }

    if (count($hits) >= 8) {
        return true;
    }

    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);

    return false;
}
