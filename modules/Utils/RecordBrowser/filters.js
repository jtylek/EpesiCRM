rb_show_filters = function(tab, filter_id){
	$('recordbrowser_filters_'+filter_id).style.display='block';
	$('show_filter_b_'+filter_id).style.display='none';
	$('hide_filter_b_'+filter_id).style.display='block';
	rb_save_filter(tab,1);
}

rb_hide_filters = function(tab, filter_id){
	$('recordbrowser_filters_'+filter_id).style.display='none';
	$('hide_filter_b_'+filter_id).style.display='none';
	$('show_filter_b_'+filter_id).style.display='block';
	rb_save_filter(tab,0);
}

rb_save_filter = function (tab,value) {
	new Ajax.Request('modules/Utils/RecordBrowser/filters.php', {
		method: 'post',
		parameters:{
			tab:Object.toJSON(tab),
			value:Object.toJSON(value),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
		}
	});
}