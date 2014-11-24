Utils_Tooltip__showTip = function(o,my_event,max_width) {
	var div_tip = jq('#tooltip_div');
	var tooltip_text = jq('#tooltip_text');
	var tip = o.getAttribute('tip');
	if(!div_tip || !tooltip_text || !tip) return;
	tooltip_text.html(tip);
	var dimensions = {width: div_tip.width(), height: div_tip.height()};
	
	var curPosx = ((my_event.clientX) ? parseInt(my_event.clientX) : parseInt(my_event.x));
	var curPosy = ((my_event.clientY) ? parseInt(my_event.clientY) : parseInt(my_event.y));
	
	if(document.body.scrollLeft + curPosx + 20 + dimensions.width < document.body.clientWidth - 10) {
		var pos = document.body.scrollLeft + curPosx + 20;		
	} else {
		var pos = document.body.scrollLeft + curPosx - (dimensions.width) - 10;
		if(pos<0) pos=0;
	}
	div_tip.css('left', pos + 'px');

	var ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
	var scrollTop = (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop);
	if(navigator.appName.indexOf('Explorer') != -1 ) {
		scrollTop = document.documentElement.scrollTop;
	}
	
	if(curPosy + 20 + dimensions.height < ch - 10) {
		var pos = scrollTop + curPosy + 20;
	} else {
		var pos = scrollTop + curPosy - (dimensions.height) - 10;
		if(pos<0) pos=0;
	}
	div_tip.css('top', pos + "px");

	jq('#tooltip_layer_div').css('maxWidth', max_width + "px");
	div_tip.css('display', 'block');
}

Utils_Tooltip__load_ajax_Tip = function(o,my_event,max_width) {
	tooltip_id = o.getAttribute('tooltip_id');
	o.setAttribute('tooltip_id','done');
	if (tooltip_id!='done') {
		Utils_Tooltip__showTip(o,my_event);
		jq.ajax({
			type: 'POST',
			url: 'modules/Utils/Tooltip/req.php', 
			data:{
				tooltip_id: tooltip_id,
				cid: Epesi.client_id
			},
			success:function(t) {
				if (t) {
					o.setAttribute('tip',t);
					jq('#tooltip_text').html(t);
					if (jq("#tooltip_leightbox_mode_content")) jq("#tooltip_leightbox_mode_content").html(t);
				}
			}
		});
	} else {
		Utils_Tooltip__showTip(o,my_event,max_width);
	}
}

Utils_Tooltip__hideTip = function() {
	jq('#tooltip_div').css('display', 'none');
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
	div.onmouseover = Utils_Tooltip__hideTip;
}

Utils_Tooltip__leightbox_mode = function(o) {
	var tip = o.getAttribute('tip');
	jq("#tooltip_leightbox_mode_content").html(tip);
}

