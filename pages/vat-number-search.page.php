<section class="hero hero--centered hero--compact">
    <div class="container">
        <span class="eyebrow" style="color:#9fb2cc;">VAT Search</span>
        <h1>Find a UK VAT number</h1>
        <p>Start typing a company name and pick a result to look up its VAT registration.</p>

        <form data-search
              data-endpoint="/vtypeahead.php"
              data-target="/vat-number-search/vat_number/{id}"
              data-label-field="company_name"
              data-id-field="vat_number"
              class="search" role="search" aria-label="VAT number search">
            <div class="search__field">
                <i class="fa fa-search" aria-hidden="true"></i>
                <label class="sr-only" for="vat-search">Company name</label>
                <input id="vat-search" type="text" class="search__input"
                       placeholder="Enter a company name…" autocomplete="off" spellcheck="false">
                <button class="search__button" type="submit">Search</button>
            </div>
            <ul class="search__results" role="listbox"></ul>
        </form>
    </div>
</section>

<?php if ($action === 'search'): ?>
    <section class="section-tight">
        <div class="container">
            <div class="panel">
                <h2 class="mt-0">Search Results</h2>
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
            <span class="eyebrow">Free VAT lookup</span>
            <h2>Search for a VAT number</h2>
            <p>Enter a UK company name into the search box above. Results will appear here.</p>
        </div>
    </div>
</section>
