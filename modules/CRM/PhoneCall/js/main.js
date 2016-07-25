CRM_PhoneCall__form_control = {
	form_id: '',
	init: function (form_name, other_phone_default_checked, other_customer_default_checked) {
		CRM_PhoneCall__form_control.form_id = '#'+form_name;
		jq('#other_phone').change(CRM_PhoneCall__form_control.onchange_other_phone);
		jq('#other_customer').change(CRM_PhoneCall__form_control.onchange_other_customer);
		CRM_PhoneCall__form_control.toggle_phone(other_phone_default_checked || CRM_PhoneCall__form_control.is_checked('#other_phone'));
		CRM_PhoneCall__form_control.toggle_customer(other_customer_default_checked || CRM_PhoneCall__form_control.is_checked('#other_customer'));
	},
	onchange_other_phone: function () {
		CRM_PhoneCall__form_control.toggle_phone(CRM_PhoneCall__form_control.is_checked('#other_phone'));
	},
	onchange_other_customer: function () {
		CRM_PhoneCall__form_control.toggle_customer(CRM_PhoneCall__form_control.is_checked('#other_customer'));
	},
	toggle_phone: function (other_phone_is_checked)	{
		CRM_PhoneCall__form_control.toggle_element('#phone', other_phone_is_checked);
		CRM_PhoneCall__form_control.toggle_element('#other_phone_number', !other_phone_is_checked);
	},
	toggle_customer: function (other_customer_is_checked)	{
		CRM_PhoneCall__form_control.toggle_element('#customer, #__autocomplete_id_customer__search, #other_phone', other_customer_is_checked);
		CRM_PhoneCall__form_control.toggle_element('#other_customer_name', !other_customer_is_checked);
		if (other_customer_is_checked) {
			jq('#other_phone', CRM_PhoneCall__form_control.form_id).prop('checked', true);
			CRM_PhoneCall__form_control.toggle_phone(true);
		}
	},
	is_checked: function (element_id) {
		return jq(element_id, CRM_PhoneCall__form_control.form_id).is(':checked');
	},
	toggle_element: function (element_selector, disabled) {
		jq(element_selector, CRM_PhoneCall__form_control.form_id).prop('disabled', disabled);
	}
}
