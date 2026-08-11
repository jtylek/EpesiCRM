function epesiBlind(el, down, seconds) {
	if (!el) return;
	el.style.overflow = 'hidden';
	if (down) {
		el.style.display = '';
		el.style.height = 'auto';
		var target = el.offsetHeight;
		el.style.height = '0px';
		el.offsetHeight;
		el.style.transition = 'height ' + seconds + 's';
		requestAnimationFrame(function(){ el.style.height = target + 'px'; });
		setTimeout(function(){ el.style.height=''; el.style.overflow=''; el.style.transition=''; }, seconds*1000);
	} else {
		var current = el.offsetHeight;
		el.style.height = current + 'px';
		el.offsetHeight;
		el.style.transition = 'height ' + seconds + 's';
		requestAnimationFrame(function(){ el.style.height = '0px'; });
		setTimeout(function(){ el.style.display='none'; el.style.height=''; el.style.overflow=''; el.style.transition=''; }, seconds*1000);
	}
}
function epesiAppear(el, seconds) {
	if (!el) return;
	el.style.display='';
	el.style.opacity='0';
	el.style.transition='opacity '+seconds+'s';
	requestAnimationFrame(function(){ el.style.opacity='1'; });
	setTimeout(function(){ el.style.transition=''; }, seconds*1000);
}
function epesiFade(el, seconds) {
	if (!el) return;
	el.style.transition='opacity '+seconds+'s';
	el.style.opacity='0';
	setTimeout(function(){ el.style.display='none'; el.style.opacity=''; el.style.transition=''; }, seconds*1000);
}

var base_setup__last_options = false;
var base_setup__last_actions = false;
var base_setup__last_actions_option = false;

base_setup__show_options = function (name) {
	if (base_setup__last_options && base_setup__last_options!=name) {
		base_setup__hide_options(base_setup__last_options);
	}
	document.getElementById('show_options_'+name).style.display='none';
	document.getElementById('hide_options_'+name).style.display='';
	epesiBlind(document.getElementById('options_'+name), true, 0.6);
	base_setup__last_options = name;
}

base_setup__hide_options = function (name) {
	document.getElementById('show_options_'+name).style.display='';
	document.getElementById('hide_options_'+name).style.display='none';
	epesiBlind(document.getElementById('options_'+name), false, 0.6);
	base_setup__last_options = false;
}

base_setup__show_actions = function (name, option) {
	if ((base_setup__last_actions && base_setup__last_actions!=name) || (base_setup__last_actions_option && base_setup__last_actions_option!=option)) {
		base_setup__hide_actions(base_setup__last_actions, base_setup__last_actions_option);
	}
	el_id = name;
	if (option) {
		el_id = el_id+'__'+option;
		document.getElementById('show_actions_button_'+name+'__'+option).style.display='none';
		document.getElementById('hide_actions_button_'+name+'__'+option).style.display='';
		epesiBlind(document.getElementById('hide_actions_'+el_id), true, 0.5);
	} else {
        if (document.getElementById('hide_actions_'+el_id)) {
		    epesiAppear(document.getElementById('hide_actions_'+el_id), 0.2);
        }
	}
	base_setup__last_actions = name;
	base_setup__last_actions_option = option;
}

base_setup__hide_actions = function (name, option) {
	el_id = name;
	if (option) {
		el_id = el_id+'__'+option;
		document.getElementById('show_actions_button_'+name+'__'+option).style.display='';
		document.getElementById('hide_actions_button_'+name+'__'+option).style.display='none';
		epesiBlind(document.getElementById('hide_actions_'+el_id), false, 0.5);
	} else {
        if (document.getElementById('hide_actions_'+el_id)) {
		    epesiFade(document.getElementById('hide_actions_'+el_id), 0.2);
        }
	}
	base_setup__last_actions = false;
	base_setup__last_actions_option = false;
}

base_setup__filter_by = function (attr) {
	if (base_setup__last_options)
		base_setup__hide_options(base_setup__last_options);
	if (base_setup__last_actions)
		base_setup__hide_actions(base_setup__last_actions, base_setup__last_actions_option);
	var container = document.getElementById('Base_Setup');
	var next_tab = document.getElementById('Base_Setup__filter_'+attr);
	if (!container || !next_tab) return;
	var prev_tab = document.getElementById('Base_Setup__filter_'+base_setup__last_filter);
	if (prev_tab) prev_tab.className="";
	next_tab.className="selected";
	base_setup__last_filter = attr;
	for (w = 0; w < container.childNodes.length; w++) {
		var div = container.childNodes[w];
		if (div.nodeType==1) {
			if (div.getAttribute(attr) || !attr) {
				if (div.style.display!='') epesiAppear(div, 0.4);
			} else {
				div.style.display='none';
			}
		}
	}
}
