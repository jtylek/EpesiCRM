	getWidth = function(someObject){
		var w = someObject.offsetWidth;
		return w;
	}
	utils_genericbrowser__fix_cell = function(cell, width) {
		var div = cell.getElementsByTagName('div');
		div = div[0];
		//alert(div.offsetWidth + ' ' +parseInt(width) + '\n' + cell.offsetWidth + ' ' +parseInt(width));
		div.style.width = parseInt(width) + 'px';
		cell.style.width = parseInt(width) + 'px';
		//alert(div.offsetWidth + ' ' +parseInt(width) + '\n' + cell.offsetWidth + ' ' +parseInt(width));
	}
	utils_genericbrowser__scrolling_table_fix_cols = function() {
		if( libs_theme__scrolling_table_fix_cols_lock == 1) {
			return setTimeout("utils_genericbrowser__scrolling_table_fix_cols()", 20);
		}
		var spans = document.getElementsByTagName('div');
		for (var i = 0; i < spans.length; i++) {
			var relAttribute = String(spans[i].getAttribute('rel'));
			if (relAttribute == 'scrolling_table') {
				var table = spans[i].getElementsByTagName('table');
				var theader = table[0].getElementsByTagName('thead')[0];
				var tbody = table[1].getElementsByTagName('tbody')[0];
				
				
				if( navigator.appName.indexOf("Opera") != -1 && table[0].offsetWidth < 0.9*document.getElementById('content').offsetWidth ) {
					var www = 0.9*document.getElementById('content').offsetWidth;
					//alert("Opera and slim: "+ table[0].offsetWidth+' < '+www);
					return;
				}
				
				var headers = theader.getElementsByTagName('th');
				var trs = tbody.getElementsByTagName('tr');
				if(typeof(trs[0])=='undefined') continue;
				var firstcols = trs[0].getElementsByTagName('td');
	
				for (var j = 0; j < headers.length; j++) {
					var w = getWidth(firstcols[j]) - 7;
					utils_genericbrowser__fix_cell(headers[j], w);
				}
				table[0].style.width = getWidth(table[1]) + 'px';
			}
		}
	}
