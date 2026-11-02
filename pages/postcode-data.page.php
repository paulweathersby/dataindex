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
            <p>A data index of postcode records, with the companies, property sales
               and street-level crime associated with each.</p>
        </div>

        <form data-search
              data-endpoint="/postcode-typeahead.php"
              data-target="/postalcode/postcode/{id}"
              data-label-field="postcode"
              data-id-field="slug"
              data-detail-fields="location,country"
              class="search search--left" role="search" aria-label="Postcode search">
            <div class="search__field">
                <i class="fa fa-search" aria-hidden="true"></i>
                <label class="sr-only" for="postcode-search">Postcode</label>
                <input id="postcode-search" type="text" class="search__input"
                       placeholder="Enter a postcode…" autocomplete="off" spellcheck="false"
                       inputmode="text" maxlength="8">
                <button class="search__button" type="submit">Search</button>
            </div>
            <ul class="search__results" role="listbox"></ul>
        </form>

        <div class="grid grid-3">
            <?php
            $tiles = [
                ['fa-home',     'Property Prices',              'Paid-price data from 1995 to current for all residential property in the UK, published three months after completion.'],
                ['fa-building', 'Companies',                    'Companies operating within each postcode. See every UK registered business at postcode level.'],
                ['fa-shield',   'Crime',                        'Street-level crime from data.police.uk for the latest published month, within a quarter of a mile. Counts by type, outcome and approximate street. In Scotland this includes British Transport Police only.'],
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
