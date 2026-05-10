<section class="hero hero--centered hero--compact">
    <div class="container">
        <h1>No matches found</h1>
        <p>Try searching with a different spelling or fewer words.</p>

        <form data-search
              data-endpoint="/typeahead.php"
              data-target="/company/number/{id}"
              data-label-field="CompanyName"
              data-id-field="CompanyNumber"
              class="search" role="search" aria-label="Company search">
            <div class="search__field">
                <i class="fa fa-search" aria-hidden="true"></i>
                <label class="sr-only" for="nores-search">Company name</label>
                <input id="nores-search" type="text" class="search__input"
                       placeholder="Enter a company name…" autocomplete="off" spellcheck="false">
                <button class="search__button" type="submit">Search</button>
            </div>
            <ul class="search__results" role="listbox"></ul>
        </form>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="panel text-center">
            <h2>No results for your search</h2>
            <p class="text-muted">Double-check the company name, or head back to the
               <a href="/">homepage</a> to start again.</p>
        </div>
    </div>
</section>
