class StatusBar {
    indicator = 'Base_StatusBar';
    indicator_text = 'statusbar_text';

    fadeOut = () => {
        NProgress.configure({parent: '#nano-bar'});
        NProgress.start();
        let statbar = document.getElementById(this.indicator);
        jQuery(statbar).fadeOut();
        NProgress.done();
	};

	fadeIn = () => {
        document.getElementById('dismiss').style.display = 'none';
        let statbar = document.getElementById(this.indicator);
		jQuery(statbar).fadeIn();
    };

	showMessage = (message) => {
        document.getElementById('dismiss').style.display = '';
        document.getElementById(this.indicator_text).innerHTML = message;
	}
}

let status = new StatusBar();

window.statusbar_message_t='';
window.statusbar_message=function(text){
	statusbar_message_t=text;
};

window.updateEpesiIndicatorFunction=function(){
	let statbar = document.getElementById('Base_StatusBar');

	statbar.addEventListener('click', () => {if(!Epesi.procOn)status.fadeOut()});
	statbar.style.display='none';

	Epesi.updateIndicator=function(){
		if(Epesi.procOn){
            status.fadeIn();
		}else{
			if(statusbar_message_t !== '') {
                status.showMessage(statusbar_message_t);
                statusbar_message('');
				setTimeout(status.fadeOut,5000);
			}else{
				status.fadeOut()
			};
		};
	};
};
