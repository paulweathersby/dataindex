<?php
/**
 * JSON endpoint backing the postcode search typeahead.
 */

declare(strict_types=1);

require __DIR__ . '/../config/app.cfg.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

visitorCheck::guardApi();

$query = isset($_GET['query']) ? (string) $_GET['query'] : '';
$compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $query) ?? '');

if (strlen($compact) < 2) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($compact) > 7) {
    $compact = substr($compact, 0, 7);
}

// The stored postcode has a space before the inward code, for example SW1A 1AA.
$prefix = strlen($compact) >= 5
    ? substr($compact, 0, -3) . ' ' . substr($compact, -3)
    : $compact;
$like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';

try {
    $stmt = $app->query(
        'SELECT postcode, location, country
         FROM postcodes
         WHERE postcode LIKE :like
         ORDER BY postcode
         LIMIT 20',
        [':like' => $like]
    );
    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $postcode = (string) ($row['postcode'] ?? '');
        $rows[] = [
            'postcode' => $postcode,
            'location' => (string) ($row['location'] ?? ''),
            'country'  => (string) ($row['country'] ?? ''),
            'slug'     => strtolower(str_replace(' ', '-', $postcode)),
        ];
    }
} catch (Throwable $exception) {
    error_log('postcode-typeahead: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
