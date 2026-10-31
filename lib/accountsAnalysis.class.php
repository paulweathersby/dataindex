<?php
/**
 * Builds a financial overview from up to three annual accounts (filing type AA).
 *
 * Filing history comes from the Companies House API, with the local filings
 * table used as the cached copy when the API has nothing newer. Figures are
 * read from the filed document: inline XBRL when Companies House publishes it,
 * with OpenAI used for scanned PDFs and for any lines the tags omit.
 * Without an OpenAI key, figures are read from the accounts PDF locally.
 */

declare(strict_types=1);

class accountsAnalysis
{
    /** @var app */
    private $app;

    /** @var apiRequest */
    private $api;

    /** @var dataOps */
    private $dataOps;

    private const MAX_ACCOUNTS = 3;
    private const MAX_OCR_PAGES = 10;
    private const CACHE_TTL = 43200;

    /** @var array<string, string> */
    private const ACCOUNT_LABELS = [
        'accounts-with-accounts-type-full' => 'Full accounts',
        'accounts-with-accounts-type-small' => 'Small company accounts',
        'accounts-with-accounts-type-medium' => 'Medium company accounts',
        'accounts-with-accounts-type-group' => 'Group accounts',
        'accounts-with-accounts-type-dormant' => 'Dormant accounts',
        'accounts-with-accounts-type-micro-entity' => 'Micro-entity accounts',
        'accounts-with-accounts-type-total-exemption-full' => 'Unaudited accounts',
        'accounts-with-accounts-type-total-exemption-small' => 'Unaudited small accounts',
        'accounts-with-accounts-type-audit-exemption-subsiduary' => 'Audit-exempt subsidiary accounts',
        'accounts-with-accounts-type-initial' => 'Initial accounts',
        'accounts-with-accounts-type-interim' => 'Interim accounts',
    ];

    /**
     * Concept local-names, in preference order, for each overview line.
     *
     * @var array<string, list<string>>
     */
    private const CONCEPTS = [
        'turnover' => ['TurnoverRevenue', 'Turnover', 'Revenue'],
        'gross_profit' => ['GrossProfitLoss'],
        'operating_profit' => ['OperatingProfitLoss'],
        'profit_before_tax' => ['ProfitLossOnOrdinaryActivitiesBeforeTax', 'ProfitLossBeforeTax'],
        'profit_for_year' => ['ProfitLossForPeriod', 'ProfitLoss', 'ProfitLossOnOrdinaryActivitiesAfterTax'],
        'fixed_assets' => ['FixedAssets'],
        'tangible_assets' => ['PropertyPlantEquipment', 'TangibleFixedAssets'],
        'intangible_assets' => ['IntangibleAssets', 'IntangibleFixedAssets'],
        'right_of_use_assets' => ['RightofuseAssets', 'RightOfUseAssets'],
        'current_assets' => ['CurrentAssets'],
        'debtors' => ['Debtors'],
        'cash' => ['CashBankOnHand', 'CashCashEquivalents', 'CashBankInHand'],
        'creditors' => ['Creditors', 'CreditorsDueWithinOneYear'],
        'net_current' => ['NetCurrentAssetsLiabilities'],
        'total_assets_less_current' => ['TotalAssetsLessCurrentLiabilities'],
        'net_assets' => ['NetAssetsLiabilities'],
        'equity' => ['Equity'],
        'employees' => ['AverageNumberEmployeesDuringPeriod'],
    ];

    /** @var list<array{key:string,label:string,kind:string}> */
    private const ROWS = [
        ['key' => 'turnover', 'label' => 'Turnover', 'kind' => 'money'],
        ['key' => 'gross_profit', 'label' => 'Gross profit / (loss)', 'kind' => 'money'],
        ['key' => 'operating_profit', 'label' => 'Operating profit / (loss)', 'kind' => 'money'],
        ['key' => 'profit_before_tax', 'label' => 'Profit / (loss) before tax', 'kind' => 'money'],
        ['key' => 'profit_for_year', 'label' => 'Profit / (loss) for the year', 'kind' => 'money'],
        ['key' => 'fixed_assets', 'label' => 'Fixed assets', 'kind' => 'money'],
        ['key' => 'current_assets', 'label' => 'Current assets', 'kind' => 'money'],
        ['key' => 'debtors', 'label' => 'Debtors', 'kind' => 'money'],
        ['key' => 'cash', 'label' => 'Cash at bank and in hand', 'kind' => 'money'],
        ['key' => 'creditors', 'label' => 'Creditors', 'kind' => 'money'],
        ['key' => 'net_current', 'label' => 'Net current assets / (liabilities)', 'kind' => 'money'],
        ['key' => 'total_assets_less_current', 'label' => 'Total assets less current liabilities', 'kind' => 'money'],
        ['key' => 'net_assets', 'label' => 'Net assets / (liabilities)', 'kind' => 'money'],
        ['key' => 'equity', 'label' => 'Equity', 'kind' => 'money'],
        ['key' => 'employees', 'label' => 'Average number of employees', 'kind' => 'count'],
    ];

    public function __construct($app, apiRequest $api, dataOps $dataOps)
    {
        $this->app = $app;
        $this->api = $api;
        $this->dataOps = $dataOps;
    }

