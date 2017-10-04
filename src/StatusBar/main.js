window.statusbar_message_t='';
window.statusbar_message=function(text){
	statusbar_message_t=text;
};

window.statusbar_fade=function(){
    NProgress.configure({parent: '#nano-bar'});
    NProgress.start();
    let statbar = document.getElementById('Base_StatusBar');
    jQuery(statbar).fadeOut();
    NProgress.done();
};

window.updateEpesiIndicatorFunction=function(){
	let statbar = document.getElementById('Base_StatusBar');

	statbar.addEventListener('click', () => {if(!Epesi.procOn)statusbar_fade()});
	statbar.style.display='none';

	Epesi.updateIndicator=function(){
        let statbar = document.getElementById('Base_StatusBar');
		if(Epesi.procOn){
            document.getElementById('dismiss').style.display = 'none';
			jQuery(statbar).fadeIn();
		}else{
			if(statusbar_message_t !== '') {
                document.getElementById('dismiss').style.display = '';
                document.getElementById('statusbar_text').innerHTML = statusbar_message_t;
				statusbar_message('');
				setTimeout('statusbar_fade()',5000);
			}else{
				statusbar_fade();
			};
		};
	};
};
