<?php
/**
 * Summarises street-level crime within a quarter of a mile of a postcode.
 *
 * The figures come from data.police.uk for the latest published month.
 * A stored summary is shown for 24 hours, and when the API cannot be
 * reached. A verified crawler is only shown that stored summary.
 * Individual crimes are not stored.
 */

declare(strict_types=1);

class crimeReport
{
    /** @var app */
    private $app;

    /** @var apiRequest */
    private $api;

    private const CACHE_HOURS = 24;
    private const STREET_LIMIT = 15;
    /** The point search is fixed at one mile, so the area is drawn instead. */
    private const RADIUS_MILES = 0.25;
    private const RADIUS_LABEL = 'a quarter of a mile';

    /** @var array<string, string> */
    private const CATEGORIES = [
        'anti-social-behaviour' => 'Anti-social behaviour',
        'bicycle-theft' => 'Bicycle theft',
        'burglary' => 'Burglary',
        'criminal-damage-arson' => 'Criminal damage and arson',
        'drugs' => 'Drugs',
        'other-theft' => 'Other theft',
        'possession-of-weapons' => 'Possession of weapons',
        'public-order' => 'Public order',
        'robbery' => 'Robbery',
        'shoplifting' => 'Shoplifting',
        'theft-from-the-person' => 'Theft from the person',
        'vehicle-crime' => 'Vehicle crime',
        'violent-crime' => 'Violence and sexual offences',
        'other-crime' => 'Other crime',
    ];

    public function __construct($app, apiRequest $api)
    {
        $this->app = $app;
        $this->api = $api;
    }

    /**
     * @return array{
     *   status: string,
     *   month: string,
     *   total: int,
     *   categories: list<array{label:string,count:int}>,
     *   outcomes: list<array{label:string,count:int}>,
     *   streets: list<array{label:string,count:int}>,
     *   street_count: int,
     *   scotland: bool
     * }
     */
    public function forPostcode(string $postcode, float $latitude, float $longitude, string $country): array
    {
        $stored = $this->stored($postcode);
        if (visitorCheck::cacheOnly()) {
            return $stored !== null ? $this->present($stored['summary']) : $this->unavailable();
        }
        if ($stored !== null && $stored['fresh']) {
            return $this->present($stored['summary']);
        }

        $raw = $this->api->streetCrimes($latitude, $longitude, $this->areaPolygon($latitude, $longitude));
        if ($raw === null) {
            return $stored !== null ? $this->present($stored['summary']) : $this->unavailable();
        }

        $summary = $this->summarise($raw, $country);
        if ($summary === null) {
            return $stored !== null ? $this->present($stored['summary']) : $this->unavailable();
        }
        $this->save($postcode, $summary);

        return $this->present($summary);
    }

