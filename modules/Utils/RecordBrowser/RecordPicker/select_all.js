var RecordPicker_select_all = function(select,path,tab,message) {
	Epesi.updateIndicatorText(message);
	Epesi.procOn++;
	Epesi.updateIndicator();
	new Ajax.Request('modules/Utils/RecordBrowser/RecordPicker/select_all.php', {
		method: 'post',
		parameters:{
			select: Object.toJSON(select),
			path: Object.toJSON(path),
			tab: Object.toJSON(tab),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
}
