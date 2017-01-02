var RecordPicker_select_all = function(select,path,message) {
	Epesi.updateIndicatorText(message);
	Epesi.procOn++;
	Epesi.updateIndicator();
	jq.ajax('modules/Utils/RecordBrowser/RecordPickerFS/select_all.php', {
		method: 'post',
		data:{
			select: JSON.stringify(select),
			path: JSON.stringify(path),
			cid: Epesi.client_id
		},
		success:function(t) {
			eval(t);
		}
	});
}
