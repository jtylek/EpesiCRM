tabbedbrowser_show_submenu = function(id) {
	var el = document.getElementById('tabbedbrowser_'+id+'_popup');
	el.style.display="";
	jQuery(el).clonePosition(document.getElementById("tabbed_browser_submenu_"+id),{setWidth:false,setHeight:false,offsetTop:document.getElementById("tabbed_browser_submenu_"+id).offsetHeight-1});
}

tabbedbrowser_hide_submenu = function(id) {
	var el = document.getElementById('tabbedbrowser_'+id+'_popup');
	el.style.display="none";
}
