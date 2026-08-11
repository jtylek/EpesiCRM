recordbrowser_edit_history_meta = {};

recordbrowser_edit_history_jump = function (selected_date, tab, id, form_name) {
	jq('#historical_view_pick_date').val(selected_date);
	recordbrowser_edit_history(tab, id, form_name);
	recordbrowser_edit_history_update_buttons();
}

recordbrowser_edit_history = function (tab, id, form_name) {
	var field = "historical_view_pick_date";
	jq.ajax({
		type: 'post',
		url: 'modules/Utils/RecordBrowser/edit_history.php',
		data:{
			tab: tab,
			id: id,
			date: jq('#'+field).val(),
			cid: Epesi.client_id
		},
		success:function(response) {
			eval(response);
		}
	});
}

recordbrowser_edit_history_step = function (direction, tab, id, form_name) {
	var select = jq('#historical_view_pick_date')[0];
	if (!select) return;
	var new_index = select.selectedIndex + direction;
	if (new_index < 0 || new_index >= select.options.length) return;
	select.selectedIndex = new_index;
	recordbrowser_edit_history(tab, id, form_name);
	recordbrowser_edit_history_update_buttons();
}

recordbrowser_edit_history_update_buttons = function () {
	var select = jq('#historical_view_pick_date')[0];
	if (!select) return;
	var at_start = select.selectedIndex <= 0;
	var at_end = select.selectedIndex >= select.options.length - 1;
	jq('#historical_view_prev_button').prop('disabled', at_end);
	jq('#historical_view_next_button').prop('disabled', at_start);
	recordbrowser_edit_history_update_indicators();
}

recordbrowser_edit_history_update_indicators = function () {
	var select = jq('#historical_view_pick_date')[0];
	var badge = jq('#historical_view_created_badge');
	var user_span = jq('#historical_view_username_indicator');
	if (!select) return;
	var meta = recordbrowser_edit_history_meta[select.value];
	if (!meta) {
		badge.hide();
		user_span.html('');
		return;
	}
	badge.toggle(!!meta.created);
	if (meta.user) {
		user_span.html(jq('#historical_view_indicators').attr('data-by-label') + ': ' + meta.user);
	} else {
		user_span.html('');
	}
}
