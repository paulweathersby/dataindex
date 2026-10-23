<?php
declare(strict_types=1);

$company_number = preg_replace('/[^A-Za-z0-9]/', '', (string) ($pathArray['number'] ?? ''));

if ($company_number === '') {
    header('Location: ' . SITE_URL . '404', true, 302);
    exit;
}

$comp_data = $app->query(
    "SELECT * FROM companies WHERE CompanyNumber = :cn LIMIT 1",
    [':cn' => $company_number]
)->fetch(PDO::FETCH_ASSOC);

if (!$comp_data || empty($comp_data['CompanyNumber'])) {
    header('HTTP/1.0 404 Not Found');
    header('Location: ' . SITE_URL . '404', true, 301);
    exit;
}

$sic_data = $app->query(
    "SELECT s.*, sc.company_number
     FROM sic_codes s
     JOIN sic_code_to_company sc ON s.code = sc.sic_code
     WHERE sc.company_number = :cn
     LIMIT 1",
    [':cn' => $company_number]
)->fetch(PDO::FETCH_ASSOC) ?: [];

$sector_data = [];
if (!empty($sic_data['section'])) {
    $sector_data = $app->query(
        "SELECT * FROM sic_industries WHERE sector = :sector LIMIT 1",
        [':sector' => $sic_data['section']]
    )->fetch(PDO::FETCH_ASSOC) ?: [];
}

$fin_query = $app->query(
    "SELECT * FROM financials WHERE companyNumber = :cn",
    [':cn' => $company_number]
);

$api     = new apiRequest($app);
$dataOps = new dataOps($app);

$fdata = $dataOps->fetchFilingsForCompany($comp_data['CompanyNumber'], $api);

// --- Page meta --------------------------------------------------------------

$companyName   = (string) ($comp_data['CompanyName'] ?? '');
$companyNumber = (string) ($comp_data['CompanyNumber'] ?? '');
$pageTitle = trim($companyName . ' ' . $companyNumber);
$canonical = SITE_URL . 'company/number/' . rawurlencode($companyNumber);

$town  = trim((string) ($comp_data['RegAddress_Town'] ?? ''));
$place = $town !== '' ? $town : 'UK';
$sicDescription = trim((string) ($sic_data['description'] ?? ''));
$pageDescription = 'Information about ' . $companyName
    . ' with registration number ' . $companyNumber
    . ' based in ' . $place
    . ($sicDescription !== '' ? ' and operating in ' . $sicDescription : '');