    /**
     * @return array{
     *   summary: string,
     *   periods: list<array<string, string|null>>,
     *   rows: list<array{label: string, values: list<string|null>}>,
     *   footnotes: list<string>
     * }
     */
    public function analyse(string $companyNumber): array
    {
        $filings = $this->selectAnnualAccounts($companyNumber);
        if ($filings === []) {
            return $this->emptyResult(
                'No annual accounts (type AA) were found for this company at Companies House.'
            );
        }

        $cached = $this->readCache($companyNumber, $filings);
        if ($cached !== null) {
            return $cached;
        }

        $periods = [];
        $figures = [];
        $footnotes = [];

        foreach ($filings as $filing) {
            $parsed = $this->readFiling($companyNumber, $filing);
            $periods[] = $parsed['period'];
            $figures[] = $parsed['figures'];
            foreach ($parsed['notes'] as $note) {
                if (!in_array($note, $footnotes, true)) {
                    $footnotes[] = $note;
                }
            }
        }

        $rateLimited = $periods !== [] && !in_array(false, array_map(static function (array $period): bool {
            return ($period['warning'] ?? null) === 'rate-limit';
        }, $periods), true);
        if ($rateLimited) {
            return $this->emptyResult(
                'Companies House is temporarily limiting requests, so the accounts could not be downloaded. Please try again in a few minutes.'
            );
        }

        foreach ($periods as $index => $period) {
            if (($period['warning'] ?? null) === 'rate-limit') {
                $periods[$index]['warning'] = 'Companies House is temporarily limiting requests. Please try again in a few minutes.';
            }
        }

        $result = [
            'summary' => 'Figures from the latest annual accounts (type AA) filed at Companies House. Each column is that filing’s own reporting period.',
            'periods' => $periods,
            'rows' => $this->buildRows($figures),
            'footnotes' => $footnotes,
        ];

        if ($result['rows'] !== []) {
            $this->writeCache($companyNumber, $filings, $result);
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function selectAnnualAccounts(string $companyNumber): array
    {
        $merged = [];

        foreach ($this->dataOps->loadAnnualAccountFilings($companyNumber) as $row) {
            if (strtoupper((string) ($row['type'] ?? '')) !== 'AA') {
                continue;
            }
            $merged[$this->filingKey($row)] = $row;
        }

        $raw = $this->api->getAccountsFilingHistory($companyNumber);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw);
            if (json_last_error() === JSON_ERROR_NONE && is_object($decoded)) {
                foreach ($this->dataOps->processFilingResults($decoded) as $row) {
                    if (strtoupper((string) ($row['type'] ?? '')) !== 'AA') {
                        continue;
                    }
                    $merged[$this->filingKey($row)] = $row;
                }
            }
        }

        $rows = array_values($merged);
        usort($rows, function (array $a, array $b): int {
            $aKey = [$this->dateOrEmpty($a['made_up_date'] ?? $a['date'] ?? ''), $this->dateOrEmpty($a['date'] ?? '')];
            $bKey = [$this->dateOrEmpty($b['made_up_date'] ?? $b['date'] ?? ''), $this->dateOrEmpty($b['date'] ?? '')];
            return $bKey <=> $aKey;
        });

        $chosen = [];
        $seenPeriods = [];
        foreach ($rows as $row) {
            $period = $this->dateOrEmpty($row['made_up_date'] ?? '');
            if ($period === '') {
                $period = $this->dateOrEmpty($row['date'] ?? '');
            }
            if ($period === '') {
                $period = (string) ($row['transaction_id'] ?? '');
            }
            if ($period === '' || isset($seenPeriods[$period])) {
                continue;
            }
            $seenPeriods[$period] = true;
            $chosen[] = $row;
            if (count($chosen) === self::MAX_ACCOUNTS) {
                break;
            }
        }

        return array_reverse($chosen);
    }

    /**
     * @param array<string, mixed> $filing
     * @return array{period: array<string, string|null>, figures: array<string, float>, notes: list<string>}
     */
    private function readFiling(string $companyNumber, array $filing): array
    {
        $madeUp = $this->dateOrEmpty($filing['made_up_date'] ?? '');
        $filed = $this->dateOrEmpty($filing['date'] ?? '');
        $period = [
            'title' => $madeUp !== '' ? 'Year ended ' . $this->formatDate($madeUp) : 'Annual accounts',
            'filed' => $filed !== '' ? 'Filed ' . $this->formatDate($filed) : '',
            'accounts' => $this->accountLabel((string) ($filing['description'] ?? '')),
            'url' => $this->documentPageUrl((string) ($filing['link_self'] ?? '')),
            'warning' => null,
        ];

        $metadataUrl = trim((string) ($filing['link_metadata'] ?? ''));
        if ($metadataUrl === '') {
            $metadataUrl = $this->metadataUrlFromTransaction($companyNumber, (string) ($filing['transaction_id'] ?? ''));
        }

        if ($metadataUrl === '') {
            if ($this->api->lastStatus() === 429) {
                $period['warning'] = 'rate-limit';
            } else {
                $period['warning'] = 'Companies House did not provide a document for ' . $period['title'] . '.';
            }
            return ['period' => $period, 'figures' => [], 'notes' => []];
        }

        $document = $this->downloadAccounts($metadataUrl);
        if ($document === null) {
            $period['warning'] = $this->api->lastStatus() === 429
                ? 'rate-limit'
                : 'The accounts document for ' . $period['title'] . ' could not be downloaded.';
            return ['period' => $period, 'figures' => [], 'notes' => []];
        }

        if ($document['kind'] === 'ixbrl') {
            $figures = $this->figuresFromInlineXbrl($document['body'], $madeUp);
            $notes = [];
            if ($this->hasOverviewFigures($figures)) {
                $notes[] = 'Figures are taken from the iXBRL accounts filed at Companies House.';
            }
            $filled = $this->fillFiguresFromOpenAiText($figures, $this->ixbrlPlainText($document['body']), $madeUp);
            if ($filled > 0) {
                $notes[] = 'OpenAI read the accounts text and supplied lines that were not tagged.';
            }
            if ($this->hasOverviewFigures($figures)) {
                return [
                    'period' => $period,
                    'figures' => $figures,
                    'notes' => $notes,
                ];
            }
        }

        if ($document['kind'] === 'pdf' && $this->openAiEnabled()) {
            $figures = $this->figuresFromOpenAiPdf($document['body'], $madeUp);
            if ($this->hasOverviewFigures($figures)) {
                return [
                    'period' => $period,
                    'figures' => $figures,
                    'notes' => ['Figures were read from the accounts PDF by OpenAI. Amounts printed in thousands are converted to pounds.'],
                ];
            }
        }

        $text = '';
        if ($document['kind'] === 'pdf') {
            $text = $this->textFromPdf($document['body']);
        }

        if ($text !== '' && $this->textHasAccounts($text)) {
            $figures = $this->figuresFromStatementText($text);
            if ($this->hasOverviewFigures($figures)) {
                return [
                    'period' => $period,
                    'figures' => $figures,
                    'notes' => ['Figures are read from the accounts PDF filed at Companies House. Amounts shown in £\'000 are converted to pounds.'],
                ];
            }
        }

        if ($document['kind'] === 'pdf' && $this->openAiEnabled()) {
            $period['warning'] = 'OpenAI could not read figures from the accounts PDF for ' . $period['title'] . '.';
        } elseif ($document['kind'] === 'pdf' && $this->executable('pdftoppm') === null) {
            $period['warning'] = 'The accounts for ' . $period['title'] . ' were filed as a PDF, and this server cannot read figures from that file.';
        } elseif ($document['kind'] === 'pdf' && $this->executable('tesseract') === null && !$this->textHasAccounts($text)) {
            $period['warning'] = 'The accounts for ' . $period['title'] . ' were filed as a scanned PDF, and this server cannot read figures from the scan.';
        } else {
            $period['warning'] = 'No financial figures could be read from the accounts for ' . $period['title'] . '.';
        }

        return ['period' => $period, 'figures' => [], 'notes' => []];
    }

