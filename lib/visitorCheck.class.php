<?php
/**
 * Cloudflare Turnstile gate.
 *
 * When both keys are set, a visitor completes the check once. A signed cookie
 * then allows the index and its JSON endpoints for 24 hours. The secret is
 * sent only to challenges.cloudflare.com.
 */

declare(strict_types=1);

class visitorCheck
{
    private const COOKIE = 'di_visitor';
    private const TTL = 86400;
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function enabled(): bool
    {
        return self::key('TURNSTILE_SITE_KEY') !== '' && self::key('TURNSTILE_SECRET_KEY') !== '';
    }

    /**
     * Stop a page request that has not passed the check.
     * The privacy policy stays available so the check can be read about first.
     */
    public static function guardPage(string $page): void
    {
        if (!self::enabled() || $page === 'privacy' || self::cookieValid() || self::verifiedBot()) {
            return;
        }

        self::renderChallenge();
        exit;
    }

    /**
     * Stop a JSON endpoint that has not passed the check.
     */
    public static function guardApi(): void
    {
        if (!self::enabled() || self::cookieValid() || self::verifiedBot()) {
            return;
        }

        http_response_code(403);
        echo json_encode(['error' => 'Confirm you are a visitor, then try again.']);
        exit;
    }

    /**
     * Validate a Turnstile token and remember the browser when it passes.
     */
    public static function acceptToken(string $token): bool
    {
        if (!self::enabled() || !self::tokenLooksValid($token)) {
            return false;
        }

        if (!self::siteverify($token)) {
            return false;
        }

        self::remember();

        return true;
    }

    public static function sameOrigin(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!is_string($origin) || $origin === '') {
            return false;
        }

        $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $siteHost = strtolower((string) parse_url(SITE_URL, PHP_URL_HOST));
        $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $requestHost = preg_replace('/:\d+$/', '', $requestHost) ?? $requestHost;

