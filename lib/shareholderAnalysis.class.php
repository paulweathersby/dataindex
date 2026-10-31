<?php
/**
 * Reads the current shareholders from Companies House share filings.
 *
 * Forms used: IN01, CS01, AR01, 363, 363a, SH01, SH02, SH03, SH04, SH05,
 * SH06, SH07, SH09 and SH19. OpenAI reads those documents. If they do not
 * name the holders, the persons-with-significant-control API is used.
 * The result is stored and reused until a newer relevant filing appears.
 * The documents themselves are not stored.
 */

declare(strict_types=1);

class shareholderAnalysis
{
    /** @var app */
    private $app;

    /** @var apiRequest */
    private $api;

    private const SNAPSHOT_TYPES = ['IN01', 'CS01', 'AR01', '363', '363A'];
    private const CHANGE_TYPES = ['SH01', 'SH02', 'SH03', 'SH04', 'SH05', 'SH06', 'SH07', 'SH09', 'SH19'];
    private const MAX_DOCUMENTS = 5;

    /** @var array<string, int> */
    private const API_CATEGORIES = [
        'confirmation-statement' => 25,
        'annual-return' => 15,
        'capital' => 25,
        'incorporation' => 5,
    ];

    public function __construct($app, apiRequest $api)
    {
        $this->app = $app;
        $this->api = $api;
    }

