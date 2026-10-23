<?php
/**
 * Thin wrapper around the few outbound HTTP calls the site makes.
 *
 * Design notes:
 *   - Credentials come from environment variables via the constants defined
 *     in config/app.cfg.php. No secrets are hard-coded here.
 *   - Requests go through Guzzle (already a Composer dependency) so the app
 *     does not need PEAR's HTTP_Request2, php-pear, or php-xml.
 *   - TLS verification is ALWAYS on. Trust store issues should be fixed on
 *     the server, not by disabling verification.
 *   - Failures are logged via error_log() and surfaced as `null` / `false`
 *     to callers. We never echo exception messages (information disclosure).
 */

declare(strict_types=1);

use GuzzleHttp\Client;

class apiRequest
{
    public $app;

    /** @var Client|null */
    private $http;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Shared client. TLS verification stays on, and HTTP error statuses are
     * returned to the caller instead of thrown, matching the old PEAR client.
     */
    private function http(): Client
    {
        if ($this->http instanceof Client) {
            return $this->http;
        }

        $this->http = new Client([
            'verify'          => true,
            'timeout'         => 10,
            'connect_timeout' => 5,
            'allow_redirects' => true,
            'http_errors'     => false,
            'headers'         => [
                'User-Agent' => 'DataIndex/1.0',
            ],
        ]);

        return $this->http;
    }

    /**
     * Look up a GB VAT number via HMRC.
     * Returns the raw JSON body on success, or false on any failure.
     *
     * The VAT number is sanitised to digits only before being placed into
     * the URL so a caller can't smuggle in path segments.
     *
     * @return string|false
     */
    public function vatNumber(string $vat_number)
    {
        $vat_number = preg_replace('/\D+/', '', $vat_number) ?? '';
        if ($vat_number === '') {
            return false;
        }

        $url = 'https://api.service.hmrc.gov.uk/organisations/vat/check-vat-number/lookup/' . $vat_number;

        try {
            $response = $this->http()->request('GET', $url);
            $status   = $response->getStatusCode();

            if ($status === 200) {
                return (string) $response->getBody();
            }

            if ($status === 429) {
                error_log('apiRequest::vatNumber: HMRC rate limit (429)');
            } elseif ($status >= 400) {
                error_log('apiRequest::vatNumber: HTTP ' . $status);
            }

            return false;
        } catch (Throwable $exception) {
            error_log('apiRequest::vatNumber failed: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Fetch a company's officers from the Companies House API.
     *
     * @return string|null
     */
    public function getDirectors(string $company_number)
    {
        return $this->companiesHouse(
            '/company/' . rawurlencode($company_number) . '/officers',
            __FUNCTION__
        );
    }

    /**
     * Fetch a company's filing history from the Companies House API.
     *
     * @return string|null
     */
    public function getFiling(string $company_number)
    {
        return $this->companiesHouse(
            '/company/' . rawurlencode($company_number) . '/filing-history',
            __FUNCTION__
        );
    }

    /**
     * Shared helper for authenticated Companies House calls.
     *
     * Returns the response body on 200/404. Returns null on rate limits,
     * server errors, or transport failures. Filing history does not fall
     * back to stored rows; officer lookups may still use the local cache.
     *
     * @return string|null
     */
    private function companiesHouse(string $path, string $context)
    {
        if (COMPANIES_HOUSE_API_KEY === '') {
            error_log($context . ': COMPANIES_HOUSE_API_KEY is not configured.');
            return null;
        }

        $url = 'https://api.company-information.service.gov.uk' . $path;

        try {
            $response = $this->http()->request('GET', $url, [
                'auth' => [COMPANIES_HOUSE_API_KEY, ''],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
            $status = $response->getStatusCode();

            if ($status === 200 || $status === 404) {
                return (string) $response->getBody();
            }

            if ($status === 429) {
                error_log($context . ': Companies House rate limit (429)');
            } else {
                error_log($context . ': Companies House HTTP ' . $status);
            }

            return null;
        } catch (Throwable $exception) {
            error_log($context . ': ' . $exception->getMessage());
            return null;
        }
    }

    /**
     * Fetch Google News RSS for a search term. Returns the raw XML body or null.
     * The term is URL-encoded to prevent query-string injection.
     */
    public function googleNews(string $term)
    {
        $url = 'https://news.google.com/rss/search?q=%22' . rawurlencode($term)
             . '%22&hl=en-GB&gl=GB&ceid=GB:en';

        try {
            $response = $this->http()->request('GET', $url);
            return (string) $response->getBody();
        } catch (Throwable $exception) {
            error_log('apiRequest::googleNews failed: ' . $exception->getMessage());
            return null;
        }
    }
}
