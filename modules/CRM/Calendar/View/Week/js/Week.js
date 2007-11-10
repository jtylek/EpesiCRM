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
					mini_calendar_week_showDetails = function(id) {  
						for(i = 0; i < mini_calendar_week_visibleDetails.size(); i++) {
							document.getElementById(mini_calendar_week_visibleDetails[i]+'_f').style.visibility = 'hidden'; 
							document.getElementById(mini_calendar_week_visibleDetails[i]+'_f').style.position = 'absolute'; 
						}
						document.getElementById(id+'_f').style.zIndex = '20020'; 
						document.getElementById(id+'_f').style.visibility = 'visible'; 

						//var t = getY( document.getElementById(id) ) - 6;
						//var l = getX( document.getElementById(id) ) - 6;
						
						//document.getElementById(id+'_f').style.top = parseInt(t) + 'px'; 
						//document.getElementById(id+'_f').style.left = parseInt(l) + 'px'; 
						mini_calendar_week_visibleDetails[mini_calendar_week_visibleDetails.size()] = id;
					}; 
					
					mini_calendar_week_hideDetails = function(id) {  
						document.getElementById(id+'_f').style.visibility = 'hidden'; 
					}; 
					
					var ppp = 1;
					calendar_show_all_events = function() {
						a = 'dupa';
						ppp = 1 - ppp;
						var em = document.getElementsByTagName('div');
						//var eb = document.getElementsByName(\"events_brief\");
						a = em.length;
						if(ppp == 0) {
							document.getElementById('calendar_toggle_gouge').innerHTML = '(is on)';
							for(var i = 0; i < em.length; i++) {
								att = em[i].getAttribute('name');
								if(att == 'events_mounted') {
									em[i].style.position = 'relative';
									em[i].style.visibility = 'visible';
									em[i].style.width = (document.body.clientWidth-100) / 7;
								}
								if(att == 'events_brief') {
									em[i].style.position = 'absolute';
									em[i].style.visibility = 'hidden';
								}
								a = a + ', 1';
							}
						} else {
							document.getElementById('calendar_toggle_gouge').innerHTML = '(is off)';
							for(var i = 0; i < em.length; i++) {
								att = em[i].getAttribute('name');
								if(att == 'events_brief') {
									em[i].style.position = 'relative';
									em[i].style.visibility = 'visible';
								}
								if(att == 'events_mounted') {
									em[i].style.position = 'absolute';
									em[i].style.visibility = 'hidden';
								}
								a = a + ', 1';
							}
						}
						
					}
