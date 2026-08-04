var statusbar_message_t='';
statusbar_message=function(text){
	statusbar_message_t=text;
};
statusbar_fade_count = 0;
function statusbar_fade_out(el, seconds) {
	if (!el) { setTimeout(function(){ statusbar_fade_out(document.getElementById('Base_StatusBar'), seconds); }, 200); return; }
	var oldOpacity = el.style.opacity;
	el.style.transition = 'opacity ' + seconds + 's';
	el.style.opacity = '0';
	setTimeout(function(){
		el.style.display = 'none';
		el.style.opacity = oldOpacity;
		el.style.transition = '';
	}, seconds*1000);
}
statusbar_fade=function(fade_count){
	if (fade_count && statusbar_fade_count!=fade_count) return;
	var seconds = 0.2;
	statusbar_fade_out(document.getElementById('Base_StatusBar'), seconds);
	document.getElementById('Base_StatusBar').onclick = null;
	statusbar_hide_selects('visible');
	setTimeout('statusbar_fade_double_check('+statusbar_fade_count+')',seconds*1000+50);
};
statusbar_fade_double_check = function(fade_count) {
	if (fade_count && statusbar_fade_count!=fade_count) document.getElementById('Base_StatusBar').style.display='block';
	else document.getElementById('Base_StatusBar').onclick = Function("if(!Epesi.procOn)statusbar_fade();");
};
statusbar_hide_selects=function(visibility){
	if(navigator.userAgent.toLowerCase().indexOf('msie')>=0){
	selects = document.getElementsByTagName('select');
	for(i = 0; i < selects.length; i++) {
		selects[i].style.visibility = visibility;
	}}
};
updateEpesiIndicatorFunction=function(){
	Epesi.indicator_text='statusbar_text';
	Epesi.indicator='Base_StatusBar';
	statbar = document.getElementById('Base_StatusBar');
	if (!statbar) {
		setTimeout('updateEpesiIndicatorFunction();',3000);
		return;
	}
	document.getElementById('epesiStatus').style.visibility='hidden';
	document.getElementById('main_content').style.display='';
	statbar.onclick = Function("if(!Epesi.procOn)statusbar_fade();");
	statbar.style.display='none';
	Epesi.updateIndicator=function(){
		statbar = document.getElementById('Base_StatusBar');
		statusbar_fade_count++;
		if(Epesi.procOn){
            document.getElementById('dismiss').style.display='none';
			statbar.style.display='block';
			cache_pause=true;
			statusbar_hide_selects('hidden');
		}else{
			if(statusbar_message_t!='') {
                document.getElementById('dismiss').style.display='';
				t=document.getElementById('statusbar_text');
				if(t)t.innerHTML=statusbar_message_t;
				// Base_StatusBarCommon::message()'s $type ('normal'/'warning'/'error')
				// ends up as a class on the injected "<div class='message TYPE'>" -
				// an error is worth reading, not a 5-second toast the user has to
				// react to before it vanishes on its own; leave it up to the
				// existing "click anywhere to dismiss" (statusbar_fade(), wired in
				// statusbar_fade_double_check() below) instead of auto-hiding it.
				var is_error = statusbar_message_t.indexOf('message error')!=-1;
				statusbar_message('');
				if (!is_error) setTimeout('statusbar_fade('+statusbar_fade_count+')',5000);
			}else{
				statusbar_fade();
			};
			cache_pause=false;
		};
	};
};

updateEpesiIndicatorFunction();
