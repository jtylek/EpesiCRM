utils_recordbrowser_set_favorite = function(state,tab,id,element) {
	$(element).innerHTML = '...';
	new Ajax.Request('modules/Utils/RecordBrowser/favorites.php', {
		method: 'post',
		parameters:{
			tab:Object.toJSON(tab),
			id:Object.toJSON(id),
			state:Object.toJSON(state),
			element:Object.toJSON(element),
			cid: Epesi.client_id
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
};
