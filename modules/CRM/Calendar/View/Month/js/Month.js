	var mini_calendar_week_visibleDetails = new Array();
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
	
	//TODO: New layer shoud cover whole of old layer.
	mini_calendar_month_showDetails = function(id) {  
		for(i = 0; i < mini_calendar_week_visibleDetails.size(); i++) {
			document.getElementById(mini_calendar_week_visibleDetails[i]+'_f').style.display = 'none'; 
		}
		document.getElementById(id+'_f').style.display = 'block'; 

		//var t = getY( document.getElementById(id+'_b') ) - 6;
		//var l = getX( document.getElementById(id+'_b') ) - 6;
		
		//document.getElementById(id+'_f').style.top = parseInt(t) + 'px'; 
		//document.getElementById(id+'_f').style.left = parseInt(l) + 'px'; 
		mini_calendar_week_visibleDetails[mini_calendar_week_visibleDetails.size()] = id;
	}; 
	
	mini_calendar_month_hideDetails = function(id) {  
		document.getElementById(id+'_f').style.display = 'none'; 
	}; 
