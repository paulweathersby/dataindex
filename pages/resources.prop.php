<?php
declare(strict_types=1);

$sectionMap = [
    'general'   => 1,
    'companies' => 2,
    'people'    => 3,
    'places'    => 4,
];

$section_name = (string) ($pathArray['section'] ?? '');
$section      = $sectionMap[$section_name] ?? 0;

$canonical = $section !== 0
    ? SITE_URL . 'resources/section/' . $section_name
    : SITE_URL . 'resources';

$pageTitle       = 'Resources - Data Index';
$pageDescription = 'Data resources of the UK. A vast amount of information is publicly available — explore it here.';

if ($section !== 0) {
    $list_query = $app->queryi(
        "SELECT * FROM blog WHERE category = :cat ORDER BY id DESC",
        [':cat' => $section]
    );
} else {
    $list_query = $app->queryi(
        "SELECT * FROM blog ORDER BY id DESC LIMIT 50"
    );
}
