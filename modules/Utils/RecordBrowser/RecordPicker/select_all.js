var RecordPicker_select_all = function(select,path,message) {
	Epesi.updateIndicatorText(message);
	Epesi.procOn++;
	Epesi.updateIndicator();
	jq.ajax('modules/Utils/RecordBrowser/RecordPicker/select_all.php', {
		method: 'post',
		data:{
			select: Object.toJSON(select),
			path: Object.toJSON(path),
			cid: Epesi.client_id
		},
		success:function(t) {
			eval(t);
		}
	});
}
