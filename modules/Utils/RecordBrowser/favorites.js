utils_recordbrowser_set_favorite = function(state,tab,id,element) {
	document.getElementById(element).innerHTML = '...';
	jQuery.ajax('modules/Utils/RecordBrowser/favorites.php', {
		method: 'post',
		data:{
			tab:JSON.stringify(tab),
			id:JSON.stringify(id),
			state:JSON.stringify(state),
			element:JSON.stringify(element),
			cid: Epesi.client_id
		},
		dataType: 'text',
		success:function(responseText) {
			eval(responseText);
		}
	});
};
