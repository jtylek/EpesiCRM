window.statusbar_message_t='';
window.statusbar_message=function(text){
	statusbar_message_t=text;
};

window.statusbar_fade_count = 0;
window.statusbar_fade=function(fade_count){
    NProgress.configure({parent: '#nano-bar'});
    NProgress.start();
	if (fade_count && statusbar_fade_count !== fade_count) return;
	let seconds = 0.2;
    let statbar = document.getElementById('Base_StatusBar');
    jQuery(statbar).fadeOut();
    NProgress.done();
	jq('#Base_StatusBar').get(0).onclick = null;
	setTimeout('statusbar_fade_double_check('+statusbar_fade_count+')',seconds*1000+50);
};
window.statusbar_fade_double_check = function(fade_count) {
    let statbar = document.getElementById('Base_StatusBar');

    if (fade_count && statusbar_fade_count !== fade_count) statbar.style.display = '';
	else 	statbar.onclick = Function("if(!Epesi.procOn)statusbar_fade();");
};
window.updateEpesiIndicatorFunction=function(){
	let statbar = document.getElementById('Base_StatusBar');

    statbar.onclick = Function("if(!Epesi.procOn)statusbar_fade();");
	statbar.style.display='none';

	Epesi.updateIndicator=function(){
        let statbar = document.getElementById('Base_StatusBar');
		statusbar_fade_count++;
		if(Epesi.procOn){
            document.getElementById('dismiss').style.display = 'none';
			statbar.style.display='block';
		}else{
			if(statusbar_message_t !== '') {
                document.getElementById('dismiss').style.display = '';
                document.getElementById('statusbar_text').innerHTML = statusbar_message_t;
				statusbar_message('');
				setTimeout('statusbar_fade('+statusbar_fade_count+')',5000);
			}else{
				statusbar_fade();
			};
		};
	};
};
