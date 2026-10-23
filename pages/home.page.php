<section class="hero hero--centered">
    <div class="container">
        <h1>UK company data, instantly</h1>
        <p>Search the UK register by company name, number, postcode or VAT
           number — then dig into filings and financials.</p>

        <form data-search
              data-endpoint="/typeahead.php"
              data-target="/company/number/{id}"
              data-label-field="CompanyName"
              data-id-field="CompanyNumber"
              class="search" role="search" aria-label="Company search">
            <div class="search__field">
                <i class="fa fa-search" aria-hidden="true"></i>
                <label class="sr-only" for="home-search">Company name</label>
                <input id="home-search" type="text" class="search__input"
                       placeholder="Enter a company name…" autocomplete="off" spellcheck="false">
                <button class="search__button" type="submit">Search</button>
            </div>
            <ul class="search__results" role="listbox"></ul>
        </form>

        <p class="search__hint mt-3">
            Looking for something else? Try
            <a href="/vat-number-search">VAT search</a> or
            <a href="/postcode-data">Postcode data</a>.
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading text-center" style="margin-inline:auto;">
            <span class="eyebrow">Data Services</span>
            <h2>Public UK data, neatly indexed</h2>
            <p style="margin-inline:auto;">
                DataIndex pulls publicly available information — from Companies House to HMRC
                VAT records and ONS postcodes — into one fast, searchable interface.
            </p>
        </div>

        <div class="grid grid-3">
            <article class="card card--feature">
                <span class="card__icon"><i class="fa fa-database" aria-hidden="true"></i></span>
                <h4>Company Data</h4>
                <p>Names, registered addresses, filing history and financial snapshots
                   for every active UK company.</p>
            </article>
            <article class="card card--feature">
                <span class="card__icon"><i class="fa fa-check-circle" aria-hidden="true"></i></span>
                <h4>VAT Number Validation</h4>
                <p>Validate any GB VAT number against HMRC's live lookup service and retrieve
                   the registered business details.</p>
            </article>
            <article class="card card--feature">
                <span class="card__icon"><i class="fa fa-file-text-o" aria-hidden="true"></i></span>
                <h4>Filing History</h4>
                <p>Accounts, confirmation statements and other documents filed with
                   Companies House, in one timeline.</p>
            </article>
        </div>
    </div>
</section>

<section class="section-sm" style="background:var(--c-surface);">
    <div class="container">
        <div class="grid grid-2" style="align-items:center; gap:3rem;">
            <div>
                <span class="eyebrow">Why DataIndex</span>
                <h2>A cleaner window into public data</h2>
                <p>In today's digital age there is a vast amount of data available online,
                   but it is often dispersed across platforms and difficult to navigate.
                   DataIndex brings it into one place — searchable, cross-referenced, and free.</p>
                <p class="mt-2">
                    <a class="btn btn-primary" href="/company-data">
                        Explore company data
                        <i class="fa fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </p>
            </div>
            <div class="panel">
                <h4 class="mt-0">What you'll find</h4>
                <ul class="stack-tight mt-2" style="list-style:none;padding:0;">
                    <li><i class="fa fa-check" aria-hidden="true" style="color:var(--c-brand);margin-right:.5rem;"></i> Company registrations, status and filing history</li>
                    <li><i class="fa fa-check" aria-hidden="true" style="color:var(--c-brand);margin-right:.5rem;"></i> Accounts and confirmation statements</li>
                    <li><i class="fa fa-check" aria-hidden="true" style="color:var(--c-brand);margin-right:.5rem;"></i> Postcode-level directories of registered businesses</li>
                    <li><i class="fa fa-check" aria-hidden="true" style="color:var(--c-brand);margin-right:.5rem;"></i> HMRC VAT number lookups in real time</li>
                </ul>
            </div>
        </div>
    </div>
</section>
