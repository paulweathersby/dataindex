<?php
declare(strict_types=1);

$pageTitle       = 'VAT Number Validation – Data Index';
$pageDescription = 'Validate a UK VAT number for free. Enter a VAT number to look up the registered company.';
$canonical       = SITE_URL . 'vat-number-validation';

$idata   = [];
$message = '';
$action  = '';
$csrf    = csrfToken();

$vat_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        $message = 'Your session expired. Please refresh the page and try again.';
        $action  = 'search';
    } else {
        $vat_number = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['vat_number'] ?? '')));
        $action     = (string) ($_POST['action'] ?? '');
    }
}

if ($action === 'search' && $vat_number !== '' && $message === '') {
    $api     = new apiRequest($app);
    $dataOps = new dataOps($app);

    $vat_number = $dataOps->normalizeVatNumber($vat_number);
    $cached     = $dataOps->loadVatFromDatabase($vat_number);
    $idata      = $dataOps->fetchVatRecord($vat_number, $api);

    if (!$idata && $cached) {
        $idata = $cached;
    }

    if (!$idata) {
        $message = 'A company matching the VAT number GB' . $vat_number . ' was not found.';
    }
}
