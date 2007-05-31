getWidth = function(someObject){
	var w;
	if(document.defaultView &&
		document.defaultView.getComputedStyle) {
		w=document.defaultView.getComputedStyle(someObject ,'').getPropertyValue('width');
	}else if(someObject.offsetWidth){
		w=someObject.offsetWidth;
	}
	if(typeof w=="string") w=parseInt(w);
	return w;
}

scrolled_table_fix_headers = function() {
	var spans = document.getElementsByTagName('span');
	for (var i = 0; i < spans.length; i++) {
		var relAttribute = String(spans[i].getAttribute('rel'));
		if (relAttribute == 'scrolled_table') {
			var table = spans[i].getElementsByTagName('table');
			var theader = table[0].getElementsByTagName('thead')[0];
			var tbody = table[1].getElementsByTagName('tbody')[0];
			
			var headers = theader.getElementsByTagName('th');
			var trs = tbody.getElementsByTagName('tr');
			var firstcols = trs[0].getElementsByTagName('td');

			for (var j = 0; j < headers.length-1; j++)
				headers[j].style.width = getWidth(firstcols[j])+'px';
			headers[headers.length-1].style.width = 'auto';
		}
	}
}

scrolled_table_fix_cols = function() {
	var spans = document.getElementsByTagName('span');
	for (var i = 0; i < spans.length; i++) {
		var relAttribute = String(spans[i].getAttribute('rel'));
		if (relAttribute == 'scrolled_table') {
			var table = spans[i].getElementsByTagName('table');
			var theader = table[0].getElementsByTagName('thead')[0];
			var tbody = table[1].getElementsByTagName('tbody')[0];
			
			var headers = theader.getElementsByTagName('th');
			var trs = tbody.getElementsByTagName('tr');
			var header_size = new Array();
			for (var j = 0; j < headers.length; j++)
				header_size[j] = getWidth(headers[j]);

			for(var k=0; k<trs.length; k++) {
				var tds = trs[k].getElementsByTagName('td');
				for(var j=0; j<headers.length; j++) {
					tds[j].style.width = header_size[j]+'px';
				}
			}
		}
	}
	setTimeout("scrolled_table_fix_headers()",1);
}
