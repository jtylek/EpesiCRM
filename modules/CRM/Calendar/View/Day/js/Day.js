					var mini_calendar_day_visibleDetails = new Array();
					
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
					mini_calendar_day_showDetails = function(id) {  
						for(i = 0; i < mini_calendar_day_visibleDetails.size(); i++) {
							document.getElementById(mini_calendar_day_visibleDetails[i]+'_f').style.display = 'none'; 
						}
						document.getElementById(id+'_f').style.display = 'block'; 

						mini_calendar_day_visibleDetails[mini_calendar_day_visibleDetails.size()] = id;
					}; 
					
					mini_calendar_day_hideDetails = function(id) {  
						document.getElementById(id+'_f').style.display = 'none'; 
					}; 
					
				
