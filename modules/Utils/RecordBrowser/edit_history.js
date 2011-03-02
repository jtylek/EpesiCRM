recordbrowser_edit_history_jump = function (selected_date, tab, id, form_name) {
	$('historical_view_pick_date').value = selected_date;
	recordbrowser_edit_history(tab, id, form_name);
}

recordbrowser_edit_history = function (tab, id, form_name) {
	var field = "historical_view_pick_date";
	new Ajax.Request('modules/Utils/RecordBrowser/edit_history.php', {
		method: 'post',
		parameters:{
			tab: tab,
			id: id,
			date: document.forms[form_name].elements[field].value,
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
}
