utils_recordbrowser_set_favorite = function(state,tab,id,element) {
	jq(element).text('...');
	jq.ajax('modules/Utils/RecordBrowser/favorites.php', {
		method: 'post',
		data:{
			tab:JSON.stringify(tab),
			id:JSON.stringify(id),
			state:JSON.stringify(state),
			element:JSON.stringify(element),
			cid: Epesi.client_id
		},
		success:function(t) {
			eval(t);
		}
	});
};
