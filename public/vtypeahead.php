<?php
/**
 * JSON endpoint backing the VAT-number search typeahead.
 */

declare(strict_types=1);

require __DIR__ . '/../config/app.cfg.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$query = isset($_GET['query']) ? (string) $_GET['query'] : '';
$query = trim($query);

if ($query === '' || mb_strlen($query) < 2) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';

try {
    $stmt = $app->query(
        "SELECT vat_number, company_name
         FROM vat_number
         WHERE company_name LIKE :like
         ORDER BY company_name
         LIMIT 20",
        [':like' => $like]
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('vtypeahead: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
