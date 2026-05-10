// Instantiate the Bloodhound suggestion engine
const companies = new Bloodhound({
    datumTokenizer: datum => Bloodhound.tokenizers.whitespace(datum.value),
    queryTokenizer: Bloodhound.tokenizers.whitespace,
    remote: {
      url: '/typeahead.php?query=%QUERY',
      wildcard: '%QUERY',
      // Map the remote source JSON array to a JavaScript object array
      filter: companies => $.map(companies, companies => ({
        value: companies.CompanyName
      }))

    }
  });
  
  // Initialize the Bloodhound suggestion engine
  companies.initialize();
  
  // Instantiate the Typeahead UI
  $('.typeahead').typeahead(null, {
    displayKey: 'value',
    source: companies.ttAdapter()
  });