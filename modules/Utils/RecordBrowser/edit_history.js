recordbrowser_edit_history_jump = function (selected_date, tab, id, form_name) {
	jq('#historical_view_pick_date').val(selected_date);
	recordbrowser_edit_history(tab, id, form_name);
}

recordbrowser_edit_history = function (tab, id, form_name) {
	var field = "historical_view_pick_date";
	jq.ajax({
		type: 'post',
		url: 'modules/Utils/RecordBrowser/edit_history.php', 
		data:{
			tab: tab,
			id: id,
			date: document.forms[form_name].elements[field].value,
			cid: Epesi.client_id
		},
		success:function(response) {
			eval(response);
		}
	});
}
