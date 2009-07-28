var Apps_MailClient = {
preview: function(id,query,msg_id) {
    var iframe = $(id+'_body');
    if(!iframe) return;
    iframe.src = 'modules/Apps/MailClient/preview.php?'+query;
	$('apps_mailclient_msg_'+msg_id).style.fontWeight='normal';
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
check_mail: Class.create({
f: function(me,name) {
	if(this.bind) {
		if($(name).style.display=='block') {
			var today = new Date();
			var ch = document.createElement('iframe');
			ch.id = name+'X';
			ch.src = 'modules/Apps/MailClient/checknew.php?id='+name+'&t='+today.getTime();
			ch.style.display = "none";

			document.body.appendChild(ch);
		} else {
			setTimeout(this.bind,100);
		}
	}
},
bind:null,
initialize: function(name) {
	if(this.destroy_bind) {
		this.destroy_bind();
	}
	this.bind = this.f.bindAsEventListener(this,name);
	Event.observe(name+'b','click',this.bind);
	this.destroy_bind = this.destroy_f.bindAsEventListener(this,name);
	document.observe('e:loading',this.destroy_bind);
},
destroy_bind:null,
destroy_f:function(em,name) {
	var x=$(name+'X');
	if(x) x.parentNode.removeChild(x);
	Event.stopObserving(name+'b','click',this.bind);
	delete(this.bind);
	document.stopObserving('e:loading',this.destroy_bind);
	delete(this.destroy_bind);
},
}),

hide:function(name) {
	var x=$(name+'X');
	x.parentNode.removeChild(x);
	leightbox_deactivate(name);
	$(name+'L').style.display='none';
},
show_hide_button:function(name) {
	$(name+'L').style.display='block';
},
actions_set_id:function(id) {
	var href = $('mail_client_actions_view_source').getAttribute('tpl_href');
	$('mail_client_actions_view_source').setAttribute('href',href.replace('__MSG_ID__',id));
	$('mail_client_actions_move_msg_id').value=id;
	var i=0;
	var e;
	while(e=$('mail_client_external_actions_'+i)) {
		e.setAttribute('onClick',e.getAttribute('tpl_onClick').replace('__MSG_ID__',id)+';leightbox_deactivate(\'mail_actions\')');
		i++;
	}
},

msg_num_cache: Array(),
updating_msg_num: Array(),
update_msg_num: function(applet_id,accid,cache) {
	if(!$('mailaccount_'+applet_id+'_'+accid)) return;
	if(Apps_MailClient.updating_msg_num[accid] == true) {
		setTimeout('Apps_MailClient.update_msg_num('+applet_id+', '+accid+', 1)',1000);
		return;
	}
	if(cache && typeof Apps_MailClient.msg_num_cache[accid] != 'undefined') {
		$('mailaccount_'+applet_id+'_'+accid).innerHTML = Apps_MailClient.msg_num_cache[accid];
	} else {
		Apps_MailClient.updating_msg_num[accid] = true;
		new Ajax.Updater('mailaccount_'+applet_id+'_'+accid,'modules/Apps/MailClient/applet_refresh.php',{
			method:'post',
			onComplete:function(r){
				Apps_MailClient.msg_num_cache[accid]=r.responseText;
				Apps_MailClient.updating_msg_num[accid] = false;
			},
			parameters:{acc_id:accid}});
	}
},
from_change: function(v) {
	if(v=='pm') {
		$("apps_mailclient_to_addr").disable();
	} else {
		$("apps_mailclient_to_addr").enable();
	}
},
addressbook_hidden:false,
addressbook_toggle: function() {
	if(Apps_MailClient.addressbook_hidden) {
		Effect.SlideDown('apps_mailclient_addressbook',{duration:0.3});
		Apps_MailClient.addressbook_hidden = false;
	} else {
		Effect.SlideUp('apps_mailclient_addressbook',{duration:0.3});
		Apps_MailClient.addressbook_hidden = true;
	}
},
addressbook_toggle_init: function() {
	if(Apps_MailClient.addressbook_hidden) {
		$('apps_mailclient_addressbook').hide();
	} else {
		$('apps_mailclient_addressbook').show();
	}
},

filters_match_change: function(val) {
	if(val=='allmessages')
		$('mail_filters_rules_block').hide();
	else
		$('mail_filters_rules_block').show();
},
filter_remove: function(what,val) {
	var ids = $('mail_filters_'+what+'s_ids').value.split(',');
	if(ids.size()<2) return;
	var new_ids = Array();
	for(var i=0; i<ids.size(); i++) {
	    if(ids[i]!=val)
		new_ids.push(ids[i]);
	}
	$('mail_filters_'+what+'_'+val).remove();
	$('mail_filters_'+what+'s_ids').value = new_ids.join(',');
},
filter_add: function(what) {
	var ids = $('mail_filters_'+what+'s_ids').value.split(',');
	var max = 0;
	for(var i=0; i<ids.size(); i++) {
	    var j = parseInt(ids[i]);
	    if(max<j) max = j;
	}
	max = max+1;
	ids.push(max);
	var new_rule = $('mail_filters_'+what+'s_template').innerHTML.replace(new RegExp('template','g'),max);
	var x = document.createElement('span'); //doesn't work with appendChild so this ugly work around...
	x.innerHTML = new_rule;
	$('mail_filters_'+what+'s_elements').appendChild(x);
//	$('mail_filters_'+what+'s_elements').innerHTML = $('mail_filters_'+what+'s_elements').innerHTML+new_rule; //doesn't work with appendChild so this other ugly work around... with bug: clears form elements
	$('mail_filters_'+what+'s_ids').value = ids.join(',');
},
filter_action_change: function(id,action) {
	if(action=='delete' || action=='read') {
		$('mail_filter_action_value_'+id).hide();
		$('mail_filter_action_value_box_'+id).hide();
	} else if(action=='forward' || action=='forward_delete') {
		$('mail_filter_action_value_'+id).show();
		$('mail_filter_action_value_box_'+id).hide();
	} else { //move,copy
		$('mail_filter_action_value_'+id).hide();
		$('mail_filter_action_value_box_'+id).show();
	}
},
cache_mailboxes_working:false,
cache_mailboxes_start: function() {
	if(Apps_MailClient.cache_mailboxes_working) return;
	Apps_MailClient.cache_mailboxes_working=true,
	Apps_MailClient.cache_mailboxes();
},
cache_request: null,
cache_mailboxes: function() {
	if(Apps_MailClient.cache_request != null) return;
	if(Apps_MailClient.cache_timeout != null) {
		clearTimeout(Apps_MailClient.cache_timeout);
		Apps_MailClient.cache_timeout = null;
	}
	Apps_MailClient.cache_request = new Ajax.Request('modules/Apps/MailClient/cache.php', {
			parameters: {
				cid: Epesi.client_id
			},
			method: 'post',
			onComplete: function(t) {
				Apps_MailClient.cache_request = null;
			},
			onSuccess: function(t) {
			},
			onException: function(t,e) {
				throw(e);
			},
			onFailure: function(t) {
				alert('Failure ('+t.status+')');
				Epesi.text(t.responseText,'error_box','p');
			}
		});
	
},
refresh_ui: function() {
	if($('mail_view_body'))
		Epesi.request('');
},
cache_timeout:null,
queue_cache: function() {
	Apps_MailClient.cache_timeout = setTimeout('Apps_MailClient.cache_mailboxes()',30000);
}
};
