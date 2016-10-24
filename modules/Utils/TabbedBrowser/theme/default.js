tabbedbrowser_show_submenu = function(id) {
	var el = jq('#tabbedbrowser_'+id+'_popup');
	el.show();
	el.clonePosition("#tabbed_browser_submenu_"+id,{cloneWidth:false,cloneHeight:false,offsetTop:jq("#tabbed_browser_submenu_"+id).height()-1});
}

tabbedbrowser_hide_submenu = function(id) {
	var el = jq('#tabbedbrowser_'+id+'_popup');
	el.hide();
}
