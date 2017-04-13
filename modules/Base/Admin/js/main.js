admin_switch_button = function(button_id,field_checked,section_id,init) {
	var section = jq('#'+section_id);
	if (init) dur = 0; else dur = 500;
	if (field_checked) {
		var s_opacity=1;
		if (admin_switch_button.status[button_id] == 1 && !init) return;
		admin_switch_button.status[button_id] = 1;
        section.slideDown(dur);
	} else {
		var s_opacity=0.4;
		if (admin_switch_button.status[button_id] == 0 && !init) return;
		admin_switch_button.status[button_id] = 0;
        section.slideUp(dur);
	}
	jq('#'+button_id).fadeTo(dur,s_opacity);
}

admin_switch_button.status = {};
