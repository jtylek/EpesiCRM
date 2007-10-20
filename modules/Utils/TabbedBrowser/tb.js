tabbed_browser_switch = function(id,max,elem,path){
	var x = $(path+"_d"+id);
	if(x) {
		for(var i=0; i<max; i++){
			var y = $(path+"_d"+i);
			if(y) y.style.display="none";
			$(path+"_c"+i).className="tabbed_browser_unselected";
		}
		x.style.display="block";
		$(path+"_c"+id).className="tabbed_browser_selected";
	} else eval(elem.getAttribute("original_action"));
};