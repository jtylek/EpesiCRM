Utils_Toltip__showTip = function(tip, my_event) {
	var div_tip = $('tooltip_div');
	var tooltip_text = $('tooltip_text');
	if(!div_tip || !tooltip_text) return;
	tooltip_text.innerHTML = tip;
	offWidth = div_tip.getWidth();
	offHeight = div_tip.getHeight();
	
	var curPosx = ((my_event.clientX) ? parseInt(my_event.clientX) : parseInt(my_event.x));
	var curPosy = ((my_event.clientY) ? parseInt(my_event.clientY) : parseInt(my_event.y));
	
	if(document.body.scrollLeft + curPosx + 20 + offWidth < document.body.clientWidth - 10) {
		var pos = document.body.scrollLeft + curPosx + 20;
		div_tip.style.left = pos + 'px';
	} else {
		var pos = document.body.scrollLeft + curPosx - (offWidth) - 10;
		div_tip.style.left = pos + 'px';//$('ev').style.width;
	}

	var ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
	var scrollTop = (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop);
	if(navigator.appName.indexOf('Explorer') != -1 ) {
		scrollTop = document.documentElement.scrollTop;
	}
	
	//tooltip_text.innerHTML += ' ' + scrollTop + ' ' + ch;
	
	if(curPosy + 20 + offHeight < ch - 10) {
		var pos = scrollTop + curPosy + 20;
		div_tip.style.top = pos + "px";
	} else {
		var pos = scrollTop + curPosy - (offHeight) - 10;
		div_tip.style.top = pos + "px";
	}
	
	div_tip.style.display = 'block';
}

Utils_Toltip__hideTip = function() {
	$('tooltip_div').style.display = 'none';
} 