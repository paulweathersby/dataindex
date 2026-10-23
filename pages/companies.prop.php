<?php
declare(strict_types=1);

$code = substr(preg_replace('/[^0-9]/', '', (string) ($pathArray['code'] ?? '')) ?? '', 0, 5);
$town = trim(str_replace('-', ' ', (string) ($pathArray['town'] ?? '')));

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

$town_data = $app->query(
    "SELECT town FROM town_outcode WHERE town = :town LIMIT 1",
    [':town' => $town]
)->fetchColumn();

$comp_query = $app->query(
    "SELECT c.CompanyNumber, c.CompanyName
     FROM companies c
     JOIN sic_code_to_company sc ON c.CompanyNumber = sc.company_number
     WHERE sc.sic_code = :code
       AND c.town = :town
       AND c.CompanyStatus = 'Active'
     ORDER BY c.CompanyName",
    [':code' => $code, ':town' => $town]
);

$pageTitle = ($town_data ?: $town) . ' - ' . ($sector_data['description'] ?? '');
$canonical = SITE_URL . 'companies/code/' . ($sector_data['code'] ?? '') . '/town/' . rawurlencode((string) $town_data);
$pageDescription = 'Find UK companies in ' . ucwords(strtolower((string) $town_data))
    . ' that operate in ' . ($section_data['name'] ?? '')
    . ', ' . ($sector_data['description'] ?? '') . '.';
