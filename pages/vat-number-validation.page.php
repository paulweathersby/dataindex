<section class="hero hero--centered hero--compact">
    <div class="container">
        <span class="eyebrow" style="color:#9fb2cc;">VAT Validation</span>
        <h1>Validate a UK VAT number</h1>
        <p>Check whether a GB VAT registration number is live with HMRC.</p>

        <form method="post" action="/vat-number-validation" class="search" role="search" aria-label="VAT number validation">
            <input type="hidden" name="action" value="search">
            <input type="hidden" name="_csrf" value="<?php echo eAttr($csrf); ?>">
            <div class="search__field">
                <i class="fa fa-check" aria-hidden="true"></i>
                <label class="sr-only" for="vat-validate">VAT number</label>
                <input id="vat-validate" type="text" name="vat_number" class="search__input"
                       placeholder="Enter GB VAT number…"
                       value="<?php echo eAttr($vat_number ?? ''); ?>"
                       autocomplete="off" spellcheck="false">
                <button class="search__button" type="submit">Validate</button>
            </div>
        </form>
    </div>
</section>

<?php if ($action === 'search'): ?>
    <section class="section-tight">
        <div class="container">
            <div class="panel">
                <h2 class="mt-0">Validation Result</h2>
                <?php if ($message !== ''): ?>
                    <div class="alert alert-warning"><?php echo e($message); ?></div>
                <?php else: ?>
                    <p class="stack-tight">
                        <strong>GB<?php echo e($idata['vat_number'] ?? ''); ?></strong><br>
                        <?php echo e($idata['company_name'] ?? ''); ?><br>
                        <?php foreach (['address1','address2','address3','address4','address5','postcode','country_code'] as $field): ?>
                            <?php if (!empty($idata[$field])): ?>
                                <?php echo e($idata[$field]); ?><br>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">How it works</span>
            <h2>UK &amp; EU VAT number formats</h2>
            <p>A UK VAT number is nine digits long, prefixed with <strong>GB</strong>.
               EU VAT numbers can be up to 15 alphanumeric characters; the first two letters
               indicate the country code.</p>
        </div>

        <div class="grid grid-3">
            <?php
            $formats = [
                'AT' => 'Austria — U + 8 numbers',
                'BE' => 'Belgium — 9 or 10 characters',
                'BG' => 'Bulgaria — 9 or 10 characters',
                'CY' => 'Cyprus — 9 characters (last a letter)',
                'CZ' => 'Czech Republic — 8–10 characters',
                'DE' => 'Germany — 9 characters',
                'DK' => 'Denmark — 8 characters',
                'EE' => 'Estonia — 9 characters',
                'EL' => 'Greece — 9 characters',
                'ES' => 'Spain — 9 characters',
                'FI' => 'Finland — 8 characters',
                'FR' => 'France — 11 characters',
                'GB' => 'United Kingdom — 9 numbers',
                'HU' => 'Hungary — 8 characters',
                'IE' => 'Ireland — 8 characters',
                'IT' => 'Italy — 11 characters',
                'LT' => 'Lithuania — 9 or 12 characters',
                'LU' => 'Luxembourg — 8 characters',
                'LV' => 'Latvia — 11 characters',
                'MT' => 'Malta — 8 characters',
                'NL' => 'Netherlands — 12 chars (10th is B)',
                'PL' => 'Poland — 10 characters',
                'PT' => 'Portugal — 9 characters',
                'RO' => 'Romania — 2–12 characters',
                'SE' => 'Sweden — 12 characters',
                'SI' => 'Slovenia — 8 characters',
                'SK' => 'Slovakia — 10 characters',
            ];
            foreach ($formats as $code => $desc): ?>
                <div class="card">
                    <span class="badge badge--brand"><?php echo e($code); ?></span>
                    <p class="mt-2 mb-0"><?php echo e($desc); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
