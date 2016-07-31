Utils_RecordBrowser_Filters = {
	show: function (tab, group_id) {
		jq('#recordbrowser_filters_'+group_id+', #hide_filter_b_'+group_id).show();
		jq('#show_filter_b_'+group_id).hide();
		Utils_RecordBrowser_Filters.save_visibility(tab,1);
	},
	hide: function (tab, group_id) {
		jq('#recordbrowser_filters_'+group_id+', #hide_filter_b_'+group_id).hide();
		jq('#show_filter_b_'+group_id).show();
		Utils_RecordBrowser_Filters.save_visibility(tab,0);
	},
	save_visibility: function (tab, visible) {
		jq.ajax({
            type: 'POST',
            url: 'modules/Utils/RecordBrowser/Filters/save_filters.php',
            data:{
            	tab:JSON.stringify(tab),
				visible:JSON.stringify(visible),
				cid: Epesi.client_id
            },
            success:function(t) {
            }
        });
	}
}
