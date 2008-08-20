var Apps_MailClient = {
preview: function(id,query) {
    var iframe = $(id+'_body');
    if(!iframe) return;
    iframe.src = 'modules/Apps/MailClient/preview.php?'+query;
},
progress_bar: {
get: function(parent,name) {
	var progr = $(parent).getElementsByClassName(name)[0];
	if(!progr) {
		var progr = document.createElement('div');
		progr.className = name;
		var progr_t = document.createElement('div');
		progr.appendChild(progr_t);
		var progr_p = document.createElement('div');
		progr_p.className = "mail_progressbar_out";
		progr.appendChild(progr_p);
		var progr_pi = document.createElement('div');
		progr_pi.className = "mail_progressbar_in";
		progr_p.appendChild(progr_pi);
		progr_pi.style.width = 0;
		$(parent).appendChild(progr);
	}
	return progr;
},
set_text: function(parent,name,t) {
	var progr = Apps_MailClient.progress_bar.get(parent,name);
	progr.getElementsByTagName('div')[0].innerHTML = t;
},
set_progress: function(parent,name,p) {
	var progr = Apps_MailClient.progress_bar.get(parent,name);
	var pr = progr.getElementsByTagName('div')[1].firstChild;
	pr.style.width = p+'%';
}
},
check_mail_f: function(me,name) {
	if(Apps_MailClient.check_mail_bind) {
		if($(name).style.display=='block') {
			var today = new Date();
			var ch = document.createElement('iframe');
			ch.id = name+'X';
			ch.src = 'modules/Apps/MailClient/checknew.php?id='+name+'&t='+today.getTime();
			ch.style.display = "none";

			document.body.appendChild(ch);
		} else {
			setTimeout(Apps_MailClient.check_mail_bind,100);
		}
	}
},
check_mail_bind:null,
check_mail_button_observe: function(name) {
	if(Apps_MailClient.check_mail_destroy_bind) {
		Apps_MailClient.check_mail_destroy_bind();
	}
	Apps_MailClient.check_mail_bind = Apps_MailClient.check_mail_f.bindAsEventListener(Apps_MailClient,name);
	Event.observe(name+'b','click',Apps_MailClient.check_mail_bind);
	Apps_MailClient.check_mail_destroy_bind = Apps_MailClient.check_mail_destroy_f.bindAsEventListener(Apps_MailClient,name);
	document.observe('e:loading',Apps_MailClient.check_mail_destroy_bind);
},
check_mail_destroy_bind:null,
check_mail_destroy_f:function(em,name) {
	var x=$(name+'X');
	if(x) x.parentNode.removeChild(x);
	Event.stopObserving(name+'b','click',Apps_MailClient.check_mail_bind);
	delete(Apps_MailClient.check_mail_bind);
	document.stopObserving('e:loading',Apps_MailClient.check_mail_destroy_bind);
	delete(Apps_MailClient.check_mail_destroy_bind);
},
hide:function(name) {
	var x=$(name+'X');
	x.parentNode.removeChild(x);
	leightbox_deactivate(name);
	$(name+'L').style.display='none';
},
show_hide_button:function(name) {
	$(name+'L').style.display='block';
}
};
