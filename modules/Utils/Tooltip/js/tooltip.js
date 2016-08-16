Utils_Tooltip = {
	timeout_obj: false,
	show: function(o,my_event,max_width) {	
		var div_tip = jq('#tooltip_div');
		var tooltip_text = jq('#tooltip_text');
		var tip = o.getAttribute('tip');
		if(!div_tip || !tooltip_text || !tip) return;
		
		tooltip_text.html(tip);	
		
		Utils_Tooltip.set_position(div_tip, my_event);
		
		jq('#tooltip_layer_div').css('maxWidth', max_width + 'px');
		div_tip.show();
	},
	set_position: function(div_tip, event) {
		var tip_size = {
			x: div_tip.width(), 
			y: div_tip.height()
		};
			
		var cursor_pos = {
			x: ((event.clientX) ? parseInt(event.clientX) : parseInt(event.x)),
			y: ((event.clientY) ? parseInt(event.clientY) : parseInt(event.y))
		}
			
		var scroll = {
			x: jq(window).scrollLeft(),
			y: jq(window).scrollTop()
		}
		
		var client_size = {
			x: jq(window).width(),
			y: jq(window).height()
		}
		
		var pos = {x: 0, y: 0}
			
		jq.each(pos, function(axis, val) {
			if(scroll[axis] + cursor_pos[axis] + 20 + tip_size[axis] < client_size[axis] - 10) {
				pos[axis] = scroll[axis] + cursor_pos[axis] + 20;		
			} else {
				pos[axis] = scroll[axis] + cursor_pos[axis] - tip_size[axis] - 10;
				if(pos[axis]<0) pos[axis]=0;
			}
		});		
		
		div_tip.css({
			left: pos.x + 'px',
			top: pos.y + 'px'
		});
	},
	load_ajax: function(o,my_event,max_width) {
		tooltip_id = o.getAttribute('tooltip_id');
	    Utils_Tooltip.show(o, my_event, max_width);
		if (tooltip_id!='done' && Utils_Tooltip.timeout_obj == false) {
	        Utils_Tooltip.timeout_obj = setTimeout(function () {
	            o.setAttribute('tooltip_id','done');
	            jq.ajax({
	                type: 'POST',
	                url: 'modules/Utils/Tooltip/req.php',
	                data:{
	                    tooltip_id: tooltip_id,
	                    cid: Epesi.client_id
	                },
	                success:function(t) {
	                    o.setAttribute('tip',t);
	                    if (t) {
	                        jq('#tooltip_text').html(t);
	                        if (jq('#tooltip_leightbox_mode_content').length) jq('#tooltip_leightbox_mode_content').html(t);
	                    } else Utils_Tooltip.hide();
	                }
	            });
	            Utils_Tooltip.timeout_obj = false;
	        }, 300);
		}
	},
	hide: function() {
	    if (Utils_Tooltip.timeout_obj) {
	        clearTimeout(Utils_Tooltip.timeout_obj);
	        Utils_Tooltip.timeout_obj = false;
	    }
		jq('#tooltip_div').hide();
	},
	create_block: function(template) {
		jq('<div></div>')
			.attr('id', 'tooltip_div')
			.css({
				position: 'absolute',
				display: 'none',
				zIndex: 2000,
				left: 0,
				top: 0
			})
			.mouseover(Utils_Tooltip.hide)
			.html(template)
			.appendTo(document.body);		
	},
	leightbox_mode: function(o) {
		var tip = o.getAttribute('tip');
		jq('#tooltip_leightbox_mode_content').html(tip);
	}
}

