<?php
declare(strict_types=1);

$item = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($pathArray['item'] ?? ''));

if ($item === '') {
    header('Location: ' . SITE_URL . 'resources', true, 302);
    exit;
}

$data = $app->queryi(
    "SELECT * FROM blog WHERE slug = :slug LIMIT 1",
    [':slug' => $item]
)->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header('HTTP/1.0 404 Not Found');
    header('Location: ' . SITE_URL . '404', true, 301);
    exit;
}

$pageTitle       = $data['title'] ?? 'Article';
$pageDescription = $data['description'] ?? '';
$canonical       = SITE_URL . 'article/item/' . ($data['slug'] ?? '');

$list_query = $app->queryi(
    "SELECT * FROM blog WHERE category = :cat AND id <> :id ORDER BY id DESC LIMIT 10",
    [':cat' => $data['category'] ?? 0, ':id' => $data['id'] ?? 0]
);
