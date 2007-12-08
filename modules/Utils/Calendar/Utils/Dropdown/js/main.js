	var CRM_Calendar_Utils_Dropdown_visible = new Array();
	var CRM_Calendar_Utils_Dropdown_timeout = new Array();
	
	getY = function( oElement ) {
		var iReturnValue = 0;
		while( oElement != null ) {
			iReturnValue += oElement.offsetTop + 1;
			oElement = oElement.offsetParent;
		}
		return iReturnValue - 2;
	}
	
	getX = function( oElement ) {
		var iReturnValue = 0;
		while( oElement != null ) {
			iReturnValue += oElement.offsetLeft + 1;
			oElement = oElement.offsetParent;
		}
		return iReturnValue - 2;
	}
	
	CRM_Calendar_Utils_Dropdown_show = function(id) {  
		clearTimeout(CRM_Calendar_Utils_Dropdown_timeout[id]);
		id = 'CRM_Calendar_Utils_Dropdown_' + id;
		
		var t = getY( document.getElementById(id+'_b') ) - 3;
		var l = getX( document.getElementById(id+'_b') ) - 2;
		var off = 0;
		if(document.getElementById(id+'_f_top')) {
			off = document.getElementById(id+'_f_top').offsetHeight - 2;
		}
		var w = document.getElementById(id+'_b').offsetWidth;
		
		document.getElementById(id+'_f').style.top = parseInt(t) - parseInt(off) + 'px'; 
		document.getElementById(id+'_f').style.left = parseInt(l) + 'px'; 
		CRM_Calendar_Utils_Dropdown_visible[CRM_Calendar_Utils_Dropdown_visible.size()] = id;
		
		document.getElementById(id+'_f').style.visibility = 'visible'; 
		
		if(w + 4 > document.getElementById(id+'_f').offsetWidth) {
			document.getElementById(id+'_f').style.width = w + 'px';
		}
	}; 
	
	CRM_Calendar_Utils_Dropdown_hide_f = function(id) {  
		id = 'CRM_Calendar_Utils_Dropdown_' + id;
		document.getElementById(id+'_f').style.visibility = 'hidden'; 
	}; 
	
	CRM_Calendar_Utils_Dropdown_hide = function(id) {  
		CRM_Calendar_Utils_Dropdown_timeout[id] = setTimeout("CRM_Calendar_Utils_Dropdown_hide_f('"+id+"')", 40);
		//id = 'CRM_Calendar_Utils_Dropdown_' + id;
		//document.getElementById(id+'_f').style.visibility = 'hidden'; 
	}; 