<?php
declare(strict_types=1);

$code = substr(preg_replace('/[^0-9]/', '', (string) ($pathArray['code'] ?? '')) ?? '', 0, 5);

$sector_data = $app->query(
    "SELECT * FROM sic_codes WHERE code = :code LIMIT 1",
    [':code' => $code]
)->fetch(PDO::FETCH_ASSOC) ?: [];

$section_data = [];
if (!empty($sector_data['section'])) {
    $section_data = $app->query(
        "SELECT * FROM sic_industries WHERE sector = :sector LIMIT 1",
        [':sector' => $sector_data['section']]
    )->fetch(PDO::FETCH_ASSOC) ?: [];
}

$code_query = $app->query(
    "SELECT DISTINCT c.town AS town
     FROM sic_code_to_company s
     JOIN companies c ON c.CompanyNumber = s.company_number
     WHERE s.sic_code = :code
       AND c.town <> ''
     ORDER BY town",
    [':code' => $code]
);

$comp_query = $app->query(
    "SELECT c.CompanyNumber, c.CompanyName
     FROM companies c
     JOIN sic_code_to_company sc ON c.CompanyNumber = sc.company_number
     WHERE c.Accounts_AccountCategory = 'FULL'
       AND sc.sic_code = :code
       AND c.CompanyStatus = 'Active'
     LIMIT 10",
    [':code' => $code]
);

$pageTitle = ($sector_data['description'] ?? 'SIC code') . ' - Data Index';
$canonical = SITE_URL . 'sic_code/code/' . ($sector_data['code'] ?? '') . '/' . ($sector_data['slug'] ?? '') . '/';
$pageDescription = 'Find UK companies in ' . ($sector_data['description'] ?? '')
    . ', Standard Industrial Classification (SIC) code ' . ($sector_data['code'] ?? $code) . '.';
