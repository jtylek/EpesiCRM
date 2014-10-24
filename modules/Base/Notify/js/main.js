function Base_Notify__refresh (cid) {
	if (Base_Notify__disable_refresh(false)) return;

	jq.getJSON('modules/Base/Notify/refresh.php?cid='+cid, function(json){
		if (typeof json === 'undefined' || jq.isEmptyObject(json) || json.length==0) return;
		if (Base_Notify__disable_refresh(typeof json.disable !== 'undefined')) return;		

		jq.each(json, function(i, m) {
			setTimeout(function(){
				if (typeof m.timeout !== 'undefined') notify.config({pageVisibility: false, autoClose: m.timeout});
				Base_Notify__notify(m.title, m.opts);			
			}, i*500);
		});

	});
}

function Base_Notify__notify (title, opts, check) {
	if (Base_Notify__disable_refresh(false, check)) return;

	if (notify.permissionLevel() === notify.PERMISSION_DEFAULT) {
		notify.requestPermission(function (permission) {
			if (permission === notify.PERMISSION_GRANTED) {
				var n = notify.createNotification(title, opts);
			}
		});
	}
	else if (notify.permissionLevel() === notify.PERMISSION_GRANTED) {
		var n = notify.createNotification(title, opts);
	}
}

function Base_Notify__disable_refresh (force, check) {
	disable = !notify.isSupported || (notify.permissionLevel() === notify.PERMISSION_DENIED);

	if (disable && check) Base_Notify__alert();

	if (disable || force) {
		clearInterval(Base_Notify__interval);
		Base_Notify__interval = 0;
	}

	return disable || force;
}



