utils_watchdog_set_subscribe = function(state,cat,id,element) {
	if (!JSON) return;	
	jq('#'+element).html('...');
	jq.ajax({
		type: 'POST',
		url: 'modules/Utils/Watchdog/subscribe.php', 
		data:{
			cat:JSON.stringify(cat),
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