    /**
     * @return array{kind:string,body:string}|null
     */
    private function downloadAccounts(string $metadataUrl): ?array
    {
        $metadataUrl = $this->normaliseDocumentUrl($metadataUrl);
        if ($metadataUrl === null) {
            return null;
        }

        $meta = $this->api->fetchCompaniesHouseDocument($metadataUrl, 'application/json');
        $resources = [];
        if (is_array($meta) && $meta['status'] === 200) {
            $decoded = json_decode($meta['body'], true);
            if (is_array($decoded) && isset($decoded['resources']) && is_array($decoded['resources'])) {
                $resources = array_map('strtolower', array_keys($decoded['resources']));
            }
        }

        $contentUrl = $metadataUrl . '/content';
        $preferXhtml = $resources === [] || in_array('application/xhtml+xml', $resources, true);

        if ($preferXhtml) {
            $xhtml = $this->api->fetchCompaniesHouseDocument($contentUrl, 'application/xhtml+xml');
            if (is_array($xhtml) && $xhtml['status'] === 200 && $this->looksLikeIxbrl($xhtml['body'])) {
                return ['kind' => 'ixbrl', 'body' => $xhtml['body']];
            }
        }

        $pdf = $this->api->fetchCompaniesHouseDocument($contentUrl, 'application/pdf');
        if (is_array($pdf) && $pdf['status'] === 200 && str_starts_with($pdf['body'], '%PDF')) {
            return ['kind' => 'pdf', 'body' => $pdf['body']];
        }

        return null;
    }

    private function metadataUrlFromTransaction(string $companyNumber, string $transactionId): string
    {
        if ($transactionId === '') {
            return '';
        }

        $raw = $this->api->getFilingItem($companyNumber, $transactionId);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $decoded = json_decode($raw);
        if (!is_object($decoded)) {
            return '';
        }

        $url = '';
        if (isset($decoded->links) && is_object($decoded->links) && isset($decoded->links->document_metadata)) {
            $url = (string) $decoded->links->document_metadata;
        }

        return $this->normaliseDocumentUrl($url) ?? '';
    }

    private function normaliseDocumentUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (str_ends_with($path, '/content')) {
            $path = substr($path, 0, -8);
        }

        if (($parts['scheme'] ?? '') !== 'https'
            || $host !== 'document-api.company-information.service.gov.uk'
            || !preg_match('#^/document/[A-Za-z0-9_-]+$#', $path)
        ) {
            return null;
        }

