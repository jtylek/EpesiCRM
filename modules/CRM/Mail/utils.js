var CRM_Mail = {
msg_num_cache: Array(),
updating_msg_num: Array(),
update_msg_num: function(applet_id,accid,cache) {
	if(!jq('#mailaccount_'+applet_id+'_'+accid).length) return;
	if(CRM_Mail.updating_msg_num[accid] == true) {
		setTimeout('CRM_Mail.update_msg_num('+applet_id+', '+accid+', 1)',1000);
		return;
	}
	if(cache && typeof CRM_Mail.msg_num_cache[accid] != 'undefined') {
		jq('#mailaccount_'+applet_id+'_'+accid).html(CRM_Mail.msg_num_cache[accid]);
	} else {
		CRM_Mail.updating_msg_num[accid] = true;
		jq.post('modules/CRM/Mail/applet_refresh.php',{acc_id:accid},
            function(data) {
                CRM_Mail.msg_num_cache[accid] = data;
                CRM_Mail.updating_msg_num[accid] = false;
                jq('#mailaccount_'+applet_id+'_'+accid).html(data);
            });
	}
},
filled_smtp_message:'',
edit_form: function() {
	jq('#account_name').on('blur',function() {
		if(jq('#login').val() == '')
			jq('login').val(jq('#account_name').val());
		if(jq('#email').val()=='')
			jq('#email').val(jq('#account_name').val());
	});
	jq('#email').on('blur',function() {
		if(jq('#account_name').val()=='')
			jq('#account_name').val(jq('#email').val());
		if(jq('#login').val()=='')
			jq('#login').val(jq('#email').val());
	});
	jq('#login').on('blur',function() {
		if(jq('#account_name').val()=='')
			jq('#account_name').val(jq('#login').val());
		if(jq('#email').val()=='')
			jq('#email').val(jq('#login').val());
	});
	jq('#server').on('blur',function() {
		if(jq('#smtp_server').val()=='')
			jq('#smtp_server').val(jq('#server').val());
	});
	jq('#smtp_server').on('blur',function() {
		if(jq('#server').val()=='')
			jq('#server').val(jq('#smtp_server').val());
	});
	jq('#smtp_auth').on('change',function() {
		if(jq('#smtp_auth').val() && jq('#smtp_login').val()=='' && jq('#smtp_pass').val()=='') {
			jq('#smtp_login').val(jq('#login').val());
			jq('#smtp_pass').val(jq('#password').val());
			alert(CRM_Mail.filled_smtp_message);
		}
	});
},
    smtp_auth_change: function (val) {
        var disabled = !val;
        jq('#smtp_login').prop('disabled', disabled);
        jq('#smtp_pass').prop('disabled', disabled);
        jq('#smtp_security').prop('disabled', disabled);
    }
};
