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
}
}