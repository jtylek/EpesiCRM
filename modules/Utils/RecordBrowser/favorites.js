utils_recordbrowser_set_favorite = function(state,tab,id,element) {
	jq(element).text('...');
	jq.ajax('modules/Utils/RecordBrowser/favorites.php', {
		method: 'post',
		data:{
			tab:Object.toJSON(tab),
			id:Object.toJSON(id),
			state:Object.toJSON(state),
			element:Object.toJSON(element),
			cid: Epesi.client_id
		},
		success:function(t) {
			eval(t);
		}
	});
};
