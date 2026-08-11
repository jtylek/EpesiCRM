recordbrowser_edit_history_play_timer = null;
recordbrowser_edit_history_meta = {};

recordbrowser_edit_history_jump = function (selected_date, tab, id, form_name) {
	recordbrowser_edit_history_stop_play();
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
	if (select.options.length > 1) {
		jq('#historical_view_play_button').prop('disabled', at_start);
	}
	if (at_start) recordbrowser_edit_history_stop_play();
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

recordbrowser_edit_history_toggle_play = function (tab, id, form_name) {
	if (recordbrowser_edit_history_play_timer) {
		recordbrowser_edit_history_stop_play();
	} else {
		recordbrowser_edit_history_start_play(tab, id, form_name);
	}
}

recordbrowser_edit_history_start_play = function (tab, id, form_name) {
	var select = jq('#historical_view_pick_date')[0];
	if (!select || select.selectedIndex <= 0) return;
	var interval_field = jq('#historical_view_play_interval')[0];
	var seconds = interval_field ? parseInt(interval_field.value, 10) : 1;
	if (!seconds) seconds = 1;
	var btn = jq('#historical_view_play_button');
	btn.val(btn.attr('data-pause-label'));
	recordbrowser_edit_history_play_timer = setInterval(function() {
		var select = jq('#historical_view_pick_date')[0];
		if (!select || select.selectedIndex <= 0) {
			recordbrowser_edit_history_stop_play();
			return;
		}
		recordbrowser_edit_history_step(-1, tab, id, form_name);
	}, seconds * 1000);
}

recordbrowser_edit_history_stop_play = function () {
	if (recordbrowser_edit_history_play_timer) {
		clearInterval(recordbrowser_edit_history_play_timer);
		recordbrowser_edit_history_play_timer = null;
	}
	var btn = jq('#historical_view_play_button');
	btn.val(btn.attr('data-play-label'));
}

recordbrowser_edit_history_interval_changed = function (tab, id, form_name) {
	if (recordbrowser_edit_history_play_timer) {
		recordbrowser_edit_history_stop_play();
		recordbrowser_edit_history_start_play(tab, id, form_name);
	}
}
