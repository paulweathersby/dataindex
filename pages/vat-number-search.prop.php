<?php
declare(strict_types=1);

$pageTitle       = 'VAT Number Search – Find a VAT number for UK companies';
$pageDescription = 'Search for VAT numbers belonging to UK registered companies.';
$canonical       = SITE_URL . 'vat-number-search';

$idata   = [];
$message = '';
$action  = '';

$dataOps = new dataOps($app);

if (isset($pathArray['vat_number'])) {
    $vat_number = $dataOps->normalizeVatNumber((string) $pathArray['vat_number']);
    $action     = 'search';
} else {
    $vat_number = '';
}

if ($action === 'search' && $vat_number !== '') {
    $api = new apiRequest($app);

    // Typeahead selects from the database; keep that row if HMRC is unavailable.
    $cached = $dataOps->loadVatFromDatabase($vat_number);
    $idata  = $dataOps->fetchVatRecord($vat_number, $api);

    if (!$idata && $cached) {
        $idata = $cached;
    }

    if (!$idata) {
        $message = 'A company matching the VAT number GB' . $vat_number . ' was not found.';
    }
}
