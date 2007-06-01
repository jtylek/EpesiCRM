getWidth = function(someObject){
	var w;
	if(document.defaultView &&
		document.defaultView.getComputedStyle) {
		w=document.defaultView.getComputedStyle(someObject ,'').getPropertyValue('width');
	}else if(someObject.offsetWidth){
		w=someObject.offsetWidth-2;
	}
	if(typeof w=="string") w=parseInt(w);
	return w;
}

scrolled_table_fix_cell = function(cell, width) {
	cell.style.width = width;
	var div = document.createElement('div');
	div.innerHTML = cell.innerHTML;
	div.style.overflow = 'hidden';
	div.style.textOverflow = 'clip';
	div.style.width = width;
	cell.innerHTML = '';
	cell.appendChild(div);
}

scrolled_table_fix_scrollbar = function() {
	var spans = document.getElementsByTagName('span');
	for (var i = 0; i < spans.length; i++) {
		var relAttribute = String(spans[i].getAttribute('rel'));
		if (relAttribute == 'scrolled_table') {
			var table = spans[i].getElementsByTagName('table');
			var tbody = table[1].getElementsByTagName('tbody')[0];
			
			var diff_width = getWidth(table[0])-getWidth(table[1]);

			if(diff_width>0) {
				var trs = tbody.getElementsByTagName('tr');
				var firstcols = trs[0].getElementsByTagName('td');
				firstcols[firstcols.length-1].style.width = (parseInt(firstcols[firstcols.length-1].style.width)-diff_width+2)+'px';
			}
		}
	}
}

scrolled_table_fix_headers = function() {
	var spans = document.getElementsByTagName('span');
	for (var i = 0; i < spans.length; i++) {
		var relAttribute = String(spans[i].getAttribute('rel'));
		if (relAttribute == 'scrolled_table') {
			var table = spans[i].getElementsByTagName('table');
			var theader = table[0].getElementsByTagName('thead')[0];
			var tbody = table[1].getElementsByTagName('tbody')[0];
			
			table[0].style.width = getWidth(table[1]);
			
			var headers = theader.getElementsByTagName('th');
			var trs = tbody.getElementsByTagName('tr');
			var firstcols = trs[0].getElementsByTagName('td');

			for (var j = 0; j < headers.length; j++) {
				var w=getWidth(firstcols[j]);
				scrolled_table_fix_cell(headers[j],w+'px');
			}
		}
	}
	setTimeout("scrolled_table_fix_scrollbar()",10);
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
			var firstcols = trs[0].getElementsByTagName('td');

			var header_size = new Array();
			for (var j = 0; j < headers.length; j++)
				header_size[j] = getWidth(headers[j]);

			for(var j=0; j<headers.length; j++)
				firstcols[j].style.width = header_size[j]+'px';
		}
	}
	setTimeout("scrolled_table_fix_headers()",10);
}
