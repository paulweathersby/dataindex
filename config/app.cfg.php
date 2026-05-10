<?php
/**
 * Application bootstrap.
 *
 * All secrets and environment-specific values are loaded from a `.env` file
 * at the project root. See `.env.example` for required keys.
 */

declare(strict_types=1);

// --------------------------------------------------------------------------
// Locate and load the .env file
// --------------------------------------------------------------------------

$rootPath = dirname(__DIR__);

require_once $rootPath . '/vendor/autoload.php';

if (class_exists(Dotenv\Dotenv::class) && file_exists($rootPath . '/.env')) {
    // immutable() so we don't silently overwrite real environment variables
    // that may have been set by the web server / systemd unit.
    Dotenv\Dotenv::createImmutable($rootPath)->safeLoad();
}

/**
 * Fetch an environment variable with a fallback and optional required flag.
 */
function env(string $key, ?string $default = null, bool $required = false): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        if ($required) {
            http_response_code(500);
            error_log("Missing required environment variable: {$key}");
            exit('Server configuration error.');
        }
        return $default;
    }
    return (string) $value;
}

// --------------------------------------------------------------------------
// Environment + error reporting
// --------------------------------------------------------------------------

define('APP_ENV', env('APP_ENV', 'production'));

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
ini_set('log_errors', '1');

// --------------------------------------------------------------------------
// Paths
// --------------------------------------------------------------------------

define('APP_PATH',     env('APP_PATH', $rootPath));
define('CONFIG_PATH',  APP_PATH . '/config');
define('PUBLIC_PATH',  APP_PATH . '/public');
define('PAGE_PATH',    APP_PATH . '/pages');
define('INCLUDE_PATH', APP_PATH . '/include');
define('LIB_PATH',     APP_PATH . '/lib');

define('SITE_URL', env('SITE_URL', '/'));

// --------------------------------------------------------------------------
// Database credentials (consumed in lib/app.class.php)
// --------------------------------------------------------------------------

define('DATABASE_DBMS',     env('DB_DBMS', 'mysql'));
define('DATABASE_SERVER',   env('DB_HOST', '127.0.0.1'));
define('DATABASE_NAME',     env('DB_NAME', ''));
define('DATABASE_USER',     env('DB_USER', ''));
define('DATABASE_PASSWORD', env('DB_PASSWORD', ''));
define('SHARES_DSN',
    DATABASE_DBMS . ':host=' . DATABASE_SERVER . ';dbname=' . DATABASE_NAME . ';charset=utf8mb4');

define('DATABASE_DBMS2',     env('DB2_DBMS', 'mysql'));
define('DATABASE_SERVER2',   env('DB2_HOST', '127.0.0.1'));
define('DATABASE_NAME2',     env('DB2_NAME', ''));
define('DATABASE_USER2',     env('DB2_USER', ''));
define('DATABASE_PASSWORD2', env('DB2_PASSWORD', ''));
define('SHARES_DSN2',
    DATABASE_DBMS2 . ':host=' . DATABASE_SERVER2 . ';dbname=' . DATABASE_NAME2 . ';charset=utf8mb4');

define('MONGO_HOST',     env('MONGO_HOST', '127.0.0.1'));
define('MONGO_PORT',     (int) env('MONGO_PORT', '27017'));
define('MONGO_DATABASE', env('MONGO_DATABASE', ''));

// --------------------------------------------------------------------------
// Auth0 (kept for future use – SDK is currently commented out in public/index.php)
// --------------------------------------------------------------------------

define('AUTH0_DOMAIN',        env('AUTH0_DOMAIN', ''));
define('AUTH0_CLIENT_ID',     env('AUTH0_CLIENT_ID', ''));
define('AUTH0_CLIENT_SECRET', env('AUTH0_CLIENT_SECRET', ''));
define('AUTH0_COOKIE_SECRET', env('AUTH0_COOKIE_SECRET', ''));
define('AUTH0_BASE_URL',      env('AUTH0_BASE_URL', rtrim(SITE_URL, '/')));

// --------------------------------------------------------------------------
// External API credentials
// --------------------------------------------------------------------------

define('COMPANIES_HOUSE_API_KEY', env('COMPANIES_HOUSE_API_KEY', ''));

// --------------------------------------------------------------------------
// Analytics (optional; empty strings disable the relevant snippets)
// --------------------------------------------------------------------------

define('OPENREPLAY_PROJECT_KEY',  env('OPENREPLAY_PROJECT_KEY', ''));
define('OPENREPLAY_INGEST_POINT', env('OPENREPLAY_INGEST_POINT', ''));
define('GA_MEASUREMENT_ID',       env('GA_MEASUREMENT_ID', ''));

// --------------------------------------------------------------------------
// Security response headers (safe to set on every request)
// --------------------------------------------------------------------------

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// --------------------------------------------------------------------------
// Shared view helpers
// --------------------------------------------------------------------------

require_once LIB_PATH . '/helpers.php';

// --------------------------------------------------------------------------
// Core services
// --------------------------------------------------------------------------

require_once LIB_PATH . '/app.class.php';
require_once LIB_PATH . '/apiRequests.class.php';
require_once LIB_PATH . '/dataOps.class.php';

$app = new app();
