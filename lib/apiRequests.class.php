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

    /** @var int|null */
    private $lastStatus;

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

    public function lastStatus(): ?int
    {
        return $this->lastStatus;
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
     * Account filings only, newest first, up to 100 items.
     * Used to choose annual accounts (type AA) for a financial analysis.
     *
     * @return string|null
     */
    /**
     * Filing history for one Companies House category, newest first.
     *
     * @return string|null
     */
    public function getCategorisedFilingHistory(string $company_number, string $category, int $items)
    {
        $allowed = ['confirmation-statement', 'annual-return', 'capital', 'incorporation'];
        if (!in_array($category, $allowed, true)) {
            return null;
        }

        $items = max(1, min(100, $items));

        return $this->companiesHouse(
            '/company/' . rawurlencode($company_number) . '/filing-history?category=' . $category . '&items_per_page=' . $items,
            __FUNCTION__
        );
    }

    /**
     * One page of filing history. Categories are comma-separated and inclusive.
     * start_index is the offset into the full result, as required by the list operation.
     *
     * @param list<string> $categories
     * @return string|null
     */
    public function getFilingHistoryPage(string $company_number, int $start_index, int $items_per_page, array $categories)
    {
        $allowed = ['confirmation-statement', 'annual-return', 'capital', 'incorporation'];
        $categories = array_values(array_intersect($categories, $allowed));
        if ($categories === []) {
            return null;
        }

        $query = http_build_query([
            'category' => implode(',', $categories),
            'items_per_page' => max(1, min(100, $items_per_page)),
            'start_index' => max(0, $start_index),
        ], '', '&', PHP_QUERY_RFC3986);

        return $this->companiesHouse(
            '/company/' . rawurlencode($company_number) . '/filing-history?' . $query,
            __FUNCTION__
        );
    }

    /**
     * Current and ceased people with significant control.
     *
     * @return string|null
     */
    public function getPersonsWithSignificantControl(string $company_number)
    {
        return $this->companiesHouse(
            '/company/' . rawurlencode($company_number) . '/persons-with-significant-control?items_per_page=100',
            __FUNCTION__
        );
    }

    /**
     * One corporate PSC record. $beneficialOwner selects the
     * corporate-entity-beneficial-owner notification; otherwise the
     * corporate-entity notification.
     *
     * @return string|null
     */
    public function getCorporateEntityPsc(string $company_number, string $notification_id, bool $beneficialOwner)
    {
        $company_number = strtoupper($company_number);
        if (!preg_match('/^[A-Z0-9]{1,12}$/', $company_number) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $notification_id)) {
            return null;
        }

        $kind = $beneficialOwner ? 'corporate-entity-beneficial-owner' : 'corporate-entity';

        return $this->companiesHouse(
            '/company/' . rawurlencode($company_number) . '/persons-with-significant-control/' . $kind . '/' . rawurlencode($notification_id),
            __FUNCTION__
        );
    }

    public function getAccountsFilingHistory(string $company_number)
    {
        return $this->companiesHouse(
            '/company/' . rawurlencode($company_number) . '/filing-history?category=accounts&items_per_page=100',
            __FUNCTION__
        );
    }

    /**
     * One filing-history item, including the document metadata link when the
     * cached filings row does not store it.
     *
     * @return string|null
     */
    public function getFilingItem(string $company_number, string $transaction_id)
    {
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $transaction_id)) {
            return null;
        }

        return $this->companiesHouse(
            '/company/' . rawurlencode($company_number) . '/filing-history/' . rawurlencode($transaction_id),
            __FUNCTION__
        );
    }

    /**
     * Download a Companies House document-api resource.
     * The API key is only sent to that host, and only for a document path.
     *
     * @return array{status:int,body:string,content_type:string}|null
     */
    public function fetchCompaniesHouseDocument(string $url, string $accept): ?array
    {
        $allowedAccept = [
            'application/json',
            'application/xhtml+xml',
            'application/pdf',
        ];
        if (!in_array($accept, $allowedAccept, true)) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https'
            || $host !== 'document-api.company-information.service.gov.uk'
            || !preg_match('#^/document/[A-Za-z0-9_-]+(/content)?$#', $path)
            || !empty($parts['query'])
            || !empty($parts['user'])
        ) {
            error_log('fetchCompaniesHouseDocument: refused URL host or path');
            $this->lastStatus = null;
            return null;
        }

        if (COMPANIES_HOUSE_API_KEY === '') {
            error_log('fetchCompaniesHouseDocument: COMPANIES_HOUSE_API_KEY is not configured.');
            $this->lastStatus = null;
            return null;
        }

        try {
            $response = $this->http()->request('GET', 'https://' . $host . $path, [
                'auth'    => [COMPANIES_HOUSE_API_KEY, ''],
                'timeout' => 25,
                'headers' => [
                    'Accept' => $accept,
                ],
            ]);
            $status = $response->getStatusCode();
            $this->lastStatus = $status;
            $length = (int) $response->getHeaderLine('Content-Length');
            if ($length > 8000000) {
                error_log('fetchCompaniesHouseDocument: response larger than 8MB');
                return null;
            }

            $body = (string) $response->getBody();
            if (strlen($body) > 8000000) {
                return null;
            }

            return [
                'status'       => $status,
                'body'         => $body,
                'content_type' => strtolower($response->getHeaderLine('Content-Type')),
            ];
        } catch (Throwable $exception) {
            $this->lastStatus = null;
            error_log('fetchCompaniesHouseDocument: ' . $exception->getMessage());
            return null;
        }
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
            $this->lastStatus = null;
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
            $this->lastStatus = $status;

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
            $this->lastStatus = null;
            error_log($context . ': ' . $exception->getMessage());
            return null;
        }
    }

    /**
     * Ask OpenAI for a JSON object. The API key is sent only to api.openai.com.
     * Returns the decoded JSON object from the assistant message, or null.
     *
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed> $schema
     * @return array<string, mixed>|null
     */
    public function openAiJson(array $messages, array $schema, string $schemaName, int $maxTokens = 800): ?array
    {
        if (!defined('OPENAI_API_KEY') || trim(OPENAI_API_KEY) === '' || trim(OPENAI_API_KEY) === 'CHANGE_ME') {
            return null;
        }

        $model = defined('OPENAI_MODEL') && trim(OPENAI_MODEL) !== '' ? trim(OPENAI_MODEL) : 'gpt-4.1';

        try {
            $response = $this->http()->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'timeout' => 90,
                'headers' => [
                    'Authorization' => 'Bearer ' . trim(OPENAI_API_KEY),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0,
                    'max_completion_tokens' => max(100, min(4000, $maxTokens)),
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => $schemaName,
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                    'messages' => $messages,
                ],
            ]);
        } catch (Throwable $exception) {
            error_log('apiRequest::openAiJson failed: ' . $exception->getMessage());
            return null;
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        if ($status !== 200) {
            error_log('apiRequest::openAiJson: HTTP ' . $status . ' ' . substr($body, 0, 300));
            return null;
        }

        $decoded = json_decode($body, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || $content === '') {
            return null;
        }

        $parsed = json_decode($content, true);

        return is_array($parsed) ? $parsed : null;
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
