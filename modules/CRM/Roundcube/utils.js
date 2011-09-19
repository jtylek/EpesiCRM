var CRM_RC = {
msg_num_cache: Array(),
updating_msg_num: Array(),
update_msg_num: function(applet_id,accid,cache) {
	if(!$('mailaccount_'+applet_id+'_'+accid)) return;
	if(CRM_RC.updating_msg_num[accid] == true) {
		setTimeout('CRM_RC.update_msg_num('+applet_id+', '+accid+', 1)',1000);
		return;
	}
	if(cache && typeof CRM_RC.msg_num_cache[accid] != 'undefined') {
		$('mailaccount_'+applet_id+'_'+accid).innerHTML = CRM_RC.msg_num_cache[accid];
	} else {
		CRM_RC.updating_msg_num[accid] = true;
		new Ajax.Updater('mailaccount_'+applet_id+'_'+accid,'modules/CRM/Roundcube/applet_refresh.php',{
			method:'post',
			onComplete:function(r){
				CRM_RC.msg_num_cache[accid]=r.responseText;
				CRM_RC.updating_msg_num[accid] = false;
			},
			parameters:{acc_id:accid}});
	}
},
filled_smtp_message:'',
edit_form: function() {
	$('account_name').observe('blur',function() {
		if($('login').value=='')
			$('login').value = $('account_name').value; 
		if($('email').value=='')
			$('email').value = $('account_name').value; 
	});
	$('email').observe('blur',function() {
		if($('account_name').value=='')
			$('account_name').value = $('email').value; 
		if($('login').value=='')
			$('login').value = $('email').value; 
	});
	$('login').observe('blur',function() {
		if($('account_name').value=='')
			$('account_name').value = $('login').value; 
		if($('email').value=='')
			$('email').value = $('login').value; 
	});
	$('server').observe('blur',function() {
		if($('smtp_server').value=='')
			$('smtp_server').value = $('server').value; 
	});
	$('smtp_server').observe('blur',function() {
		if($('server').value=='')
			$('server').value = $('smtp_server').value; 
	});
	$('smtp_auth').observe('change',function() {
		if($('smtp_auth').checked && $('smtp_login').value=='' && $('smtp_pass').value=='') {
			$('smtp_login').value = $('login').value;
			$('smtp_pass').value = $('password').value;
			alert(CRM_RC.filled_smtp_message);
		}
	});
}
};

