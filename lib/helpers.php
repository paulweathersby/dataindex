<?php
/**
 * Small, dependency-free helpers used by both the controller in
 * public/index.php and the page templates.
 */

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * HTML-escape a value for safe output in page text.
     *
     * `null` and non-scalars are coerced to an empty string so that templates
     * can call `e($data['field'])` without an isset() dance.
     */
    function e($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return '';
    }
}

if (!function_exists('eAttr')) {
    /**
     * HTML-escape specifically for use inside an attribute value.
     * Equivalent to `e()` today but kept as a named helper so intent is
     * clear in templates and we can tighten the ruleset later if needed.
     */
    function eAttr($value): string
    {
        return e($value);
    }
}

if (!function_exists('eUrl')) {
    /**
     * Escape a value for use as part of a URL path/segment.
     * Strips anything that isn't URL-safe and then HTML-escapes the result.
     */
    function eUrl($value): string
    {
        if ($value === null || !is_scalar($value)) {
            return '';
        }
        return e(rawurlencode((string) $value));
    }
}

if (!function_exists('routeIsAllowed')) {
    /**
     * Whitelist of pages that can be loaded from the public controller.
     * Anything not in this list falls through to the 404 page, preventing
     * Local File Inclusion via the `path` query parameter.
     */
    function routeIsAllowed(string $page): bool
    {
        static $allowed = [
            '404',
            'article',
            'companies',
            'company',
            'company-data',
            'home',
            'industry',
            'no-results',
            'postalcode',
            'postcode-data',
            'privacy',
            'resources',
            'sic_code',
            'vat-number-search',
            'vat-number-validation',
        ];

        return in_array($page, $allowed, true);
    }
}

if (!function_exists('parsePath')) {
    /**
     * Parse the front-controller `path` query parameter into:
     *   [0] the page slug
     *   [1] an associative array of key/value segments
     *
     * Example: path = "company/number/12345/tab/overview"
     *   -> ['company', ['number' => '12345', 'tab' => 'overview']]
     *
     * The raw value is constrained to a safe character set before any
     * further processing so it can never escape the `pages/` directory.
     */
    function parsePath(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return ['home', []];
        }

        // Allow letters, digits, hyphen, underscore, and forward-slash only.
        $clean = preg_replace('#[^A-Za-z0-9_\-/]#', '', $raw) ?? '';
        $clean = trim($clean, '/');

        if ($clean === '') {
            return ['home', []];
        }

        $parts = explode('/', $clean);
        $page  = array_shift($parts);

        $segments = [];
        for ($i = 0, $n = count($parts); $i < $n; $i += 2) {
            $key = $parts[$i] ?? null;
            $val = $parts[$i + 1] ?? '';
            if ($key !== null && $key !== '') {
                $segments[$key] = $val;
            }
        }

        return [$page, $segments];
    }
}

if (!function_exists('csrfToken')) {
    /**
     * Lazy-init a per-session CSRF token. Call once on any page that renders
     * a <form method="post">.
     */
    function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $secure = !empty($_SERVER['HTTPS']);

            // PHP < 7.3 doesn't support the session_start() cookie_samesite option,
            // and will emit: "Setting option 'cookie_samesite' failed".
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                session_start([
                    'cookie_httponly' => true,
                    'cookie_samesite' => 'Lax',
                    'cookie_secure'   => $secure,
                ]);
            } else {
                $params = session_get_cookie_params();
                // Pre-7.3: append SameSite to the path attribute.
                // This is a common compatibility technique supported by most browsers.
                $path = ($params['path'] ?? '/') . '; samesite=Lax';

                session_set_cookie_params(
                    (int) ($params['lifetime'] ?? 0),
                    $path,
                    (string) ($params['domain'] ?? ''),
                    $secure,
                    true
                );
                session_start();
            }
        }
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrfCheck')) {
    function csrfCheck(?string $token): bool
    {
        return !empty($_SESSION['_csrf'])
            && is_string($token)
            && hash_equals($_SESSION['_csrf'], $token);
    }
}