    /**
     * @return array{summary:array<string,mixed>,fresh:bool}|null
     */
    private function stored(string $postcode): ?array
    {
        try {
            $row = $this->app->query(
                'SELECT result_json, fetched_at >= NOW() - INTERVAL ' . self::CACHE_HOURS . ' HOUR AS fresh
                 FROM postcode_crimes
                 WHERE postcode = :postcode
                 LIMIT 1',
                [':postcode' => $postcode]
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('crimeReport stored: ' . $exception->getMessage());
            return null;
        }

        if (!$row) {
            return null;
        }

        $summary = json_decode((string) $row['result_json'], true);
        if (!is_array($summary)) {
            return null;
        }

        $radius = (float) ($summary['radius_miles'] ?? 0);

        return [
            'summary' => $summary,
            'fresh' => (int) ($row['fresh'] ?? 0) === 1 && abs($radius - self::RADIUS_MILES) < 0.001,
        ];
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function save(string $postcode, array $summary): void
    {
        try {
            $json = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            error_log('crimeReport save: ' . $exception->getMessage());
            return;
        }

        try {
            $this->app->query(
                'INSERT INTO postcode_crimes (postcode, result_json, fetched_at)
                 VALUES (:postcode, :json, NOW())
                 ON DUPLICATE KEY UPDATE result_json = VALUES(result_json), fetched_at = NOW()',
                [
                    ':postcode' => $postcode,
                    ':json' => $json,
                ]
            );
        } catch (Throwable $exception) {
            error_log('crimeReport save: ' . $exception->getMessage());
        }
    }

    /**
     * A circle of a quarter of a mile, drawn as a polygon the crime API accepts.
     * The point search cannot be smaller than one mile.
     */
    private function areaPolygon(float $latitude, float $longitude): string
    {
        $radius = self::RADIUS_MILES * 1609.344;
        $latRadians = deg2rad($latitude);
        $points = [];
        $steps = 16;
        for ($step = 0; $step < $steps; $step++) {
            $angle = (2 * M_PI * $step) / $steps;
            $latitudeOffset = rad2deg(($radius * cos($angle)) / 6371000);
            $longitudeScale = cos($latRadians);
            if (abs($longitudeScale) < 0.01) {
                $longitudeScale = $longitudeScale < 0 ? -0.01 : 0.01;
            }
            $longitudeOffset = rad2deg(($radius * sin($angle)) / (6371000 * $longitudeScale));
            $points[] = sprintf('%.5F,%.5F', $latitude + $latitudeOffset, $longitude + $longitudeOffset);
        }

        return implode(':', $points);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summarise(string $raw, string $country): ?array
    {
        $crimes = json_decode($raw, true);
        if (!is_array($crimes)) {
            return null;
        }

        $categories = [];
        $outcomes = [];
        $streets = [];
        $month = '';
        $total = 0;
        foreach ($crimes as $crime) {
            if (!is_array($crime)) {
                continue;
            }
            $total++;
            $recorded = $this->month((string) ($crime['month'] ?? ''));
            if ($recorded !== '' && ($month === '' || strcmp($recorded, $month) > 0)) {
                $month = $recorded;
            }

            $category = $this->categoryLabel((string) ($crime['category'] ?? ''));
            $categories[$category] = ($categories[$category] ?? 0) + 1;

            $outcome = 'No outcome recorded';
            if (isset($crime['outcome_status']) && is_array($crime['outcome_status'])) {
                $label = trim((string) ($crime['outcome_status']['category'] ?? ''));
                if ($label !== '') {
                    $outcome = $label;
                }
            }
            $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;

            $street = '';
            if (isset($crime['location']) && is_array($crime['location'])
                && isset($crime['location']['street']) && is_array($crime['location']['street'])) {
                $street = trim((string) ($crime['location']['street']['name'] ?? ''));
            }
            if ($street === '') {
                $street = 'Location not recorded';
            }
            $street = function_exists('mb_substr') ? mb_substr($street, 0, 120) : substr($street, 0, 120);
            $streets[$street] = ($streets[$street] ?? 0) + 1;
        }

        $streetRows = $this->ranked($streets);

        return [
            'month' => $month,
            'month_label' => $this->monthLabel($month),
            'total' => $total,
            'categories' => $this->ranked($categories),
            'outcomes' => $this->ranked($outcomes),
            'streets' => array_slice($streetRows, 0, self::STREET_LIMIT),
            'street_count' => count($streetRows),
            'scotland' => strcasecmp(trim($country), 'Scotland') === 0,
            'radius_miles' => self::RADIUS_MILES,
            'radius_label' => self::RADIUS_LABEL,
        ];
    }

    /**
     * @param array<string, int> $counts
     * @return list<array{label:string,count:int}>
     */
    private function ranked(array $counts): array
    {
        $rows = [];
        foreach ($counts as $label => $count) {
            $rows[] = ['label' => (string) $label, 'count' => (int) $count];
        }
        usort($rows, function (array $a, array $b): int {
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }

            return strcasecmp($a['label'], $b['label']);
        });

        return $rows;
    }

    private function categoryLabel(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return 'Other crime';
        }
        if (isset(self::CATEGORIES[$slug])) {
            return self::CATEGORIES[$slug];
        }

        return ucfirst(str_replace('-', ' ', $slug));
    }

    private function month(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private function monthLabel(string $month): string
    {
        if ($month === '') {
            return '';
        }
        $parsed = DateTime::createFromFormat('!Y-m', $month);

        return $parsed instanceof DateTime ? $parsed->format('F Y') : $month;
    }

    /**
     * @param array<string, mixed> $summary
     * @return array{
     *   status: string,
     *   month: string,
     *   total: int,
     *   categories: list<array{label:string,count:int}>,
     *   outcomes: list<array{label:string,count:int}>,
     *   streets: list<array{label:string,count:int}>,
     *   street_count: int,
     *   scotland: bool
     * }
     */
    private function present(array $summary): array
    {
        $total = max(0, (int) ($summary['total'] ?? 0));

        return [
            'status' => $total > 0 ? 'ready' : 'empty',
            'month' => trim((string) ($summary['month_label'] ?? '')),
            'total' => $total,
            'categories' => $this->rows($summary['categories'] ?? []),
            'outcomes' => $this->rows($summary['outcomes'] ?? []),
            'streets' => $this->rows($summary['streets'] ?? []),
            'street_count' => max(0, (int) ($summary['street_count'] ?? 0)),
            'scotland' => !empty($summary['scotland']),
            'radius' => trim((string) ($summary['radius_label'] ?? '')) ?: 'about a mile',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array{label:string,count:int}>
     */
    private function rows($rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $count = (int) ($row['count'] ?? 0);
            if ($label === '' || $count < 1) {
                continue;
            }
            $clean[] = ['label' => $label, 'count' => $count];
        }

        return $clean;
    }

    /**
     * @return array{
     *   status: string,
     *   month: string,
     *   total: int,
     *   categories: list<array{label:string,count:int}>,
     *   outcomes: list<array{label:string,count:int}>,
     *   streets: list<array{label:string,count:int}>,
     *   street_count: int,
     *   scotland: bool
     * }
     */
    private function unavailable(): array
    {
        return [
            'status' => 'unavailable',
            'month' => '',
            'total' => 0,
            'categories' => [],
            'outcomes' => [],
            'streets' => [],
            'street_count' => 0,
            'scotland' => false,
            'radius' => self::RADIUS_LABEL,
        ];
    }
}
