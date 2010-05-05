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
}
};

