Utils_Tooltip__showTip = function(o,my_event,max_width) {
	var div_tip = $('tooltip_div');
	var tooltip_text = $('tooltip_text');
	var tip = o.getAttribute('tip');
	if(!div_tip || !tooltip_text || !tip) return;
	tooltip_text.innerHTML = tip;
	var dimensions = div_tip.getDimensions();
	
	var curPosx = ((my_event.clientX) ? parseInt(my_event.clientX) : parseInt(my_event.x));
	var curPosy = ((my_event.clientY) ? parseInt(my_event.clientY) : parseInt(my_event.y));
	
	if(document.body.scrollLeft + curPosx + 20 + dimensions.width < document.body.clientWidth - 10) {
		var pos = document.body.scrollLeft + curPosx + 20;
		div_tip.style.left = pos + 'px';
	} else {
		var pos = document.body.scrollLeft + curPosx - (dimensions.width) - 10;
		if(pos<0) pos=0;
		div_tip.style.left = pos + 'px';//$('ev').style.width;
	}

	var ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
	var scrollTop = (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop);
	if(navigator.appName.indexOf('Explorer') != -1 ) {
		scrollTop = document.documentElement.scrollTop;
	}
	
	//tooltip_text.innerHTML += ' ' + scrollTop + ' ' + ch;
	
	if(curPosy + 20 + dimensions.height < ch - 10) {
		var pos = scrollTop + curPosy + 20;
		div_tip.style.top = pos + "px";
	} else {
		var pos = scrollTop + curPosy - (dimensions.height) - 10;
		if(pos<0) pos=0;
		div_tip.style.top = pos + "px";
	}

	$('tooltip_layer_div').style.maxWidth = max_width + "px";
	div_tip.style.display = 'block';
}

Utils_Tooltip__load_ajax_Tip = function(o,my_event,max_width) {
	tooltip_id = o.getAttribute('tooltip_id');
	o.setAttribute('tooltip_id','done');
	if (tooltip_id!='done') {
		Utils_Tooltip__showTip(o,my_event);
		new Ajax.Request('modules/Utils/Tooltip/req.php', {
			method: 'post',
			parameters:{
				tooltip_id: tooltip_id,
				cid: Epesi.client_id
			},
			onSuccess:function(t) {
				o.setAttribute('tip',t.responseText);
				$('tooltip_text').innerHTML = t.responseText;
			}
		});
	} else {
		Utils_Tooltip__showTip(o,my_event,max_width);
	}
}

Utils_Tooltip__hideTip = function() {
	$('tooltip_div').style.display = 'none';
} 

Utils_Tooltip__create_block = function(template) {
	div = document.createElement('div');
	div.id = 'tooltip_div';
	div.style.position = 'absolute';
	div.style.display = 'none';
	div.style.zIndex = 2000;
	div.style.left = 0;
	div.style.top = 0;
	div.onmouseover = "Utils_Tooltip__hideTip()";
	div.innerHTML = template;
	body = document.getElementsByTagName('body');
	body = body[0];
	document.body.appendChild(div);
}