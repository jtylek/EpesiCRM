recordbrowser_edit_history = function (tab, id, form_name) {
	var field = "historical_view_pick_date";
	new Ajax.Request('modules/Utils/RecordBrowser/edit_history.php', {
		method: 'post',
		parameters:{
			tab: tab,
			id: id,
			date: document.forms[form_name].elements[field+"[__datepicker]"].value,
			H: 			document.forms[form_name].elements[field+"[__date][H]"]?document.forms[form_name].elements[field+"[__date][H]"].value:0,
			H_small: 	document.forms[form_name].elements[field+"[__date][h]"]?document.forms[form_name].elements[field+"[__date][h]"].value:0,
			i: 			document.forms[form_name].elements[field+"[__date][i]"].value,
			a: 			document.forms[form_name].elements[field+"[__date][a]"]?document.forms[form_name].elements[field+"[__date][a]"].value:0,
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
}