<?php
/**
 * Thin wrapper around the few outbound HTTP calls the site makes.
 *
 * Design notes:
 *   - Credentials come from environment variables via the constants defined
 *     in config/app.cfg.php. No secrets are hard-coded here.
 *   - TLS verification is ALWAYS on. Trust store issues should be fixed on
 *     the server, not by disabling verification.
 *   - Failures are logged via error_log() and surfaced as `null` / `false`
 *     to callers. We never echo exception messages (information disclosure).
 */

declare(strict_types=1);

include_once 'HTTP/Request2.php';

class apiRequest
{
    public $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Build a hardened HTTP_Request2 with sane defaults.
     */
    private function buildRequest(string $url, string $method = HTTP_Request2::METHOD_GET): HTTP_Request2
    {
        $request = new HTTP_Request2($url, $method);
        $request->setConfig([
            'ssl_verify_peer' => true,
            'ssl_verify_host' => true,
            'follow_redirects' => true,
            'timeout'          => 10,
            'connect_timeout'  => 5,
        ]);
        return $request;
    }

    /**
     * Look up a GB VAT number via HMRC.
     * Returns the raw JSON body on success, or false on any failure.
     *
     * The VAT number is sanitised to digits only before being placed into
     * the URL so a caller can't smuggle in path segments.
     */
    public function vatNumber(string $vat_number)
    {
        $vat_number = preg_replace('/\D+/', '', $vat_number) ?? '';
        if ($vat_number === '') {
            return false;
        }

        $url = 'https://api.service.hmrc.gov.uk/organisations/vat/check-vat-number/lookup/' . $vat_number;

        try {
            $request = $this->buildRequest($url);
            $response = $request->send();
            if ($response->getStatus() === 200) {
                return $response->getBody();
            }
            return false;
        } catch (Throwable $exception) {
            error_log('apiRequest::vatNumber failed: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Fetch a company's officers from the Companies House API.
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
     */
    private function companiesHouse(string $path, string $context)
    {
        if (COMPANIES_HOUSE_API_KEY === '') {
            error_log($context . ': COMPANIES_HOUSE_API_KEY is not configured.');
            return null;
        }

        $url = 'https://api.company-information.service.gov.uk' . $path;

        try {
            $request = $this->buildRequest($url);
            $request->setHeader([
                'Content-Length' => 0,
                'Content-Type'   => 'application/json; charset=utf-8',
            ]);
            $request->setAuth(COMPANIES_HOUSE_API_KEY, '', HTTP_Request2::AUTH_BASIC);

            $response = $request->send();
            // The CH API returns useful JSON even on non-200 (404 etc.), so pass it back.
            return $response->getBody();
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
            $request = $this->buildRequest($url);
            $response = $request->send();
            return $response->getBody();
        } catch (Throwable $exception) {
            error_log('apiRequest::googleNews failed: ' . $exception->getMessage());
            return null;
        }
    }
}
