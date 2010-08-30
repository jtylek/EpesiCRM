crm_contacts_display_company = function(state) {
	if ($("company_name__from")) {
		$("company_name__from").disabled = state;
		$("company_name__to").disabled = state;
		$("company_name__add_selected").disabled = state;
		$("company_name__add_all").disabled = state;
		$("company_name__remove_selected").disabled = state;
		$("company_name__remove_all").disabled = state;
	} else {
		$("__autocomplete_id_company_name__search").disabled = state;
		$("company_name").disabled = state;
	}
}