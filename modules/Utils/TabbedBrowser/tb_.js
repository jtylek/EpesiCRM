tabbed_browser_switch = function(id,max,elem,path){
	var x = $(path+"_d"+id);
	var parent_menu;
	if(x) {
		for(var i=0; i<max; i++){
			var y = $(path+"_d"+i);
			if(y) y.style.display="none";
			jQuery($(path+"_c"+i)).parent().removeClass("active");
			parent_menu = $(path+"_c"+i).getAttribute("parent_menu");
			if (parent_menu)
				jQuery($("tabbed_browser_submenu_"+parent_menu)).parent().removeClass("active");
		}
		x.style.display="block";
		jQuery($(path+"_c"+id)).parent().addClass("active");
		parent_menu = $(path+"_c"+id).getAttribute("parent_menu");
		if (parent_menu)
			jQuery($("tabbed_browser_submenu_"+parent_menu)).addClass("active");
	} else eval(elem.getAttribute("original_action"));
};

tabbed_browser_hide = function(path,id){
	var x = $(path+"_d"+id);
	var y = $(path+"_c"+id);
	if(x && y) {
		x.hide();
		y.hide();
	}
};

tabbed_browser_show = function(path,id){
	var x = $(path+"_d"+id);
	var y = $(path+"_c"+id);
	if(x && y) {
		x.show();
		y.show();
	}
};