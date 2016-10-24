Utils_CalendarBusyReport = {
activate_dclick:function(new_ev) {
	jq('td.inter_other, td.inter_other_weekend, td.inter_today, td.inter_today_weekend').each(function() {
		var id = jq(this).attr('time');
		var obj = jq(this).attr('object');
		var f = '';
		if(typeof id=='string' && id.indexOf('_')>=0) {
			var kkk = id.indexOf('_');
			f = new_ev.replace('__TIME__',id.substr(0,kkk));
			f = f.replace('__TIMELESS__',id.substr(kkk+1));
		} else {
			f = new_ev.replace('__TIME__',id);
			f = f.replace('__TIMELESS__','0');
		}
		f = f.replace('__OBJECT__',obj);
		
		jq(this).dblclick(function(e){eval(f)});
		jq(this).bind('touchend',function(e){
		    var now = new Date().getTime();
		    var lastTouch = jq(this).attr('lastTouch') || 0;
		    var delta = now-lastTouch;
		    jq(this).attr('lastTouch',now);
		    if(delta<500)
    		    eval(f);
		});
	});
},
};
