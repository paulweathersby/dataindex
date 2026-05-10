<?php
declare(strict_types=1);

$pageTitle       = 'VAT Number Search – Find a VAT number for UK companies';
$pageDescription = 'Search for VAT numbers belonging to UK registered companies.';
$canonical       = SITE_URL . 'vat-number-search';

$idata   = [];
$message = '';
$action  = '';

if (isset($pathArray['vat_number'])) {
    $vat_number = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $pathArray['vat_number']));
    $action     = 'search';
} else {
    $vat_number = '';
}

if ($action === 'search' && $vat_number !== '') {
    $api     = new apiRequest($app);
    $dataOps = new dataOps($app);

    $vat_number = preg_replace('/^GB/', '', $vat_number);

    $results = $api->vatNumber($vat_number);

    if ($results) {
        $jdata = json_decode((string) $results);
        $idata = $dataOps->processVatResults($jdata);
        $dataOps->saveVatNumber($idata);
    } else {
        $message = 'A company matching the VAT number GB' . $vat_number . ' was not found.';
    }
}
