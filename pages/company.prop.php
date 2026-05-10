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

$psc_query = $app->query(
    "SELECT * FROM psc WHERE company_number = :cn AND ceased_on IS NULL",
    [':cn' => $company_number]
);

$fin_query = $app->query(
    "SELECT * FROM financials WHERE companyNumber = :cn",
    [':cn' => $company_number]
);

$api     = new apiRequest($app);
$dataOps = new dataOps($app);

$cdata = $api->getDirectors($comp_data['CompanyNumber']);
$jdata = json_decode((string) $cdata);
$idata = $dataOps->processOfficerResults($jdata);
$dataOps->saveOfficers($idata, $comp_data['CompanyNumber']);

$fidata = $api->getFiling($comp_data['CompanyNumber']);
$fdata  = $dataOps->processFilingResults(json_decode((string) $fidata));
$dataOps->saveFilings($fdata, $comp_data['CompanyNumber']);

// --- Page meta --------------------------------------------------------------

$pageTitle = $comp_data['CompanyName'] . ' ' . $comp_data['CompanyNumber'];
$canonical = SITE_URL . 'company/number/' . $comp_data['CompanyNumber'];

$place = $comp_data['RegAddress_Town'] !== '' ? $comp_data['RegAddress_Town'] : 'UK';
$pageDescription = 'Information about ' . $comp_data['CompanyName']
    . ' with registration number ' . $comp_data['CompanyNumber']
    . ' based in ' . $place
    . (isset($sic_data['description']) ? ' and operating in ' . $sic_data['description'] : '');
