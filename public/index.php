<?php
/**
 * Front controller.
 *
 * Safely resolves a `path` query parameter to a page in ../pages/ and
 * renders it inside the shared layout.
 */

declare(strict_types=1);

require __DIR__ . '/../config/app.cfg.php';

[$page, $pathArray] = parsePath($_GET['path'] ?? null);

if (!routeIsAllowed($page)) {
    $page = '404';
}

visitorCheck::guardPage($page);

$propFile = PAGE_PATH . '/' . $page . '.prop.php';
$pageFile = PAGE_PATH . '/' . $page . '.page.php';

if (!is_file($propFile) || !is_file($pageFile)) {
    $page = '404';
    $propFile = PAGE_PATH . '/404.prop.php';
    $pageFile = PAGE_PATH . '/404.page.php';
}

// Defaults so the layout never outputs "undefined" notices.
$pageTitle       = 'Data Index';
$pageDescription = '';
$canonical       = null;

include_once $propFile;

if (!headers_sent()) {
    header('Cache-Control: no-cache');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#0b1a2b">

    <title><?php echo e($pageTitle); ?></title>

    <?php if (!empty($pageDescription)): ?>
        <meta name="description" content="<?php echo eAttr($pageDescription); ?>">
    <?php endif; ?>
    <?php if (!empty($canonical)): ?>
        <link rel="canonical" href="<?php echo eAttr($canonical); ?>">
    <?php endif; ?>

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="DataIndex.online">
    <meta property="og:title" content="<?php echo eAttr($pageTitle); ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo eAttr($pageTitle); ?>">
    <?php if (!empty($pageDescription)): ?>
        <meta property="og:description" content="<?php echo eAttr($pageDescription); ?>">
        <meta name="twitter:description" content="<?php echo eAttr($pageDescription); ?>">
    <?php endif; ?>

    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
    <link rel="manifest" href="/assets/images/site.webmanifest">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo eAttr(assetUrl('assets/css/fontawesome.css')); ?>">
    <link rel="stylesheet" href="<?php echo eAttr(assetUrl('assets/css/dataindex.css')); ?>">

    <?php if (GA_MEASUREMENT_ID !== ''): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo eAttr(GA_MEASUREMENT_ID); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', <?php echo json_encode(GA_MEASUREMENT_ID); ?>);
        </script>
    <?php endif; ?>

    <?php if (OPENREPLAY_PROJECT_KEY !== '' && OPENREPLAY_INGEST_POINT !== ''): ?>
        <script>
            var initOpts = {
                projectKey: <?php echo json_encode(OPENREPLAY_PROJECT_KEY); ?>,
                ingestPoint: <?php echo json_encode(OPENREPLAY_INGEST_POINT); ?>,
                defaultInputMode: 2,
                obscureTextNumbers: false,
                obscureTextEmails: true
            };
            var startOpts = { userID: "" };
            (function(A,s,a,y,e,r){
                r=window.OpenReplay=[e,r,y,[s-1, e]];
                s=document.createElement('script');s.src=A;s.async=!a;
                document.getElementsByTagName('head')[0].appendChild(s);
                r.start=function(v){r.push([0])};
                r.stop=function(v){r.push([1])};
                r.setUserID=function(id){r.push([2,id])};
                r.setUserAnonymousID=function(id){r.push([3,id])};
                r.setMetadata=function(k,v){r.push([4,k,v])};
                r.event=function(k,p,i){r.push([5,k,p,i])};
                r.issue=function(k,p){r.push([6,k,p])};
                r.isActive=function(){return false};
                r.getSessionToken=function(){};
            })("//static.openreplay.com/18.0.6/openreplay.js",1,0,initOpts,startOpts);
        </script>
    <?php endif; ?>
</head>
<body data-page="<?php echo eAttr($page); ?>">

<a class="skip-link" href="#main">Skip to main content</a>

<?php include_once INCLUDE_PATH . '/header.inc.php'; ?>

<main id="main" class="site-main">
    <?php include_once $pageFile; ?>
</main>

<?php include_once INCLUDE_PATH . '/footer.inc.php'; ?>

<script src="<?php echo eAttr(assetUrl('assets/js/dataindex.js')); ?>" defer></script>
</body>
</html>