    /**
     * @return array{
     *   summary: string,
     *   cached: bool,
     *   source: string,
     *   holders: list<array<string, string>>
     * }
     */
    public function analyse(string $companyNumber): array
    {
        $this->companyNumber = $companyNumber;
        $filings = $this->relevantFilings($companyNumber);
        $marker = $this->marker($filings);
        $stored = $this->storedResult($companyNumber, $marker);
        if ($stored !== null) {
            return $stored;
        }

        $chosen = $this->chooseFilings($filings);
        $documents = $this->readDocuments($companyNumber, $chosen);
        $readable = 0;
        foreach ($documents as $document) {
            if ($document['text'] !== '' || $document['pdf'] !== '' || $document['images'] !== []) {
                $readable++;
            }
        }

        $parsed = null;
        if ($readable > 0 && $this->openAiEnabled()) {
            $parsed = $this->askOpenAi($documents, true);
            if ($parsed === null) {
                $parsed = $this->askOpenAi($documents, false);
            }
        }

        if (is_array($parsed) && ($parsed['found'] ?? false) === true) {
            $holders = $this->normaliseHolders($parsed['holders'], $this->latestSource($chosen));
            if ($holders !== []) {
                $summary = 'Shareholders read from the latest share filings at Companies House.';
                $this->save($companyNumber, $marker, 'documents', $summary, $holders);

                return $this->present('documents', $summary, $holders, false);
            }
        }

        $documentsRead = is_array($parsed);
        $psc = $this->pscHolders();
        if ($psc === null && !$documentsRead) {
            return $this->present(
                'none',
                $readable === 0
                    ? 'The share filings could not be downloaded from Companies House. Please try again in a few minutes.'
                    : 'The share filings could not be read. Please try again in a few minutes.',
                [],
                false
            );
        }

        $holders = $psc ?? [];
        if ($holders !== []) {
            $summary = $documentsRead
                ? 'The filings do not list the shareholders. These are the current people with significant control from Companies House.'
                : 'The filings could not be read, so these are the current people with significant control from Companies House.';
            $this->save($companyNumber, $marker, 'psc', $summary, $holders);

            return $this->present('psc', $summary, $holders, false);
        }

        $summary = 'No shareholder list was found in the filings, and Companies House has no current person with significant control.';
        $this->save($companyNumber, $marker, 'none', $summary, []);

        return $this->present('none', $summary, [], false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function relevantFilings(string $companyNumber): array
    {
        $merged = [];
        foreach ($this->databaseFilings($companyNumber) as $row) {
            $merged[$this->filingKey($row)] = $row;
        }

        foreach (self::API_CATEGORIES as $category => $items) {
            $raw = $this->api->getCategorisedFilingHistory($companyNumber, $category, $items);
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $decoded = json_decode($raw);
            if (!is_object($decoded) || !isset($decoded->items) || !is_array($decoded->items)) {
                continue;
            }
            foreach ($decoded->items as $item) {
                $row = $this->filingFromApi($item);
                if ($row === null) {
                    continue;
                }
                $merged[$this->filingKey($row)] = $row;
            }
        }

        $rows = array_values($merged);
        usort($rows, function (array $a, array $b): int {
            return strcmp((string) $b['date'], (string) $a['date']);
        });

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function databaseFilings(string $companyNumber): array
    {
        $types = array_merge(self::SNAPSHOT_TYPES, self::CHANGE_TYPES);
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        try {
            $stmt = $this->app->db->prepare(
                'SELECT date, type, description, link_self, link_metadata, transaction_id
                 FROM filings
                 WHERE company_number = ? AND UPPER(type) IN (' . $placeholders . ')
                 ORDER BY date DESC
                 LIMIT 50'
            );
            $stmt->execute(array_merge([$companyNumber], $types));
        } catch (Throwable $exception) {
            error_log('shareholderAnalysis filings: ' . $exception->getMessage());
            return [];
        }

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = $this->normaliseType((string) ($row['type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $rows[] = [
                'date' => $this->dateOrEmpty($row['date'] ?? ''),
                'type' => $type,
                'description' => (string) ($row['description'] ?? ''),
                'link_self' => (string) ($row['link_self'] ?? ''),
                'link_metadata' => (string) ($row['link_metadata'] ?? ''),
                'transaction_id' => (string) ($row['transaction_id'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function filingFromApi($item): ?array
    {
        if (!is_object($item)) {
            return null;
        }

        $type = $this->normaliseType((string) ($item->type ?? ''));
        if ($type === '') {
            return null;
        }

        $metadata = '';
        if (isset($item->links) && is_object($item->links)) {
            $metadata = (string) ($item->links->document_metadata ?? $item->links->metadata ?? '');
        }

        return [
            'date' => $this->dateOrEmpty($item->date ?? ''),
            'type' => $type,
            'description' => (string) ($item->description ?? ''),
            'link_self' => isset($item->links) && is_object($item->links) ? (string) ($item->links->self ?? '') : '',
            'link_metadata' => $metadata,
            'transaction_id' => (string) ($item->transaction_id ?? ''),
        ];
    }

    /**
     * @param list<array<string, mixed>> $filings
     * @return list<array<string, mixed>>
     */
    private function chooseFilings(array $filings): array
    {
        $snapshot = null;
        foreach ($filings as $filing) {
            if (in_array($filing['type'], self::SNAPSHOT_TYPES, true)) {
                $snapshot = $filing;
                break;
            }
        }

        $chosen = [];
        if ($snapshot !== null) {
            $chosen[] = $snapshot;
            $snapshotDate = (string) $snapshot['date'];
            $changes = 0;
            foreach ($filings as $filing) {
                if (!in_array($filing['type'], self::CHANGE_TYPES, true)) {
                    continue;
                }
                if ($snapshotDate !== '' && strcmp((string) $filing['date'], $snapshotDate) < 0) {
                    continue;
                }
                $chosen[] = $filing;
                $changes++;
                if ($changes >= self::MAX_DOCUMENTS - 1) {
                    break;
                }
            }
        } else {
            $chosen = array_slice($filings, 0, self::MAX_DOCUMENTS);
        }

        usort($chosen, function (array $a, array $b): int {
            return strcmp((string) $a['date'], (string) $b['date']);
        });

        return $chosen;
    }

    /**
     * @param list<array<string, mixed>> $filings
     * @return list<array{type:string,date:string,text:string,pdf:string,images:list<string>}>
     */
    private function readDocuments(string $companyNumber, array $filings): array
    {
        $documents = [];
        foreach ($filings as $filing) {
            $text = '';
            $pdf = '';
            $metadata = trim((string) ($filing['link_metadata'] ?? ''));
            if ($metadata === '') {
                $metadata = $this->metadataUrlFromTransaction($companyNumber, (string) ($filing['transaction_id'] ?? ''));
            }
            $downloaded = $metadata !== '' ? $this->downloadDocument($metadata) : null;
            if ($downloaded !== null && $downloaded['kind'] === 'ixbrl') {
                $text = $this->plainText($downloaded['body']);
            } elseif ($downloaded !== null && $downloaded['kind'] === 'pdf') {
                $pdf = $downloaded['body'];
                $text = $this->textFromPdf($pdf);
            }

            $documents[] = [
                'type' => (string) $filing['type'],
                'date' => (string) $filing['date'],
                'text' => $text,
                'pdf' => $pdf,
                'images' => [],
            ];
        }

        return $documents;
    }

    /**
     * @param list<array{type:string,date:string,text:string,pdf:string,images:list<string>}> $documents
     * @return array{found:bool,holders:list<array<string,mixed>>}|null
     */
    private function askOpenAi(array $documents, bool $sendPdf): ?array
    {
        $content = [[
            'type' => 'text',
            'text' => 'These UK Companies House filings are in date order, oldest first. '
                . 'IN01, CS01, AR01, 363 and 363a are snapshots of the members. '
                . 'SH01, SH02, SH03, SH04, SH05, SH06, SH07, SH09 and SH19 change the share capital after that snapshot. '
                . 'List the shareholders after the latest document. Use null when a number is not printed. '
                . 'Do not invent a holder. If the documents do not list the holders, set found to false and return no holders.',
        ]];
        $included = 0;
        foreach ($documents as $document) {
            $label = 'Form ' . $document['type'] . ' filed ' . ($document['date'] !== '' ? $document['date'] : 'on an unknown date');
            $pdf = (string) ($document['pdf'] ?? '');
            if ($sendPdf && $pdf !== '' && strlen($pdf) <= 8000000) {
                $content[] = ['type' => 'text', 'text' => $label . '. The next item is the filed PDF.'];
                $content[] = [
                    'type' => 'file',
                    'file' => [
                        'filename' => strtolower($document['type']) . '.pdf',
                        'file_data' => 'data:application/pdf;base64,' . base64_encode($pdf),
                    ],
                ];
                $included++;
                continue;
            }

            $text = trim($document['text']);
            if (strlen($text) > 12000) {
                $text = substr($text, 0, 12000);
            }
            if ($text !== '') {
                $content[] = ['type' => 'text', 'text' => $label . "\n\n" . $text];
                $included++;
            }
            $images = $document['images'];
            if ($images === [] && $pdf !== '' && strlen($text) < 400) {
                $images = $this->pageImages($pdf);
            }
            foreach ($images as $image) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $image, 'detail' => 'high'],
                ];
                $included++;
            }
        }
        if ($included === 0) {
            return null;
        }

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['found', 'holders'],
            'properties' => [
                'found' => ['type' => 'boolean'],
                'holders' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'share_class', 'shares', 'percentage'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'share_class' => ['type' => 'string'],
                            'shares' => ['type' => ['number', 'null']],
                            'percentage' => ['type' => ['number', 'null']],
                        ],
                    ],
                ],
            ],
        ];

        $parsed = $this->api->openAiJson([
            [
                'role' => 'system',
                'content' => 'You extract the current shareholders from UK company filings. Return only holders named in the documents.',
            ],
            ['role' => 'user', 'content' => $content],
        ], $schema, 'shareholders', 2000);

        if (!is_array($parsed) || !is_bool($parsed['found'] ?? null) || !is_array($parsed['holders'] ?? null)) {
            return null;
        }

        return ['found' => $parsed['found'], 'holders' => $parsed['holders']];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function pscHolders(): ?array
    {
        $companyNumber = $this->companyNumber;
        if ($companyNumber === null) {
            return null;
        }

        $raw = $this->api->getPersonsWithSignificantControl($companyNumber);
        if (!is_string($raw) || $raw === '') {
            return $this->api->lastStatus() === 404 ? [] : null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $items = $decoded['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $holders = [];
        foreach ($items as $item) {
            if (!is_array($item) || !empty($item['ceased_on'])) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '' && isset($item['name_elements']) && is_array($item['name_elements'])) {
                $name = trim(implode(' ', array_filter([
                    (string) ($item['name_elements']['forename'] ?? ''),
                    (string) ($item['name_elements']['surname'] ?? ''),
                ], static function (string $part): bool {
                    return $part !== '';
                })));
            }
            if ($name === '') {
                $name = 'Protected person';
            }
            $notes = [];
            foreach (($item['natures_of_control'] ?? []) as $nature) {
                if (is_string($nature) && $nature !== '') {
                    $notes[] = $this->natureLabel($nature);
                }
            }
            $holders[] = [
                'name' => $name,
                'share_class' => '',
                'shares' => null,
                'percentage' => null,
                'note' => implode('; ', array_slice($notes, 0, 3)),
                'source_type' => 'PSC',
                'source_date' => $this->dateOrEmpty($item['notified_on'] ?? ''),
            ];
            if (count($holders) >= 40) {
                break;
            }
        }

        return $holders;
    }

    /** @var string|null */
    private $companyNumber;

    /**
     * @param list<array<string, mixed>> $holders
     * @return list<array<string, mixed>>
     */
    private function normaliseHolders(array $holders, array $source): array
    {
        $clean = [];
        foreach ($holders as $holder) {
            if (!is_array($holder)) {
                continue;
            }
            $name = trim(preg_replace('/\s+/', ' ', (string) ($holder['name'] ?? '')) ?? '');
            if (strlen($name) < 2 || strlen($name) > 255) {
                continue;
            }
            $shares = $holder['shares'] ?? null;
            $percentage = $holder['percentage'] ?? null;
            $clean[] = [
                'name' => $name,
                'share_class' => substr(trim((string) ($holder['share_class'] ?? '')), 0, 80),
                'shares' => is_numeric($shares) && (float) $shares >= 0 && (float) $shares < 1e16 ? (float) $shares : null,
                'percentage' => is_numeric($percentage) && (float) $percentage >= 0 && (float) $percentage <= 100 ? (float) $percentage : null,
                'note' => substr(trim((string) ($holder['note'] ?? '')), 0, 160),
                'source_type' => (string) ($source['type'] ?? 'PSC'),
                'source_date' => (string) ($source['date'] ?? ''),
            ];
            if (count($clean) >= 40) {
                break;
            }
        }

        return $clean;
    }

    /**
     * @param list<array<string, mixed>> $filings
     * @return array{type:string,date:string}
     */
    private function latestSource(array $filings): array
    {
        $latest = ['type' => '', 'date' => ''];
        foreach ($filings as $filing) {
            if (strcmp((string) $filing['date'], $latest['date']) >= 0) {
                $latest = ['type' => (string) $filing['type'], 'date' => (string) $filing['date']];
            }
        }

        return $latest;
    }

    /**
     * @param list<array<string, mixed>> $filings
     */
    private function marker(array $filings): string
    {
        $parts = [];
        foreach ($filings as $filing) {
            $parts[] = $filing['type'] . '|' . $filing['date'] . '|' . $filing['transaction_id'];
        }
        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @return array{summary:string,cached:bool,source:string,holders:list<array<string,string>>}|null
     */
    private function storedResult(string $companyNumber, string $marker): ?array
    {
        try {
            $snapshot = $this->app->query(
                'SELECT source, summary, processed_at FROM shareholder_snapshots
                 WHERE company_number = :cn AND filing_marker = :marker LIMIT 1',
                [':cn' => $companyNumber, ':marker' => $marker]
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('shareholderAnalysis snapshot: ' . $exception->getMessage());
            return null;
        }

        if (!$snapshot) {
            return null;
        }

        try {
            $stmt = $this->app->query(
                'SELECT holder_name, share_class, shares, percentage, holding_note, source_type, source_date
                 FROM shareholders
                 WHERE company_number = :cn
                 ORDER BY sort_order ASC, id ASC',
                [':cn' => $companyNumber]
            );
        } catch (Throwable $exception) {
            error_log('shareholderAnalysis load: ' . $exception->getMessage());
            return null;
        }

        $holders = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $holders[] = [
                'name' => (string) $row['holder_name'],
                'share_class' => (string) $row['share_class'],
                'shares' => $row['shares'] === null ? null : (float) $row['shares'],
                'percentage' => $row['percentage'] === null ? null : (float) $row['percentage'],
                'note' => (string) $row['holding_note'],
                'source_type' => (string) $row['source_type'],
                'source_date' => $this->dateOrEmpty($row['source_date'] ?? ''),
            ];
        }

        $processed = $this->dateOrEmpty($snapshot['processed_at'] ?? '');
        $summary = 'No newer share filing since this was last read'
            . ($processed !== '' ? ' on ' . $this->formatDate($processed) : '')
            . '. ' . (string) $snapshot['summary'];

        return $this->present((string) $snapshot['source'], $summary, $holders, true);
    }

    /**
     * @param list<array<string, mixed>> $holders
     */
    private function save(string $companyNumber, string $marker, string $source, string $summary, array $holders): void
    {
        $this->app->db->beginTransaction();
        try {
            $this->app->db->prepare(
                'INSERT INTO shareholder_snapshots (company_number, filing_marker, source, summary, processed_at)
                 VALUES (:cn, :marker, :source, :summary, NOW())
                 ON DUPLICATE KEY UPDATE filing_marker = VALUES(filing_marker), source = VALUES(source),
                    summary = VALUES(summary), processed_at = NOW()'
            )->execute([
                ':cn' => $companyNumber,
                ':marker' => $marker,
                ':source' => $source,
                ':summary' => substr($summary, 0, 500),
            ]);

            $this->app->db->prepare('DELETE FROM shareholders WHERE company_number = ?')->execute([$companyNumber]);
            $insert = $this->app->db->prepare(
                'INSERT INTO shareholders
                    (company_number, holder_name, share_class, shares, percentage, holding_note, source_type, source_date, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($holders as $index => $holder) {
                $date = $this->dateOrEmpty($holder['source_date'] ?? '');
                $insert->execute([
                    $companyNumber,
                    $holder['name'],
                    (string) ($holder['share_class'] ?? ''),
                    $holder['shares'],
                    $holder['percentage'],
                    substr((string) ($holder['note'] ?? ''), 0, 160),
                    substr((string) ($holder['source_type'] ?? ''), 0, 8),
                    $date !== '' ? $date : null,
                    $index,
                ]);
            }
            $this->app->db->commit();
        } catch (Throwable $exception) {
            $this->app->db->rollBack();
            error_log('shareholderAnalysis save: ' . $exception->getMessage());
        }
    }

    /**
     * @param list<array<string, mixed>> $holders
     * @return array{summary:string,cached:bool,source:string,holders:list<array<string,string>>}
     */
    private function present(string $source, string $summary, array $holders, bool $cached): array
    {
        $rows = [];
        foreach ($holders as $holder) {
            $shares = $holder['shares'];
            $percentage = $holder['percentage'];
            $rows[] = [
                'name' => (string) $holder['name'],
                'share_class' => (string) ($holder['share_class'] ?? ''),
                'shares' => is_numeric($shares) ? $this->formatNumber((float) $shares) : '',
                'percentage' => is_numeric($percentage) ? rtrim(rtrim(number_format((float) $percentage, 2, '.', ''), '0'), '.') . '%' : '',
                'note' => (string) ($holder['note'] ?? ''),
                'source' => (string) ($holder['source_type'] ?? ''),
                'date' => $this->formatDate($this->dateOrEmpty($holder['source_date'] ?? '')),
            ];
        }

        return [
            'summary' => $summary,
            'cached' => $cached,
            'source' => $source,
            'holders' => $rows,
        ];
    }

    private function natureLabel(string $nature): string
    {
        $labels = [
            'ownership-of-shares-25-to-50-percent' => 'Owns 25% to 50% of the shares',
            'ownership-of-shares-50-to-75-percent' => 'Owns 50% to 75% of the shares',
            'ownership-of-shares-75-to-100-percent' => 'Owns 75% to 100% of the shares',
            'voting-rights-25-to-50-percent' => 'Holds 25% to 50% of the voting rights',
            'voting-rights-50-to-75-percent' => 'Holds 50% to 75% of the voting rights',
            'voting-rights-75-to-100-percent' => 'Holds 75% to 100% of the voting rights',
            'right-to-appoint-and-remove-directors' => 'Can appoint and remove directors',
            'significant-influence-or-control' => 'Has significant influence or control',
        ];

        return $labels[$nature] ?? ucfirst(str_replace('-', ' ', $nature));
    }

    private function normaliseType(string $type): string
    {
        $type = strtoupper(trim($type));
        $allowed = array_merge(self::SNAPSHOT_TYPES, self::CHANGE_TYPES);

        return in_array($type, $allowed, true) ? $type : '';
    }

    private function filingKey(array $row): string
    {
        $id = (string) ($row['transaction_id'] ?? '');

        return $id !== '' ? $id : $row['type'] . '|' . $row['date'];
    }

    private function openAiEnabled(): bool
    {
        if (!defined('OPENAI_API_KEY')) {
            return false;
        }
        $key = trim(OPENAI_API_KEY);

        return $key !== '' && $key !== 'CHANGE_ME';
    }

    private function downloadDocument(string $metadataUrl): ?array
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
        if ($resources === [] || in_array('application/xhtml+xml', $resources, true)) {
            $xhtml = $this->api->fetchCompaniesHouseDocument($contentUrl, 'application/xhtml+xml');
            if (is_array($xhtml) && $xhtml['status'] === 200 && stripos($xhtml['body'], 'ix:') !== false) {
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
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $transactionId)) {
            return '';
        }
        $raw = $this->api->getFilingItem($companyNumber, $transactionId);
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        $decoded = json_decode($raw);
        if (!is_object($decoded) || !isset($decoded->links) || !is_object($decoded->links)) {
            return '';
        }

        return $this->normaliseDocumentUrl((string) ($decoded->links->document_metadata ?? '')) ?? '';
    }

    private function normaliseDocumentUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts)) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '');
        if (str_ends_with($path, '/content')) {
            $path = substr($path, 0, -8);
        }
        if (($parts['scheme'] ?? '') !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'document-api.company-information.service.gov.uk'
            || !preg_match('#^/document/[A-Za-z0-9_-]+$#', $path)
        ) {
            return null;
        }

        return 'https://document-api.company-information.service.gov.uk' . $path;
    }

    private function plainText(string $xml): string
    {
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $xml) ?? $xml;
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);

        return strlen($text) > 12000 ? substr($text, 0, 12000) : $text;
    }

