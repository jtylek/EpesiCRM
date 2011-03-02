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

jump_to_record_id = function (tab) {
	if ($("jump_to_record_input").style.display=="")
		$("jump_to_record_input").style.display = "none";
	else
		$("jump_to_record_input").style.display = "";
	focus_by_id("jump_to_record_input");
}
