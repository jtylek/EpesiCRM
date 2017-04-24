var base_setup__last_options = false;
var base_setup__last_actions = false;
var base_setup__last_actions_option = false;

base_setup__show_options = function (name) {
	if (base_setup__last_options && base_setup__last_options!=name) {
		base_setup__hide_options(base_setup__last_options);
	}
	jq('[id="show_options_'+name+'"]').hide();
	jq('[id="hide_options_'+name+'"]').show();
	jq('[id="options_'+name+'"]').fadeIn();
	base_setup__last_options = name;
}

base_setup__hide_options = function (name) {
	jq('[id="show_options_'+name+'"]').show();
	jq('[id="hide_options_'+name+'"]').hide();
	jq('[id="options_'+name+'"]').fadeOut();
	base_setup__last_options = false;
}

base_setup__show_actions = function (name, option) {
	if ((base_setup__last_actions && base_setup__last_actions!=name) || (base_setup__last_actions_option && base_setup__last_actions_option!=option)) {
		base_setup__hide_actions(base_setup__last_actions, base_setup__last_actions_option);
	}
	el_id = name;
	if (option) {
		el_id = el_id+'__'+option;
		jq('[id="show_actions_button_'+name+'__'+option+'"]').hide();
		jq('[id="hide_actions_button_'+name+'__'+option+'"]').show();
		jq('[id="hide_actions_'+el_id+'"]').fadeIn();
	} else {
		if (jq('[id="hide_actions_'+el_id+'"]').length>0) {
			jq('[id="hide_actions_'+el_id+'"]').fadeIn();
		}
	}
	base_setup__last_actions = name;
	base_setup__last_actions_option = option;
}

base_setup__hide_actions = function (name, option) {
	el_id = name;
	if (option) {
		el_id = el_id+'__'+option;
		jq('[id="show_actions_button_'+name+'__'+option+'"]').show();
		jq('[id="hide_actions_button_'+name+'__'+option+'"]').hide();
		jq('[id="hide_actions_'+el_id+'"]').fadeOut();
	} else {
		if (jq('[id="hide_actions_'+el_id+'"]').length>0) {
			jq('[id="hide_actions_'+el_id+'"]').fadeOut();
		}
	}
	base_setup__last_actions = false;
	base_setup__last_actions_option = false;
}

base_setup__filter_by = function (attr) {

	if (base_setup__last_options)div
	base_setup__hide_options(base_setup__last_options);
	if (base_setup__last_actions)
		base_setup__hide_actions(base_setup__last_actions, base_setup__last_actions_option);
	jq('#Base_Setup__filter_'+base_setup__last_filter).attr('class',"btn");
	jq('#Base_Setup__filter_'+attr).attr('class',"btn selected");
	base_setup__last_filter = attr;
	for (w in jq('#Base_Setup').get(0).childNodes) {
		var div = jq('#Base_Setup').get(0).childNodes[w];
		if (div.nodeType==1) {
			if (div.getAttribute(attr) || !attr) {
				if (div.style.display!='') jq(div).fadeIn();
			} else {
				div.style.display='none';
			}
		}
	}
}

jq(document).on('click','ul#first-dropdown', function (e) {
	e.stopPropagation();
});
