Utils_LeightboxPrompt = {
	init : function(group, active_option_key) {
		if (active_option_key)
			Utils_LeightboxPrompt.show_form(group, active_option_key);
		else
			Utils_LeightboxPrompt.show_buttons(group);
	},
	activate : function(group, params) {
		leightbox_activate(group + '_prompt_leightbox');
		Utils_LeightboxPrompt.set_params(group, params);
	},
	set_params : function(group, params) {
		if (!params) return;

		params = jq.type(params)==='string'? jq.deparam(params): params;

		jq.each(params, function(key, value) {
			jq('[name="' + group + '_' + key + '"]').val(value);
		});
	},
	deactivate : function(group, reset_view) {
		leightbox_deactivate(group + '_prompt_leightbox');

		if (reset_view) {
			Utils_LeightboxPrompt.show_buttons(group);
		}
	},
	show_buttons : function(group) {
		jq('.' + group + '_form_section').hide();
		jq('#' + group + '_buttons_section').show();
	},
	show_form : function(group, option_key) {
		jq('#' + group + '_' + option_key + '_form_section').show();
		jq('#' + group + '_buttons_section').hide();
	}
}