    private function textFromPdf(string $pdf): string
    {
        $pdftotext = $this->executable('pdftotext');
        if ($pdftotext === null) {
            return '';
        }
        $dir = $this->makeTempDir();
        if ($dir === null) {
            return '';
        }
        try {
            $path = $dir . '/filing.pdf';
            file_put_contents($path, $pdf);
            $text = $this->commandOutput([$pdftotext, '-layout', '-f', '1', '-l', '8', $path, '-']) ?? '';
        } finally {
            $this->removeDir($dir);
        }

        return trim($text);
    }

    /**
     * @return list<string>
     */
    private function pageImages(string $pdf): array
    {
        if (!$this->openAiEnabled()) {
            return [];
        }
        $pdftoppm = $this->executable('pdftoppm');
        if ($pdftoppm === null) {
            return [];
        }
        $dir = $this->makeTempDir();
        if ($dir === null) {
            return [];
        }
        $images = [];
        try {
            $path = $dir . '/filing.pdf';
            file_put_contents($path, $pdf);
            $this->commandOutput([$pdftoppm, '-png', '-r', '120', '-f', '1', '-l', '2', $path, $dir . '/page'], 40);
            $pages = glob($dir . '/page-*.png') ?: [];
            natsort($pages);
            foreach (array_slice(array_values($pages), 0, 2) as $page) {
                $url = $this->imageDataUrl($page);
                if ($url !== null) {
                    $images[] = $url;
                }
            }
        } finally {
            $this->removeDir($dir);
        }

        return $images;
    }

    private function imageDataUrl(string $pngPath): ?string
    {
        if (!function_exists('imagecreatefrompng')) {
            return null;
        }
        $image = @imagecreatefrompng($pngPath);
        if (!$image) {
            return null;
        }
        ob_start();
        imagejpeg($image, null, 70);
        $bytes = ob_get_clean();
        imagedestroy($image);
        if (!is_string($bytes) || $bytes === '') {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($bytes);
    }

    private function executable(string $name): ?string
    {
        if (!in_array($name, ['pdftotext', 'pdftoppm'], true)) {
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
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
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
        $dir = sys_get_temp_dir() . '/dataindex-sh-' . bin2hex(random_bytes(8));
        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return null;
        }

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }

    private function dateOrEmpty($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }
        $date = date_create($value);

        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : '';
    }

    private function formatDate(string $iso): string
    {
        if ($iso === '') {
            return '';
        }
        $date = date_create($iso);

        return $date instanceof DateTimeInterface ? $date->format('j M Y') : $iso;
    }

    private function formatNumber(float $value): string
    {
        $decimals = abs($value - round($value)) < 0.0001 ? 0 : 2;

        return number_format($value, $decimals, '.', ',');
    }
}
