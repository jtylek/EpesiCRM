utils_watchdog_set_subscribe = function(state,cat,id,element) {
	$(element).innerHTML = '...';
	new Ajax.Request('modules/Utils/Watchdog/subscribe.php', {
		method: 'post',
		parameters:{
			cat:Object.toJSON(cat),
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
