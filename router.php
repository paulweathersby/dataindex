<?php
/**
 * Router for PHP's built-in server. Apache uses public/.htaccess instead.
 *
 * APP_PATH="/path/to/dataindex" php -S 127.0.0.1:8765 -t public router.php
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? rawurldecode($uri) : '/';
$public = __DIR__ . '/public';
$local = $public . $uri;

if ($uri !== '/' && (is_file($local) || is_dir($local))) {
    return false;
}

$_GET['path'] = trim($uri, '/');
require $public . '/index.php';
