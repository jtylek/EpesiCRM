RB_hide_form_field = function (field, hide) {
	if ($(field)) $(field).up('tr').style.display = hide?'none':'';
	//else alert(field);
}

RB_hide_form_fields = function () {
	var t = $('select_data_type').value;
	RB_hide_form_field('required', (t=='checkbox') || (t=='autonumber'));
	RB_hide_form_field('length', t!='text');
	RB_hide_form_field('data_source', t!='select');
	RB_hide_form_field('select_type', t!='select');
    RB_hide_form_field('filter', t=='autonumber');
    RB_hide_form_field('autonumber_prefix', t!='autonumber');
    RB_hide_form_field('autonumber_pad_length', t!='autonumber');
    RB_hide_form_field('autonumber_pad_mask', t!='autonumber');
	if ($('data_source')) {
		var d = $('data_source').value;
		RB_hide_form_field('rset', d!='rset' || t!='select');
		RB_hide_form_field('label_field', d!='rset' || t!='select');
		RB_hide_form_field('commondata_table', d!='commondata' || t!='select');
		RB_hide_form_field('order_by', d!='commondata' || t!='select');
	}
}

var RB_advanced_confirmation = '';
RB_advanced_settings = function () {
	var a = $('advanced').checked;
	if (a) {
		if (!confirm(RB_advanced_confirmation)) {
			$('advanced').checked = false;
			return;
		}
	}
	RB_hide_form_field('display_callback', !a);
	RB_hide_form_field('QFfield_callback', !a);
}
