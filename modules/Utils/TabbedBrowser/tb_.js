tabbed_browser_switch = function(id,max,elem,path){
	var x = $(path+"_d"+id);
	var parent_menu;
	if(x) {
		for(var i=0; i<max; i++){
			var y = $(path+"_d"+i);
			if(y) y.style.display="none";
			$(path+"_c"+i).className="tabbed_browser_unselected";
			parent_menu = $(path+"_c"+i).getAttribute("parent_menu")
			if (parent_menu)
				$("tabbed_browser_submenu_"+parent_menu).className="tabbed_browser_unselected";
		}
		x.style.display="block";
		$(path+"_c"+id).className="tabbed_browser_selected";
		parent_menu = $(path+"_c"+id).getAttribute("parent_menu")
		if (parent_menu)
			$("tabbed_browser_submenu_"+parent_menu).className="tabbed_browser_selected";
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