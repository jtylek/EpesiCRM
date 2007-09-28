Utils_Toltip__showTip = function(tip, my_event) {
	var div_tip = $('tooltip_div');
	var tooltip_text = $('tooltip_text');
	if(!div_tip || !tooltip_text) return;
	div_tip.style.top = 0;
	div_tip.style.left = 0;
	tooltip_text.innerHTML = tip;
	offWidth = div_tip.offsetWidth;
	offHeight = div_tip.offsetHeight;
	
	var curPosx = ((my_event.x) ? parseInt(my_event.x) : parseInt(my_event.clientX));
	var curPosy = ((my_event.y) ? parseInt(my_event.y) : parseInt(my_event.clientY));
	
	if(document.body.scrollLeft + curPosx + 20 + offWidth < document.body.clientWidth - 5) {
		var pos = document.body.scrollLeft + curPosx + 20;
		//alert("pos x: "+pos);
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
	
	if(curPosy + 20 + offHeight < ch - 5) {
		var pos = scrollTop + curPosy + 20;
		div_tip.style.top = pos + "px";
	} else {
		var pos = scrollTop + curPosy - (offHeight) - 10;
		div_tip.style.top = pos + "px";
	}
	
	div_tip.style.visibility = 'visible';
}

Utils_Toltip__hideTip = function() {
	$('tooltip_div').style.visibility = 'hidden';
} 