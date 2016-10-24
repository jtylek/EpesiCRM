tabbed_browser_switch = function(id,max,elem,path){
	var x = jq('#'+path+"_d"+id);
	var parent_menu;
	if(x.length) {
		for(var i=0; i<max; i++){
			var y = jq('#'+path+"_d"+i);
			if(y.length) y.hide();
			jQuery('#'+path+"_c"+i).parent().removeClass("active");
			parent_menu = jq('#'+path+"_c"+i).attr("parent_menu");
			if (parent_menu)
				jQuery("#tabbed_browser_submenu_"+parent_menu).parent().removeClass("active");
		}
		x.show();
		jQuery('#'+path+"_c"+id).parent().addClass("active");
		parent_menu = jq('#'+path+"_c"+id).attr("parent_menu");
		if (parent_menu)
			jQuery("#tabbed_browser_submenu_"+parent_menu).addClass("active");
	} else eval(elem.getAttribute("original_action"));
};

tabbed_browser_hide = function(path,id){
	var x = jq('#'+path+"_d"+id);
	var y = jq('#'+path+"_c"+id);
	if(x.length && y.length) {
		x.hide();
		y.hide();
	}
};

tabbed_browser_show = function(path,id){
	var x = jq('#'+path+"_d"+id);
	var y = jq('#'+path+"_c"+id);
	if(x.length && y.length) {
		x.show();
		y.show();
	}
};