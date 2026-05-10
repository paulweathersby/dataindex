<?php
declare(strict_types=1);

$sector = preg_replace('/[^A-Za-z]/', '', (string) ($pathArray['sector'] ?? ''));
$sector = strtoupper(substr($sector, 0, 1));

$sector_data = $app->query(
    "SELECT * FROM sic_industries WHERE sector = :sector LIMIT 1",
    [':sector' => $sector]
)->fetch(PDO::FETCH_ASSOC) ?: [];

$sic_query = $app->query(
    "SELECT * FROM sic_codes WHERE section = :sector ORDER BY code",
    [':sector' => $sector]
);

$comp_query = $app->query(
    "SELECT c.CompanyNumber, c.CompanyName
     FROM companies c
     JOIN sic_code_to_company sc ON c.CompanyNumber = sc.company_number
     JOIN sic_codes s ON sc.sic_code = s.code
     WHERE c.Accounts_AccountCategory = 'FULL'
       AND s.section = :sector
       AND c.CompanyStatus = 'Active'
     LIMIT 10",
    [':sector' => $sector]
);

$pageTitle = ($sector_data['name'] ?? 'Industry') . ' Data Index';
$canonical = SITE_URL . 'industry/sector/' . strtolower((string) ($sector_data['sector'] ?? $sector))
    . '/' . ($sector_data['slug'] ?? '') . '/';
$pageDescription = 'Find UK companies in ' . ($sector_data['name'] ?? '')
    . ', Standard Industrial Classification (SIC) sector ' . ($sector_data['sector'] ?? $sector) . '.';
