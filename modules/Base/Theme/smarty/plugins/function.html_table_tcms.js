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

showTip = function(tip, style, my_event) {
	var div_tip = 'div_tip_' + style;
	
	document.getElementById(div_tip).style.top = 0;
	document.getElementById(div_tip).style.left = 0;
	document.getElementById(div_tip).innerHTML = tip;
	offWidth = document.getElementById(div_tip).offsetWidth;
	offHeight = document.getElementById(div_tip).offsetHeight;

	var curPosx = ((my_event.x) ? parseInt(my_event.x) : parseInt(my_event.clientX));
	var curPosy = ((my_event.y) ? parseInt(my_event.y) : parseInt(my_event.clientY));

	if(document.body.scrollLeft + curPosx + 20 + offWidth < document.body.clientWidth - 5) {
		var pos = document.body.scrollLeft + curPosx + 20;
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

var div_tip = document.createElement('div');
div_tip.id = 'div_tip_scrolled_table';
div_tip.style.position = 'absolute';
div_tip.style.visibility = 'hidden';
document.body.appendChild(div_tip);

var span_tip_text = document.createElement('span');
span_tip_text.id = 'tooltip_text_scrolled_table';
document.body.appendChild(span_tip_text);

scrolled_table_fix_cell = function(cell, width) {
	cell.style.width = width;
	var div = document.createElement('div');
	div.innerHTML = cell.innerHTML;
	div.style.overflow = 'hidden';
	div.style.textOverflow = 'clip';
//	div.class = "scrolled_table_header";
	div.onmousemove = function(e) {
		showTip(this.innerHTML,'scrolled_table',e);
	}
	div.onmouseout = function(e) {
		hideTip('scrolled_table');
	}
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
