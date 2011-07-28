admin_switch_button = function(button_id,field_checked,section_id,init) {
	var section = $(section_id);
	if (init) dur = 0; else dur = 0.3;
	if (field_checked) {
		var s_opacity=1;
		if (admin_switch_button.status[button_id] == 1 && !init) return;
		admin_switch_button.status[button_id] = 1;
		Effect.BlindDown(section, {duration:dur});
	} else {
		var s_opacity=0.4;
		if (admin_switch_button.status[button_id] == 0 && !init) return;
		admin_switch_button.status[button_id] = 0;
		Effect.BlindUp(section, {duration:dur});
	}
	$(button_id).style.opacity=s_opacity;
}

admin_switch_button.status = {};
