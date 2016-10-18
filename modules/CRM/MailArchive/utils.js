var CRM_MailArchive = {
cache: Array(),
updating: Array(),
update_last_messages: function(applet_id,accid,folders,period,link,cache) {
	if(!jq('#mailaccount_'+applet_id+'_'+accid).length) return;
	if(CRM_MailArchive.updating[accid] == true) {
		return;
	}
	if(jq('#mailaccount_'+applet_id+'_'+accid+':hover').length != 0) {
		setTimeout('CRM_MailArchive.update_last_messages('+applet_id+', '+accid+', \''+JSON.stringify(folders)+'\','+period+','+JSON.stringify(link)+',1)',1000);
		return;
	}
	if(cache && typeof CRM_MailArchive.cache[applet_id] != 'undefined' && typeof CRM_MailArchive.cache[applet_id][accid] != 'undefined') {
		jq('#mailaccount_'+applet_id+'_'+accid).html(CRM_MailArchive.cache[applet_id][accid]);
		CRM_MailArchive.refresh_accordion(applet_id);
	} else {
		CRM_MailArchive.updating[accid] = true;
		jq.post('modules/CRM/MailArchive/applet_refresh.php',{acc_id:accid,ipath:folders,p:period,l:link},
            function(data) {
                CRM_MailArchive.cache[applet_id] = {};
				CRM_MailArchive.cache[applet_id][accid] = data;
                CRM_MailArchive.updating[accid] = false;
                jq('#mailaccount_'+applet_id+'_'+accid).html(data);
				CRM_MailArchive.refresh_accordion(applet_id);
            });
	}
},
refresh_accordion:function(applet_id) {
	var isAccordion = jq("#mail_archive_accordion_"+applet_id).hasClass("ui-accordion");
	if(!isAccordion) {
		jq("#mail_archive_accordion_"+applet_id).accordion({header: "h3",heightStyle: "content"});
	} else {
		jq("#mail_archive_accordion_"+applet_id).accordion('refresh');
	}
}
};
