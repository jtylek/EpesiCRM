			showTip = function(tip, style, my_event) {
				var div_tip = 'div_tip_' + style;
				var tooltip_text = 'tooltip_text_' + style;
				//document.getElementById(div_tip).style = 'tip';
				document.getElementById(div_tip).style.top = 0;
				document.getElementById(div_tip).style.left = 0;
				document.getElementById(tooltip_text).innerHTML = tip;
				offWidth = document.getElementById(div_tip).offsetWidth;
				offHeight = document.getElementById(div_tip).offsetHeight;
				
				var curPosx = ((my_event.x) ? parseInt(my_event.x) : parseInt(my_event.clientX));
				var curPosy = ((my_event.y) ? parseInt(my_event.y) : parseInt(my_event.clientY));
				
				//document.getElementById(div_tip).style.width = offWidth;
				if(document.body.scrollLeft + curPosx + 20 + offWidth < document.body.clientWidth - 5) {
					var pos = document.body.scrollLeft + curPosx + 20;
					//alert("pos x: "+pos);
					document.getElementById(div_tip).style.left = pos + 'px';
				} else {
					var pos = document.body.scrollLeft + curPosx - (offWidth) - 10;
					document.getElementById(div_tip).style.left = pos + 'px';//document.getElementById('ev').style.width;
				}

				var ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
				var scrollTop = (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop);
				if(navigator.appName.indexOf('Explorer') != -1 ) {
					scrollTop = document.documentElement.scrollTop;
				}
				
				//document.getElementById(tooltip_text).innerHTML += ' ' + scrollTop + ' ' + ch;
				
				if(curPosy + 20 + offHeight < ch - 5) {
					var pos = scrollTop + curPosy + 20;
					document.getElementById(div_tip).style.top = pos + "px";
				} else {
					var pos = scrollTop + curPosy - (offHeight) - 10;
					document.getElementById(div_tip).style.top = pos + "px";
				}
				
				document.getElementById(div_tip).style.visibility = 'visible';
			}
			
			hideTip = function(style) {
				document.getElementById('div_tip_'+style).style.visibility = 'hidden';
			} 