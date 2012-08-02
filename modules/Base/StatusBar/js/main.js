var statusbar_message_t='';
statusbar_message=function(text){
	statusbar_message_t=text;
};
statusbar_fade_count = 0;
statusbar_fade=function(fade_count){
	if (fade_count && statusbar_fade_count!=fade_count) return;
	var seconds = 0.2;
	wait_while_null('$(\'Base_StatusBar\')','Effect.Fade(\'Base_StatusBar\',{duration:'+seconds+'});');
	$('Base_StatusBar').onclick = null;
	statusbar_hide_selects('visible');
	setTimeout('statusbar_fade_double_check('+statusbar_fade_count+')',seconds*1000+50);
};
statusbar_fade_double_check = function(fade_count) {
	if (fade_count && statusbar_fade_count!=fade_count) $('Base_StatusBar').style.display='block';
	else $('Base_StatusBar').onclick = Function("if(!Epesi.procOn)statusbar_fade();");
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
	statbar = $('Base_StatusBar');
	if (!statbar) {
		setTimeout('updateEpesiIndicatorFunction();',3000);
		return;
	}
	$('epesiStatus').style.visibility='hidden';
	$('main_content').style.display='';
	statbar.onclick = Function("if(!Epesi.procOn)statusbar_fade();");
	statbar.style.display='none';
	Epesi.updateIndicator=function(){
		statbar = $('Base_StatusBar');
		statusbar_fade_count++;
		if(Epesi.procOn){
			statbar.style.display='block';
			cache_pause=true;
			statusbar_hide_selects('hidden');
		}else{
			if(statusbar_message_t!='') {
				t=$('statusbar_text');
				if(t)t.innerHTML=statusbar_message_t;
				statusbar_message('');
				setTimeout('statusbar_fade('+statusbar_fade_count+')',5000);
			}else{
				statusbar_fade();
			};
			cache_pause=false;
		};
	};
};

updateEpesiIndicatorFunction();
