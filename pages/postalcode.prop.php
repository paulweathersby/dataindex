<?php
declare(strict_types=1);

// URLs encode the space in a postcode as a hyphen (see dataOps::pcode2String),
// so we must preserve hyphens through sanitisation and let string2pcode()
// turn them back into a space before querying the DB.
$pcode_string = strtolower(
    preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($pathArray['postcode'] ?? ''))
);

$dataOps = new dataOps($app);
$pcode   = $dataOps->string2pcode($pcode_string);

$pcode_data = $app->query(
    "SELECT * FROM postcodes WHERE postcode = :pcode LIMIT 1",
    [':pcode' => $pcode]
)->fetch(PDO::FETCH_ASSOC) ?: [];

$comp_query = $app->query(
    "SELECT * FROM companies WHERE RegAddress_Postcode = :pcode",
    [':pcode' => $pcode]
);

$vat_query = $app->query(
    "SELECT * FROM vat_number WHERE postcode = :pcode",
    [':pcode' => $pcode]
);

$price_query = $app->query(
    'SELECT price, date_of_transfer, property_type, old_new, duration,
            paon, saon, street, locality, town_city, district, county,
            ppd_category_type
     FROM hmlr_price_paid
     WHERE postcode = :pcode
       AND record_status <> :deleted
     ORDER BY date_of_transfer DESC, price DESC',
    [
        ':pcode'   => $pcode,
        ':deleted' => 'D',
    ]
);

$crime = null;
$crimeLatitude = trim((string) ($pcode_data['latitude'] ?? ''));
$crimeLongitude = trim((string) ($pcode_data['longitude'] ?? ''));
if ($pcode !== '' && $crimeLatitude !== '' && $crimeLongitude !== '' && is_numeric($crimeLatitude) && is_numeric($crimeLongitude)) {
    $crime = (new crimeReport($app, new apiRequest($app)))->forPostcode(
        $pcode,
        (float) $crimeLatitude,
        (float) $crimeLongitude,
        (string) ($pcode_data['country'] ?? '')
    );
}

$pageTitle = 'Information about ' . ($pcode_data['postcode'] ?? $pcode) . ' ' . ($pcode_data['ward'] ?? '');
$canonical = SITE_URL . 'postalcode/postcode/' . rawurlencode($pcode_string);
$pageDescription = 'Information about ' . ($pcode_data['postcode'] ?? $pcode)
    . ' ' . ($pcode_data['ward'] ?? '')
    . ' including postcode data, property sales, street-level crime, registered companies and VAT registered businesses.';
