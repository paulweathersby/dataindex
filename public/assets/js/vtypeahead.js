var $ = jQuery;
var isLoading = false;
var $companySearchInput = $('.um-field-company_search input');


if ($companySearchInput.length <= 0) {
  $companySearchInput = $('.form-controlinput[name=company-search]'); // CF7 (Logged in form)
}
var $compDropdownContainer = $('.um-field-company_select');


if ($compDropdownContainer.length <= 0) {
  $compDropdownContainer = $('.company-dropdown-container'); // CF7 (Logged in form)
}

var $compDropdown = $('.um-field-company_select select');


if ($compDropdown.length <= 0) {
  $compDropdown = $('.company-dropdown-container select'); // CF7 (Logged in form)
}






jQuery(document).ready(() => {
    jQuery("input[name=company-search]").prop("autocomplete", "off");
    if (jQuery("input[name=company-search]").prop("name")) {
      jQuery("input[name=company-search]").on("input", async () => {
        const query = jQuery("input[name=company-search]").val();
        const response = await fetch(`/vtypeahead.php?query=${query}`);
        const results = await response.json();
        const suggestions = results.map(result => result.company_name);
        jQuery("input[name=company-search]").autocomplete({
          source: suggestions
        });
      });
    }



  });