        return 'https://' . $host . $path;
    }

    /**
     * @return array<string, float>
     */
    public function figuresFromInlineXbrl(string $xml, string $madeUpDate): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $contexts = $this->inlineContexts($dom);
        $periodEnd = $this->selectPeriodEnd($contexts, $madeUpDate);

        /** @var array<string, array{value:float,rank:int}> $best */
        $best = [];
        foreach ($dom->getElementsByTagName('*') as $node) {
            if (!$node instanceof DOMElement || strtolower($node->localName) !== 'nonfraction') {
                continue;
            }

            $concept = $this->localConcept((string) $node->getAttribute('name'));
            $contextId = (string) $node->getAttribute('contextRef');
            $context = $contexts[$contextId] ?? null;
            if ($concept === '' || $context === null) {
                continue;
            }

            $end = $context['instant'] !== '' ? $context['instant'] : $context['end'];
            if ($periodEnd !== '' && $end !== $periodEnd) {
                continue;
            }

            $value = $this->parseInlineAmount($node);
            if ($value === null) {
                continue;
            }

            foreach (self::CONCEPTS as $key => $names) {
                if (!in_array($concept, $names, true)) {
                    continue;
                }

                $rank = 0;
                if ($context['members'] === []) {
                    $rank = 2;
                } elseif ($key === 'creditors' && $context['members'] === ['CurrentFinancialInstruments']) {
                    $rank = 1;
                }

                if ($rank === 0) {
                    continue;
                }

                $preference = array_search($concept, $names, true);
                $score = ($rank * 100) - (int) $preference;
                if (!isset($best[$key]) || $score > $best[$key]['rank']) {
                    $best[$key] = ['value' => $value, 'rank' => $score];
                }
            }
        }

        $figures = [];
        foreach ($best as $key => $item) {
            $figures[$key] = $item['value'];
        }

        if (!isset($figures['fixed_assets'])) {
            $parts = [];
            foreach (['tangible_assets', 'intangible_assets', 'right_of_use_assets'] as $part) {
                if (isset($figures[$part])) {
                    $parts[] = $figures[$part];
                }
            }
            if ($parts !== []) {
                $figures['fixed_assets'] = array_sum($parts);
            }
        }

        unset($figures['tangible_assets'], $figures['intangible_assets'], $figures['right_of_use_assets']);

        return $figures;
    }

    /**
     * @return array<string, array{instant:string,end:string,members:list<string>}>
     */
    private function inlineContexts(DOMDocument $dom): array
    {
        $contexts = [];
        foreach ($dom->getElementsByTagName('*') as $node) {
            if (!$node instanceof DOMElement || strtolower($node->localName) !== 'context') {
                continue;
            }

            $instant = '';
            $end = '';
            $members = [];
            foreach ($node->getElementsByTagName('*') as $child) {
                if (!$child instanceof DOMElement) {
                    continue;
                }
                $name = strtolower($child->localName);
                $text = substr(trim($child->textContent), 0, 10);
                if ($name === 'instant') {
                    $instant = $text;
                } elseif ($name === 'enddate') {
                    $end = $text;
                } elseif ($name === 'explicitmember') {
                    $member = $this->localConcept(trim($child->textContent));
                    // "Original" marks the reported total in some iXBRL files, not a breakdown.
                    if ($member !== '' && $member !== 'Original') {
                        $members[] = $member;
                    }
                }
            }

            $id = (string) $node->getAttribute('id');
            if ($id !== '') {
                $contexts[$id] = [
                    'instant' => $instant,
                    'end' => $end,
                    'members' => $members,
                ];
            }
        }

        return $contexts;
    }

    /**
     * @param array<string, array{instant:string,end:string,members:list<string>}> $contexts
     */
    private function selectPeriodEnd(array $contexts, string $madeUpDate): string
    {
        $madeUpDate = $this->dateOrEmpty($madeUpDate);
        $ends = [];
        foreach ($contexts as $context) {
            $end = $context['instant'] !== '' ? $context['instant'] : $context['end'];
            if ($end !== '') {
                $ends[$end] = true;
            }
        }

        if ($madeUpDate !== '' && isset($ends[$madeUpDate])) {
            return $madeUpDate;
        }

        $list = array_keys($ends);
        rsort($list);

        return $list[0] ?? $madeUpDate;
    }

    private function parseInlineAmount(DOMElement $node): ?float
    {
        $raw = trim(preg_replace('/\s+/', '', $node->textContent) ?? '');
        if ($raw === '' || !preg_match('/[0-9]/', $raw)) {
            return null;
        }

        $negative = $node->getAttribute('sign') === '-'
            || str_contains($raw, '(')
            || str_starts_with($raw, '-');
        $clean = str_replace(['(', ')', ',', '£', '+'], '', $raw);
        $clean = ltrim($clean, '-');
        if (!is_numeric($clean)) {
            return null;
        }

        $value = (float) $clean;
        $scale = $node->getAttribute('scale');
        if ($scale !== '' && is_numeric($scale)) {
            $value *= 10 ** (int) $scale;
        }

        return $negative ? -abs($value) : $value;
    }

    /**
     * Read labelled lines from an accounts statement.
     * The first amount on a line is the current year; a leading note number is ignored.
     *
     * @return array<string, float>
     */
    public function figuresFromStatementText(string $text): array
    {
        $text = str_replace(["\r\n", "\r", "\xC2\xA0", "\u{2019}", '`'], ["\n", "\n", ' ', "'", "'"], $text);
        $thousands = preg_match("/£\\s*['’`]?\\s*000|\\bGBP\\s*000|['’`]\\s*000\\b|\\bin thousands\\b/i", $text) === 1;
        $scale = $thousands ? 1000 : 1;

        $figures = [];
        $inFixed = false;
        $components = [];
        $fixedTotal = null;

        foreach (preg_split('/\n/', $text) ?: [] as $line) {
            $line = trim(preg_replace('/[_|]+/', ' ', $line) ?? '');
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            $line = preg_replace('/(\d),\s+(\d{3})\b/', '$1,$2', $line) ?? $line;
            if ($line === '') {
                continue;
            }

            [$label, $tokens] = $this->splitLabelAndAmounts($line);
            $tokens = $this->dropYearTokens($tokens);
            $key = $this->matchStatementLine($label);

            if ($key === 'fixed_assets' && $tokens === []) {
                $inFixed = true;
                continue;
            }
            if ($key === 'current_assets' && $tokens === []) {
                $inFixed = false;
                continue;
            }
            if ($inFixed && $label === '' && $tokens !== []) {
                $amount = $this->currentYearAmount($tokens, $scale, false);
                if ($amount !== null) {
                    $fixedTotal = $amount;
                }
                $inFixed = false;
                continue;
            }
            if ($inFixed && preg_match('/^deferred tax assets\b/i', $label)) {
                $amount = $this->currentYearAmount($tokens, $scale, false);
                if ($amount !== null) {
                    $components['deferred_tax'] = $amount;
                }
                continue;
            }

            if ($key === null || $tokens === []) {
                continue;
            }

            if (in_array($key, ['intangible_assets', 'tangible_assets', 'right_of_use_assets'], true)) {
                $amount = $this->currentYearAmount($tokens, $scale, false);
                if ($amount !== null) {
                    $components[$key] = $amount;
                    $inFixed = true;
                }
                continue;
            }

            if ($key === 'fixed_assets') {
                $amount = $this->currentYearAmount($tokens, $scale, false);
                if ($amount !== null) {
                    $fixedTotal = $amount;
                }
                continue;
            }

            if (isset($figures[$key])) {
                continue;
            }

            $amount = $this->currentYearAmount($tokens, $scale, $key === 'employees');
            if ($amount !== null) {
                // Accounts PDFs print creditors in brackets. The figure is the amount owed.
                if ($key === 'creditors') {
                    $amount = abs($amount);
                }
                $figures[$key] = $amount;
            }
        }

        if ($fixedTotal !== null) {
            $figures['fixed_assets'] = $fixedTotal;
        } elseif ($components !== []) {
            $figures['fixed_assets'] = array_sum($components);
        }

        return $this->reconcileStatement($figures);
    }

    /**
     * Scanned accounts sometimes misread a digit. Where the statement's own
     * arithmetic is available, prefer the figure that line implies.
     *
     * @param array<string, float> $figures
     * @return array<string, float>
     */
    private function reconcileStatement(array $figures): array
    {
        if (isset($figures['turnover'], $figures['cost_of_sales'])) {
            $implied = $figures['turnover'] - abs($figures['cost_of_sales']);
            if (!isset($figures['gross_profit']) || abs($figures['gross_profit'] - $implied) > max(1000, abs($implied) * 0.02)) {
                $figures['gross_profit'] = $implied;
            }
        }

        if (isset($figures['gross_profit'], $figures['admin_expenses'])) {
            $implied = $figures['gross_profit'] - abs($figures['admin_expenses']);
            if (!isset($figures['operating_profit']) || abs($figures['operating_profit'] - $implied) > max(1000, abs($implied) * 0.05)) {
                $figures['operating_profit'] = $implied;
            }
        }

        if (isset($figures['profit_before_tax'], $figures['tax'])) {
            $implied = $figures['profit_before_tax'] + $figures['tax'];
            if (!isset($figures['profit_for_year']) || abs($figures['profit_for_year'] - $implied) > max(1000, abs($implied) * 0.05)) {
                $figures['profit_for_year'] = $implied;
            }
        }

        unset($figures['cost_of_sales'], $figures['admin_expenses'], $figures['tax']);

        return $figures;
    }

    /**
     * @return array{0:string,1:list<string>}
     */
    private function splitLabelAndAmounts(string $line): array
    {
        if (!preg_match_all('/\(?\d[\d,]*(?:\.\d+)?\)?/', $line, $matches, PREG_OFFSET_CAPTURE) || $matches[0] === []) {
            return [$line, []];
        }

        $label = trim(substr($line, 0, $matches[0][0][1]));
        $label = trim($label, " \t:-");
        $tokens = [];
        foreach ($matches[0] as $match) {
            $tokens[] = $match[0];
        }

        return [$label, $tokens];
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function dropYearTokens(array $tokens): array
    {
        return array_values(array_filter($tokens, function (string $token): bool {
            return !$this->isYearToken($token);
        }));
    }

    private function matchStatementLine(string $label): ?string
    {
        $label = strtolower(trim(preg_replace('/\s+/', ' ', $label) ?? ''));
        if ($label === '') {
            return null;
        }

        $checks = [
            'turnover' => '/^turnover\b/',
            'cost_of_sales' => '/^cost of sales\b/',
            'gross_profit' => '/^gross (profit|loss)\b/',
            'admin_expenses' => '/^administrative expenses\b/',
            'operating_profit' => '/^operating (profit|loss)\b/',
            'profit_for_year' => '/^(profit|loss)\b.*\bfor the\b/',
            'profit_before_tax' => '/^(profit|loss)\b.*\bbefore tax/',
            'tax' => '/^tax\b/',
            'intangible_assets' => '/^intangible assets\b/',
            'tangible_assets' => '/^tangible( fixed)? assets\b/',
            'right_of_use_assets' => '/^right[- ]of[- ]use assets\b/',
            'fixed_assets' => '/^fixed assets$/',
            'current_assets' => '/^current assets$/',
            'debtors' => '/^debtors\b/',
            'cash' => '/^cash (at bank|and cash equivalents)\b/',
            'creditors_after' => '/^creditors\b.*\bafter\b/',
            'creditors' => '/^creditors\b/',
            'net_current' => '/^net current (assets|liabilities)\b/',
            'total_assets_less_current' => '/^total assets less current liabilities\b/',
            'net_assets' => '/^net (assets|liabilities)\b/',
            'equity' => '/^(total equity|capital and reserves)$/',
            'employees' => '/average number of (employees|persons employed|people employed)/',
        ];

        foreach ($checks as $key => $pattern) {
            if (preg_match($pattern, $label)) {
                if ($key === 'creditors_after') {
                    return null;
                }
                return $key;
            }
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     */
    private function currentYearAmount(array $tokens, int $scale, bool $employees): ?float
    {
        if ($tokens === []) {
            return null;
        }

        if (!$employees && count($tokens) >= 2 && $this->isNoteToken($tokens[0])) {
            array_shift($tokens);
        }

        foreach ($tokens as $token) {
            if ($employees && $this->isYearToken($token)) {
                continue;
            }
            return $this->parseAmountToken($token, $employees ? 1 : $scale);
        }

        return null;
    }

    private function parseAmountToken(string $token, int $scale): ?float
    {
        $negative = str_contains($token, '(') || str_starts_with($token, '-');
        $commas = substr_count($token, ',');
        $clean = str_replace([',', '(', ')', ' '], '', $token);
        $clean = ltrim($clean, '-');
        if (!is_numeric($clean)) {
            return null;
        }

        $value = (float) $clean;
        if ($scale === 1000 && $commas < 2) {
            $value *= 1000;
        }

        return $negative ? -abs($value) : $value;
    }

    private function isNoteToken(string $token): bool
    {
        if (!preg_match('/^\d{1,2}$/', $token)) {
            return false;
        }

        $value = (int) $token;

        return $value >= 1 && $value <= 45;
    }

    private function isYearToken(string $token): bool
    {
        if (!preg_match('/^\d{4}$/', $token)) {
            return false;
        }

        $value = (int) $token;

        return $value >= 1990 && $value <= 2100;
    }

    private function openAiEnabled(): bool
    {
        if (!defined('OPENAI_API_KEY')) {
            return false;
        }

        $key = trim(OPENAI_API_KEY);

        return $key !== '' && $key !== 'CHANGE_ME';
    }

    /**
     * Fill overview lines the iXBRL tags did not include. Tagged values are kept.
     *
     * @param array<string, float> $figures
     */
    private function fillFiguresFromOpenAiText(array &$figures, string $text, string $madeUp): int
    {
        if (!$this->openAiEnabled() || trim($text) === '') {
            return 0;
        }

        $read = $this->requestOpenAiFigures([
            ['type' => 'text', 'text' => "These are UK annual accounts for the period ending {$madeUp}. Extract the current-period figures only.\n\n" . $text],
        ], $madeUp);
        if ($read === null) {
            return 0;
        }

        $filled = 0;
        foreach ($read as $key => $value) {
            if (!isset($figures[$key])) {
                $figures[$key] = $value;
                $filled++;
            }
        }

        return $filled;
    }

    /**
     * @return array<string, float>
     */
    private function figuresFromOpenAiPdf(string $pdf, string $madeUp): array
    {
        if (str_starts_with($pdf, '%PDF') && strlen($pdf) <= 8000000) {
            $fromFile = $this->requestOpenAiFigures([
                $this->pdfFilePart('accounts.pdf', $pdf),
                [
                    'type' => 'text',
                    'text' => 'This PDF is UK annual accounts. The reporting period ends ' . ($madeUp !== '' ? $madeUp : 'on the date shown on the statement') . '. Read the current-year column only, not the comparative column.',
                ],
            ], $madeUp);
            if ($this->hasOverviewFigures($fromFile ?? [])) {
                return $fromFile;
            }
        }

        $dir = $this->makeTempDir();
        if ($dir === null) {
            return [];
        }

        try {
            $path = $dir . '/accounts.pdf';
            file_put_contents($path, $pdf);
            $pdftoppm = $this->executable('pdftoppm');
            if ($pdftoppm === null) {
                return [];
            }

            $this->commandOutput([
                $pdftoppm, '-png', '-r', '140', '-f', '1', '-l', (string) self::MAX_OCR_PAGES, $path, $dir . '/page',
            ], 60);

            $pages = glob($dir . '/page-*.png') ?: [];
            natsort($pages);
            $pages = array_values($pages);
            if ($pages === []) {
                return [];
            }

            $chosen = $this->chooseStatementPages($pages);
            $content = [[
                'type' => 'text',
                'text' => 'These images are pages from UK annual accounts. The reporting period ends ' . ($madeUp !== '' ? $madeUp : 'on the date shown on the statement') . '. Read the current-year column only, not the comparative column.',
            ]];
            foreach ($chosen as $image) {
                $url = $this->imageDataUrl($image);
                if ($url === null) {
                    continue;
                }
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $url, 'detail' => 'high'],
                ];
            }

            if (count($content) < 2) {
                return [];
            }

            return $this->requestOpenAiFigures($content, $madeUp) ?? [];
        } finally {
            $this->removeDir($dir);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function pdfFilePart(string $filename, string $pdf): array
    {
        return [
            'type' => 'file',
            'file' => [
                'filename' => $filename,
                'file_data' => 'data:application/pdf;base64,' . base64_encode($pdf),
            ],
        ];
    }

    /**
     * @param list<string> $pages
     * @return list<string>
     */
    private function chooseStatementPages(array $pages): array
    {
        $tesseract = $this->executable('tesseract');
        $scored = [];
        $hasIncome = false;
        $hasPosition = false;
        if ($tesseract !== null) {
            foreach ($pages as $index => $page) {
                $text = $this->commandOutput([$tesseract, $page, 'stdout', '--psm', '6', '-l', 'eng'], 25) ?? '';
                $score = 0;
                $income = preg_match('/comprehensive income|income statement|profit and loss/i', $text) === 1;
                $position = preg_match('/financial position|balance sheet/i', $text) === 1;
                if ($income) {
                    $score += 5;
                    $hasIncome = true;
                }
                if ($position) {
                    $score += 5;
                    $hasPosition = true;
                }
                if (preg_match('/\bturnover\b/i', $text)) {
                    $score += 3;
                }
                if (preg_match('/fixed assets|net assets|total equity/i', $text)) {
                    $score += 2;
                }
                if ($score > 0) {
                    $scored[] = ['page' => $page, 'score' => $score, 'index' => $index];
                }
                if ($hasIncome && $hasPosition) {
                    break;
                }
            }
        }

        if ($scored === []) {
            $start = min(4, count($pages) - 1);
            $start = max(0, $start);

            return array_slice($pages, $start, 4);
        }

        usort($scored, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'] ?: $a['index'] <=> $b['index'];
        });
        $picked = array_slice($scored, 0, 3);
        usort($picked, static function (array $a, array $b): int {
            return $a['index'] <=> $b['index'];
        });

        return array_map(static function (array $item): string {
            return $item['page'];
        }, $picked);
    }

    /**
     * @param list<array<string, mixed>> $userContent
     * @return array<string, float>|null
     */
    private function requestOpenAiFigures(array $userContent, string $madeUp): ?array
    {
        $period = $madeUp !== '' ? $madeUp : 'the current reporting date printed on the accounts';
        $messages = [
            [
                'role' => 'system',
                'content' => 'You extract figures from UK company annual accounts. Report only amounts printed for the period ending ' . $period . '. Use the current-year column, which is the left-hand amount column, and ignore the comparative column. If the statement is headed £\'000, £000 or "in thousands", set scale to "thousands" and return the number as printed, without multiplying. Otherwise set scale to "pounds". Use null when a line is not printed. Do not invent or calculate a missing line. Creditors are the amount owed falling due within one year, as a positive number. Net liabilities and losses are negative.',
            ],
            [
                'role' => 'user',
                'content' => $userContent,
            ],
        ];

        $parsed = $this->api->openAiJson($messages, $this->openAiSchema(), 'accounts_overview');
        if ($parsed === null) {
            return null;
        }

        return $this->figuresFromOpenAiPayload($parsed);
    }

    /**
     * @return array<string, mixed>
     */
    private function openAiSchema(): array
    {
        $number = ['type' => ['number', 'null']];
        $properties = ['scale' => ['type' => 'string', 'enum' => ['pounds', 'thousands']]];
        $required = ['scale'];
        foreach ([
            'turnover', 'gross_profit', 'operating_profit', 'profit_before_tax', 'profit_for_year',
            'fixed_assets', 'current_assets', 'debtors', 'cash', 'creditors',
            'net_current', 'total_assets_less_current', 'net_assets', 'equity', 'employees',
        ] as $key) {
            $properties[$key] = $number;
            $required[] = $key;
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, float>
     */
    private function figuresFromOpenAiPayload(array $payload): array
    {
        $scale = ($payload['scale'] ?? '') === 'thousands' ? 1000 : 1;
        $figures = [];
        foreach ([
            'turnover', 'gross_profit', 'operating_profit', 'profit_before_tax', 'profit_for_year',
            'fixed_assets', 'current_assets', 'debtors', 'cash', 'creditors',
            'net_current', 'total_assets_less_current', 'net_assets', 'equity', 'employees',
        ] as $key) {
            if (!isset($payload[$key]) || !is_numeric($payload[$key])) {
                continue;
            }
            $value = (float) $payload[$key];
            if ($key === 'employees') {
                $figures[$key] = $value;
                continue;
            }
            if ($key === 'creditors') {
                $value = abs($value);
            }
            if ($scale === 1000 && abs($value) < 1000000) {
                $value *= 1000;
            }
            $figures[$key] = $value;
        }

        return $figures;
    }

    private function ixbrlPlainText(string $xml): string
    {
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $xml) ?? $xml;
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);
        if (strlen($text) > 18000) {
            $text = substr($text, 0, 18000);
        }

        return $text;
    }

    private function imageDataUrl(string $pngPath): ?string
    {
        if (!function_exists('imagecreatefrompng')) {
            $bytes = @file_get_contents($pngPath);
            if (!is_string($bytes) || $bytes === '' || strlen($bytes) > 1500000) {
                return null;
            }

            return 'data:image/png;base64,' . base64_encode($bytes);
        }

        $image = @imagecreatefrompng($pngPath);
        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxWidth = 1400;
        if ($width > $maxWidth && function_exists('imagescale')) {
            $scaled = imagescale($image, $maxWidth, (int) round($height * ($maxWidth / $width)));
            if ($scaled !== false) {
                imagedestroy($image);
                $image = $scaled;
            }
        }

        ob_start();
        imagejpeg($image, null, 75);
        $bytes = ob_get_clean();
        imagedestroy($image);
        if (!is_string($bytes) || $bytes === '') {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($bytes);
    }

    private function textFromPdf(string $pdf): string
    {
        $pdftotext = $this->executable('pdftotext');
        $extracted = $this->pdfBytesToText($pdf);
        if ($pdftotext !== null) {
            $dir = $this->makeTempDir();
            if ($dir !== null) {
                try {
                    $path = $dir . '/accounts.pdf';
                    file_put_contents($path, $pdf);
                    $layout = $this->commandOutput([$pdftotext, '-layout', '-f', '1', '-l', '12', $path, '-']);
                    if ($layout !== null && strlen(trim($layout)) > strlen(trim($extracted))) {
                        $extracted = $layout;
                    }
                } finally {
                    $this->removeDir($dir);
                }
            }
        }

        if ($this->textHasAccounts($extracted)) {
            return $extracted;
        }

        $ocr = $this->ocrPdf($pdf);
        if ($ocr !== '') {
            return $extracted . "\n" . $ocr;
        }

        return $extracted;
    }

    private function ocrPdf(string $pdf): string
    {
        $pdftoppm = $this->executable('pdftoppm');
        $tesseract = $this->executable('tesseract');
        if ($pdftoppm === null || $tesseract === null) {
            return '';
        }

        $dir = $this->makeTempDir();
        if ($dir === null) {
            return '';
        }

        try {
            $path = $dir . '/accounts.pdf';
            file_put_contents($path, $pdf);
            $this->commandOutput([
                $pdftoppm,
                '-png',
                '-r',
                '160',
                '-f',
                '1',
                '-l',
                (string) self::MAX_OCR_PAGES,
                $path,
                $dir . '/page',
            ], 60);

            $pages = glob($dir . '/page-*.png') ?: [];
            natsort($pages);
            $text = '';
            foreach ($pages as $page) {
                $pageText = $this->commandOutput([$tesseract, $page, 'stdout', '--psm', '6', '-l', 'eng'], 40);
                if ($pageText !== null) {
                    $text .= "\n" . $pageText;
                }
                if ($this->statementPagesFound($text)) {
                    break;
                }
            }

            return $text;
        } finally {
            $this->removeDir($dir);
        }
    }

    private function statementPagesFound(string $text): bool
    {
        $hasIncome = preg_match('/\bturnover\b/i', $text) === 1;
        $hasPosition = preg_match('/net (assets|liabilities)|total equity|total assets less current liabilities/i', $text) === 1;

        return $hasIncome && $hasPosition;
    }

    /**
     * Pull visible text from an uncompressed or Flate-compressed PDF.
     * Scanned filings have no text layer; those are handled by OCR.
     */
    private function pdfBytesToText(string $pdf): string
    {
        $chunks = [$pdf];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches)) {
            foreach ($matches[1] as $stream) {
                $inflated = @gzuncompress($stream);
                if (!is_string($inflated)) {
                    $inflated = @gzinflate($stream);
                }
                if (is_string($inflated) && $inflated !== '') {
                    $chunks[] = $inflated;
                }
            }
        }

        $lines = [];
        foreach ($chunks as $chunk) {
            if (!preg_match_all('/\(((?:\\\\.|[^\\\\)]){1,300})\)\s*Tj/s', $chunk, $found)) {
                continue;
            }
            foreach ($found[1] as $literal) {
                $decoded = stripcslashes(str_replace(['\(', '\)'], ['(', ')'], $literal));
                $decoded = trim($decoded);
                if ($decoded !== '') {
                    $lines[] = $decoded;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<array<string, float>> $figuresByPeriod
     * @return list<array{label:string,values:list<string|null>}>
     */
    private function buildRows(array $figuresByPeriod): array
    {
        $rows = [];
        foreach (self::ROWS as $definition) {
            $values = [];
            $any = false;
            foreach ($figuresByPeriod as $figures) {
                if (!isset($figures[$definition['key']])) {
                    $values[] = null;
                    continue;
                }
                $any = true;
                $values[] = $definition['kind'] === 'count'
                    ? $this->formatCount($figures[$definition['key']])
                    : $this->formatMoney($figures[$definition['key']]);
            }
            if ($any) {
                $rows[] = ['label' => $definition['label'], 'values' => $values];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, float> $figures
     */
    private function hasOverviewFigures(array $figures): bool
    {
        foreach (self::ROWS as $definition) {
            if (isset($figures[$definition['key']])) {
                return true;
            }
        }

        return false;
    }

    private function formatMoney(float $value): string
    {
        $negative = $value < 0;
        $decimals = abs($value - round($value)) < 0.001 ? 0 : 2;
        $formatted = '£' . number_format(abs($value), $decimals);

        return $negative ? '(' . $formatted . ')' : $formatted;
    }

    private function formatCount(float $value): string
    {
        $decimals = abs($value - round($value)) < 0.001 ? 0 : 1;

        return number_format($value, $decimals);
    }

    private function accountLabel(string $description): string
    {
        if (isset(self::ACCOUNT_LABELS[$description])) {
            return self::ACCOUNT_LABELS[$description];
        }

        $text = str_replace(['-', '_'], ' ', $description);
        $text = preg_replace('/^accounts with accounts type /i', '', $text) ?? $text;
        $text = trim($text);

        return $text === '' ? 'Annual accounts' : ucwords($text);
    }

    private function documentPageUrl(string $linkSelf): ?string
    {
        if (!preg_match('#^/company/[A-Za-z0-9]+/filing-history/[A-Za-z0-9_-]+$#', $linkSelf)) {
            return null;
        }

        return 'https://find-and-update.company-information.service.gov.uk'
            . $linkSelf
            . '/document?format=pdf&download=0';
    }

    private function formatDate(string $iso): string
    {
        $date = date_create($iso);

        return $date instanceof DateTimeInterface ? $date->format('j M Y') : $iso;
    }

    private function dateOrEmpty($value): string
    {
        $value = substr(trim((string) $value), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || str_starts_with($value, '0000')) {
            return '';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function filingKey(array $row): string
    {
        $id = trim((string) ($row['transaction_id'] ?? ''));
        if ($id !== '') {
            return $id;
        }

        $link = trim((string) ($row['link_self'] ?? ''));
        if ($link !== '') {
            return $link;
        }

        return $this->dateOrEmpty($row['made_up_date'] ?? '') . '|' . $this->dateOrEmpty($row['date'] ?? '');
    }

    private function localConcept(string $name): string
    {
        $name = trim($name);
        $pos = strrpos($name, ':');
        if ($pos !== false) {
            $name = substr($name, $pos + 1);
        }

        return $name;
    }

    private function looksLikeIxbrl(string $body): bool
    {
        return stripos($body, 'nonFraction') !== false || stripos($body, 'inlineXBRL') !== false;
    }

    private function textHasAccounts(string $text): bool
    {
        return preg_match('/turnover|fixed assets|current assets|net assets|net liabilities|creditors|balance sheet|profit/i', $text) === 1;
    }

    /**
     * @param list<array<string, mixed>> $filings
     * @return array{summary:string,periods:list<array<string,string|null>>,rows:list<array{label:string,values:list<string|null>}>,footnotes:list<string>}|null
     */
    private function readCache(string $companyNumber, array $filings): ?array
    {
        $path = $this->cachePath($companyNumber, $filings);
        if (!is_file($path) || filemtime($path) < time() - self::CACHE_TTL) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || !isset($decoded['rows'], $decoded['periods'], $decoded['summary'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param list<array<string, mixed>> $filings
     * @param array{summary:string,periods:array,rows:array,footnotes:array} $result
     */
    private function writeCache(string $companyNumber, array $filings, array $result): void
    {
        $path = $this->cachePath($companyNumber, $filings);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return;
        }

        @file_put_contents($path, json_encode($result), LOCK_EX);
    }

    /**
     * @param list<array<string, mixed>> $filings
     */
    private function cachePath(string $companyNumber, array $filings): string
    {
        $ids = [];
        foreach ($filings as $filing) {
            $ids[] = (string) ($filing['transaction_id'] ?? '') . ':' . (string) ($filing['made_up_date'] ?? '');
        }

        return sys_get_temp_dir() . '/dataindex-accounts/' . hash('sha256', $companyNumber . '|' . implode(',', $ids)) . '.json';
    }

    /**
     * @return array{summary:string,periods:list<array<string,string|null>>,rows:list<array{label:string,values:list<string|null>}>,footnotes:list<string>}
     */
    private function emptyResult(string $summary): array
    {
        return [
            'summary' => $summary,
            'periods' => [],
            'rows' => [],
            'footnotes' => [],
        ];
    }

    private function executable(string $name): ?string
    {
        if (!in_array($name, ['pdftotext', 'pdftoppm', 'tesseract'], true)) {
            return null;
        }

        $dirs = array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH')));
        foreach (array_merge($dirs, ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin']) as $dir) {
            $path = rtrim((string) $dir, '/') . '/' . $name;
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param list<string> $command
     */
    private function commandOutput(array $command, int $timeout = 30): ?string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $started = time();
        while (true) {
            $output .= (string) stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (time() - $started > $timeout) {
                proc_terminate($process);
                break;
            }
            usleep(100000);
        }

        $output .= (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $output;
    }

    private function makeTempDir(): ?string
    {
        $dir = sys_get_temp_dir() . '/dataindex-aa-' . bin2hex(random_bytes(8));
        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return null;
        }

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        $files = glob($dir . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}
