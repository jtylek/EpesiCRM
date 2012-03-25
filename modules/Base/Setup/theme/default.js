var base_setup__last_options = false;
var base_setup__last_actions = false;
var base_setup__last_actions_option = false;

base_setup__show_options = function (name) {
	if (base_setup__last_options && base_setup__last_options!=name) {
		base_setup__hide_options(base_setup__last_options);
	}
	$('show_options_'+name).style.display='none';
	$('hide_options_'+name).style.display='';
	Effect.BlindDown($('options_'+name), {duration:0.6});
	base_setup__last_options = name;
}

base_setup__hide_options = function (name) {
	$('show_options_'+name).style.display='';
	$('hide_options_'+name).style.display='none';
	Effect.BlindUp($('options_'+name), {duration:0.6});
	base_setup__last_options = false;
}

base_setup__show_actions = function (name, option) {
	if ((base_setup__last_actions && base_setup__last_actions!=name) || (base_setup__last_actions_option && base_setup__last_actions_option!=option)) {
		base_setup__hide_actions(base_setup__last_actions, base_setup__last_actions_option);
	}
	el_id = name;
	if (option) {
		el_id = el_id+'__'+option;
		$('show_actions_button_'+name+'__'+option).style.display='none';
		$('hide_actions_button_'+name+'__'+option).style.display='';
		Effect.BlindDown($('hide_actions_'+el_id), {duration:0.5});
	} else {
		Effect.Appear($('hide_actions_'+el_id), {duration:0.2});
	}
	base_setup__last_actions = name;
	base_setup__last_actions_option = option;
}

base_setup__hide_actions = function (name, option) {
	el_id = name;
	if (option) {
		el_id = el_id+'__'+option;
		$('show_actions_button_'+name+'__'+option).style.display='';
		$('hide_actions_button_'+name+'__'+option).style.display='none';
		Effect.BlindUp($('hide_actions_'+el_id), {duration:0.5});
	} else {
		Effect.Fade($('hide_actions_'+el_id), {duration:0.2});
	}
	base_setup__last_actions = false;
	base_setup__last_actions_option = false;
}

base_setup__filter_by = function (attr) {
	if (base_setup__last_options)
		base_setup__hide_options(base_setup__last_options);
	if (base_setup__last_actions)
		base_setup__hide_actions(base_setup__last_actions, base_setup__last_actions_option);
	$('Base_Setup__filter_'+base_setup__last_filter).className="";
	$('Base_Setup__filter_'+attr).className="selected";
	base_setup__last_filter = attr;
	for (w in $('Base_Setup').childNodes) {
		var div = $('Base_Setup').childNodes[w];
		if (div.nodeType==1) {
			if (div.getAttribute(attr) || !attr) {
				if (div.style.display!='') Effect.Appear(div, {duration:0.4});
			} else {
				div.style.display='none';
			}
		}
	}
}

base_setup__package_description = function (url,package_name) {
	$('Base_Setup__module_name').innerHTML = package_name;
	leightbox_activate('base_setup__module_desc_leightbox');
	var iframe = $('Base_Setup__module_description');
	iframe.src = url;
	iframe.style.height = (iframe.parentNode.parentNode.clientHeight-35)+'px';
	iframe.style.width = '100%';
}
