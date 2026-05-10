<section class="page-header">
    <div class="container">
        <h1>Postcode Data</h1>
        <p class="breadcrumbs"><a href="/">Home</a> <span aria-hidden="true">›</span> Postcode Data</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">Open postcode data</span>
            <h2>Data about every UK postcode</h2>
            <p>A data index of postcode records with the companies, officers, PSCs and property
               data associated with each. Understand what's available at postcode level.</p>
        </div>

        <div class="grid grid-3">
            <?php
            $tiles = [
                ['fa-home',     'Property Prices',              'Paid-price data from 1995 to current for all residential property in the UK, published three months after completion.'],
                ['fa-building', 'Companies',                    'Companies operating within each postcode. See every UK registered business at postcode level.'],
                ['fa-users',    'Company Officers',             'Company officers including directors and secretaries based on their service address.'],
                ['fa-id-card-o','Persons of Significant Control','PSCs listed against the service address at postcode level.'],
                ['fa-gavel',    'Insolvencies',                 'Corporate insolvencies and winding-up orders collated at postcode level.'],
                ['fa-file-text-o', 'Wills &amp; Probate',       'Will &amp; Probate records created within a postcode, with statistics.'],
            ];
            foreach ($tiles as [$icon, $title, $body]): ?>
                <article class="card card--feature">
                    <span class="card__icon"><i class="fa <?php echo e($icon); ?>" aria-hidden="true"></i></span>
                    <h4><?php echo $title; ?></h4>
                    <p><?php echo $body; ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
