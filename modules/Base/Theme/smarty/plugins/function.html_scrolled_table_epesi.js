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

libs_theme__scrolled_table_fix_headers = function() {
	var divs = document.getElementsByTagName('div');
	for (var i = 0; i < divs.length; i++) {
		var relAttribute = String(divs[i].getAttribute('rel'));
		if (relAttribute != 'scrolled_table') continue;
		var table = divs[i].getElementsByTagName('table');
		table[0].style.width = getWidth(table[1])+"px";
	}
}

libs_theme__scrolled_table_fix_cols = function() {
	var tables = document.getElementsByTagName('table');
	for (var i = 0; i < tables.length; i++) {
		var relAttribute = String(tables[i].getAttribute('rel'));
		if (relAttribute != 'scrolled_table') continue;
		var table = tables[i];
		var theader = table.getElementsByTagName('thead')[0];
		var tbody = table.getElementsByTagName('tbody')[0];
		var ths = theader.getElementsByTagName('th');
		var widths = Array();
		var div_width=getWidth(table);
		for(var k=0; k<ths.length; k++)
			widths[k] = getWidth(ths[k]);
		var style=table.getAttribute('style');
		var id=table.getAttribute('id');
		var cl=table.getAttribute('class');		

		var main_div = document.createElement('div');
		main_div.style.textAlign="left";
		main_div.style.width=(div_width+30)+"px";
		main_div.setAttribute("rel","scrolled_table");
//		main_div.style.border="1px solid red";
		table.parentNode.insertBefore(main_div,table);

		var table_header = document.createElement('table');
		table_header.appendChild(theader);
		main_div.appendChild(table_header);
		table_header.setAttribute('style',style);
		table_header.setAttribute('class',cl);
		table_header.setAttribute('id',id);
		var ths = theader.getElementsByTagName('th');
		for(var k=0; k<ths.length; k++)
			ths[k].style.width = widths[k]+"px";

		var div = document.createElement('div');
		div.style.overflow='auto';
		div.style.overflowX='hidden';
		var height = table.getAttribute('body_height');
		if(typeof(height)=='undefined') height='300px';
		div.style.height=height;
		div.style.width=(div_width+30)+'px';
		main_div.appendChild(div);

		var table_body = document.createElement('table');
		table_body.appendChild(tbody);
		div.appendChild(table_body);
		var trs = tbody.getElementsByTagName('tr');
		table_body.setAttribute('style',style);
		table_body.setAttribute('class',cl);
		table_body.setAttribute('id',id);
		if(trs[0]) {
			var tds = trs[0].getElementsByTagName('td');
			for(var k=0; k<tds.length; k++)
				tds[k].style.width = widths[k]+"px";
		}
		table.parentNode.removeChild(table);
	}
	setTimeout("libs_theme__scrolled_table_fix_headers()",10);
}
