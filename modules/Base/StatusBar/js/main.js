var statusbar_message_t='';
statusbar_message=function(text){
	statusbar_message_t=text;
};
statusbar_fade=function(){
	wait_while_null('$(\'Base_StatusBar\')','Effect.Fade(\'Base_StatusBar\',{duration:0.2});');
	statusbar_hide_selects('visible');
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
	$('epesiStatus').style.visibility='hidden';
	statbar = $('Base_StatusBar');
	statbar.onclick = Function("if(!Epesi.procOn)statusbar_fade();");
	statbar.style.display='none';
	Epesi.updateIndicator=function(){
		statbar = $('Base_StatusBar');
		if(Epesi.procOn){
			statbar.style.display='block';
			cache_pause=true;
			statusbar_hide_selects('hidden');
		}else{
			if(statusbar_message_t!='') {
				t=$('statusbar_text');
				if(t)t.innerHTML=statusbar_message_t;
				statusbar_message('');
				setTimeout('statusbar_fade()',5000);
			}else{
				statusbar_fade();
			};
			cache_pause=false;
		};
	};
};

updateEpesiIndicatorFunction();
