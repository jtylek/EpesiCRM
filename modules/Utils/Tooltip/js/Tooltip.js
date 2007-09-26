Utils_Toltip__showTip = function(tip, my_event) {
	var div_tip = 'tooltip_div';
	var tooltip_text = 'tooltip_text';
	document.getElementById(div_tip).style.top = 0;
	document.getElementById(div_tip).style.left = 0;
	document.getElementById(tooltip_text).innerHTML = tip;
	offWidth = document.getElementById(div_tip).offsetWidth;
	offHeight = document.getElementById(div_tip).offsetHeight;
	
	var curPosx = ((my_event.x) ? parseInt(my_event.x) : parseInt(my_event.clientX));
	var curPosy = ((my_event.y) ? parseInt(my_event.y) : parseInt(my_event.clientY));
	
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

Utils_Toltip__hideTip = function() {
	document.getElementById('tooltip_div').style.visibility = 'hidden';
} 