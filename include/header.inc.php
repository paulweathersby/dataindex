<?php
/** @var string $page Set by public/index.php */
$currentPage = $page ?? '';

$navItems = [
    ['home',                 '/',                        'Home'],
    ['company-data',         '/company-data',            'Company Data'],
    ['vat-number-search',    '/vat-number-search',       'VAT Search'],
    ['vat-number-validation','/vat-number-validation',   'VAT Validation'],
    ['postcode-data',        '/postcode-data',           'Postcode Data'],
    ['resources',            '/resources',               'Resources'],
];
?>
<header class="site-header" role="banner">
    <div class="container site-header__inner">
        <a class="brand" href="/" aria-label="Data Index home">
            Data<span class="brand__accent">Index</span>
        </a>

        <button class="nav-toggle" type="button"
                data-nav-toggle
                aria-controls="primary-nav"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <i class="fa fa-bars" aria-hidden="true"></i>
        </button>

        <nav id="primary-nav" class="primary-nav" data-primary-nav aria-label="Primary">
            <?php foreach ($navItems as [$slug, $href, $label]): ?>
                <a href="<?php echo eAttr($href); ?>"
                   <?php echo $currentPage === $slug ? 'aria-current="page"' : ''; ?>>
                    <?php echo e($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
