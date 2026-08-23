var Base_Notify = {
	interval: 0,
	disabled: 0,
	disabled_message: 'Notifications disabled!',
	working: 0,
	
	init: function(refresh_interval, disabled_message) {
		this.set_interval(refresh_interval);
		this.disabled_message = disabled_message;
		this.refresh();
	},
	
	set_interval: function (t) {
		if (!this.is_active()) return;
		
		clearInterval(this.interval);
				
		this.interval = setInterval(function () {Base_Notify.refresh();}, t);
	},
	
	refresh: function () {
		if (!this.is_active()) return;

		if(Base_Notify.working) return;
		Base_Notify.working = 1;

		jq.getJSON('modules/Base/Notify/refresh.php', function(json){
			Base_Notify.working = 0;

			if (typeof json === 'undefined' || jq.isEmptyObject(json)) return;
			if (typeof json.disable !== 'undefined') {
				Base_Notify.disable();
				return;	
			}

			if (typeof json.messages === 'undefined') return;
			
			jq.each(json.messages, function(i, m) {
				setTimeout(function(){
					Base_Notify.notify(m.title, m.opts, m.timeout);			
				}, i*500);
			});
		});		
	},
	
	notify: function (title, opts, timeout) {
		if (!this.is_active(true)) return;
		
		var n;
		
		if (Notification.permission === 'default') {
			Notification.requestPermission().then(function (permission) {
				Base_Notify.notify(title, opts, timeout);
			});
		}
		else if (Notification.permission === 'granted') {
			n = new Notification(title, opts);
		}

		if (n && jq.isNumeric(timeout) && timeout > 5000) {
			setInterval(n.close.bind(n), timeout);
		}
	},
	
	is_active: function (show_alert) {
		if (this.disabled) return false;

		if (Notification.permission === 'granted' || Notification.permission === 'default') return true;
		
		if (show_alert) {
			var message = this.disabled_message
			if (Notification.permission === 'notsupported') {
				message = 'Notifications not supported';
			}
			alert(message);
		}
		
		return false;
	},

	disable: function () {
		clearInterval(this.interval);
		this.interval = 0;
		this.disabled = 1;
	}
};