        return $originHost !== '' && ($originHost === $siteHost || $originHost === $requestHost);
    }

    public static function tooManyAttempts(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $dir = sys_get_temp_dir() . '/dataindex-turnstile-limit';
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

        if (count($hits) >= 30) {
            return true;
        }

        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);

        return false;
    }

    private static function key(string $constant): string
    {
        if (!defined($constant)) {
            return '';
        }

        $value = trim((string) constant($constant));
        if ($value === '' || strcasecmp($value, 'CHANGE_ME') === 0 || preg_match('/\s/', $value) === 1) {
            return '';
        }

        return $value;
    }

    /**
     * Cloudflare adds X-Verified-Bot for crawlers it has verified.
     * The value must match VERIFIED_BOT_SECRET, and the request must have
     * come through Cloudflare. A matching User-Agent is not accepted.
     */
    private static function verifiedBot(): bool
    {
        $secret = self::key('VERIFIED_BOT_SECRET');
        if ($secret === '' || strlen($secret) < 16) {
            return false;
        }

        $header = $_SERVER['HTTP_X_VERIFIED_BOT'] ?? '';
        if (!is_string($header) || $header === '') {
            return false;
        }

        $ray = $_SERVER['HTTP_CF_RAY'] ?? '';
        $connecting = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        $viaCloudflare = (is_string($ray) && $ray !== '') || (is_string($connecting) && $connecting !== '');
        if (!$viaCloudflare) {
            return false;
        }

        return hash_equals($secret, $header);
    }

    private static function cookieValid(): bool
    {
        $cookie = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($cookie)) {
            return false;
        }

        $parts = explode('.', $cookie, 2);
        if (count($parts) !== 2 || !preg_match('/^\d+$/', $parts[0])) {
            return false;
        }

        $expires = (int) $parts[0];
        $now = time();
        if ($expires < $now || $expires > $now + self::TTL + 60) {
            return false;
        }

        $expected = hash_hmac('sha256', (string) $expires, self::key('TURNSTILE_SECRET_KEY'));

        return hash_equals($expected, $parts[1]);
    }

    private static function remember(): void
    {
        $expires = time() + self::TTL;
        $value = $expires . '.' . hash_hmac('sha256', (string) $expires, self::key('TURNSTILE_SECRET_KEY'));
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function tokenLooksValid(string $token): bool
    {
        return $token !== ''
            && strlen($token) <= 2048
            && preg_match('/^[\x21-\x7E]+$/', $token) === 1;
    }

    private static function siteverify(string $token): bool
    {
        try {
            $client = new GuzzleHttp\Client([
                'timeout' => 10,
                'connect_timeout' => 5,
                'http_errors' => false,
            ]);
            $response = $client->request('POST', self::VERIFY_URL, [
                'form_params' => [
                    'secret' => self::key('TURNSTILE_SECRET_KEY'),
                    'response' => $token,
                ],
            ]);
        } catch (Throwable $exception) {
            error_log('visitorCheck: siteverify request failed');
            return false;
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (is_array($decoded) && ($decoded['success'] ?? false) === true) {
            return true;
        }

        $codes = 'rejected';
        if (is_array($decoded) && is_array($decoded['error-codes'] ?? null)) {
            $codes = implode(',', $decoded['error-codes']);
        }
        error_log('visitorCheck: siteverify ' . $response->getStatusCode() . ' ' . substr($codes, 0, 120));

        return false;
    }

    private static function renderChallenge(): void
    {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');

        $siteKey = json_encode(self::key('TURNSTILE_SITE_KEY'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Confirm you are a visitor – Data Index</title>
    <link rel="stylesheet" href="' . eAttr(assetUrl('assets/css/dataindex.css')) . '">
</head>
<body>
<main class="visitor-check">
    <div class="panel visitor-check__card">
        <a class="brand" href="/privacy">Data<span class="brand__accent">Index</span></a>
        <h1>Confirm you are a visitor</h1>
        <p class="text-muted">This check keeps automated traffic off the index. It uses Cloudflare Turnstile and lasts for 24 hours in this browser.</p>
        <div id="visitor-check" class="visitor-check__widget"></div>
        <p id="visitor-check-status" class="text-muted" role="status"></p>
        <p class="mt-2"><a href="/privacy">Privacy policy</a></p>
    </div>
</main>
<script>
function onTurnstileLoad() {
    var status = document.getElementById("visitor-check-status");
        window.turnstile.render("#visitor-check", {
        sitekey: ' . $siteKey . ',
        callback: function (token) {
            status.textContent = "Checking…";
            fetch("/turnstile-verify.php", {
                method: "POST",
                headers: { "Content-Type": "application/json", "Accept": "application/json" },
                body: JSON.stringify({ token: token })
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error("rejected");
                }
                window.location.reload();
            }).catch(function () {
                status.textContent = "The check could not be confirmed. Please try again.";
                window.turnstile.reset("#visitor-check");
            });
        },
        "error-callback": function (code) {
            var errorCode = String(code);
            if (errorCode === "110200" || errorCode === "400021") {
                status.textContent = "This address is not listed on the Turnstile widget. In Cloudflare, add this hostname, including localhost when testing here.";
            } else if (errorCode === "400020" || errorCode === "110100" || errorCode === "110110" || errorCode === "400070") {
                status.textContent = "Cloudflare did not accept the Turnstile site key. Copy the site key from the widget again into TURNSTILE_SITE_KEY.";
            } else if (errorCode === "200500") {
                status.textContent = "The visitor check could not reach Cloudflare. Check the connection and try again.";
            } else {
                status.textContent = "The visitor check could not be started. Please try again.";
            }
            return true;
        }
    });
}
window.setTimeout(function () {
    if (!window.turnstile) {
        document.getElementById("visitor-check-status").textContent = "The visitor check could not be loaded. Please try again in a moment.";
    }
}, 8000);
</script>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad&render=explicit" async defer></script>
</body>
</html>';
    }
}
