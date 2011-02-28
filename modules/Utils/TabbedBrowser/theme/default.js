tabbedbrowser_show_submenu = function(id) {
	var el = $('tabbedbrowser_'+id+'_popup');
	el.style.display="";
	el.clonePosition("tabbed_browser_submenu_"+id,{setWidth:false,setHeight:false,offsetTop:$("tabbed_browser_submenu_"+id).getHeight()});
}

tabbedbrowser_hide_submenu = function(id) {
	var el = $('tabbedbrowser_'+id+'_popup');
	el.style.display="none";
}
