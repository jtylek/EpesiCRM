tabbed_browser_switch = function(id,max,elem,path){
	var x = document.getElementById(path+"_d"+id);
	var parent_menu;
	if(x) {
		for(var i=0; i<max; i++){
			var y = document.getElementById(path+"_d"+i);
			if(y) y.style.display="none";
			document.getElementById(path+"_c"+i).className="tabbed_browser_unselected";
			parent_menu = document.getElementById(path+"_c"+i).getAttribute("parent_menu")
			if (parent_menu)
				document.getElementById("tabbed_browser_submenu_"+parent_menu).className="tabbed_browser_unselected";
		}
		x.style.display="block";
		document.getElementById(path+"_c"+id).className="tabbed_browser_selected";
		parent_menu = document.getElementById(path+"_c"+id).getAttribute("parent_menu")
		if (parent_menu)
			document.getElementById("tabbed_browser_submenu_"+parent_menu).className="tabbed_browser_selected";
	} else eval(elem.getAttribute("original_action"));
};

tabbed_browser_hide = function(path,id){
	var x = document.getElementById(path+"_d"+id);
	var y = document.getElementById(path+"_c"+id);
	if(x && y) {
		x.style.display = "none";
		y.style.display = "none";
	}
};

tabbed_browser_show = function(path,id){
	var x = document.getElementById(path+"_d"+id);
	var y = document.getElementById(path+"_c"+id);
	if(x && y) {
		x.style.display = "";
		y.style.display = "";
	}
};