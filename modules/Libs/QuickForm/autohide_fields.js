var Libs_QuickForm__hide_groups = {};
Libs_QuickForm__autohide = function(e) {
	var el = jq(e.target);
	var hide_groups = Libs_QuickForm__hide_groups[el.attr('id')];
	var reverse_mode = {
		'hide' : 'show',
		'show' : 'hide'
	};
	
	set_fields = {};
	jq.each(hide_groups, function(i, group) {
		var f = jq(group.fields).closest('tr');
		var autohide_values = group.values;
		var val;
		if (el.attr('type') == 'checkbox') {
			val = el.is(':checked') ? '1' : '0';
		} else {
			val = el.val();
		}

		if (autohide_values.indexOf(val) > -1) {
			f[group.mode]();
		} else {
			//apply reverse mode only to fields not specifically set
			not_set_fields = jq(group.fields).not(set_fields[group.mode]).get();
			jq(not_set_fields).closest('tr')[reverse_mode[group.mode]]();
		}
		
		set_fields[group.mode] = group.fields;
	});
}