Base_Help = function(){
	this.compatibility_mode = null;
	this.pointerX = 0;
	this.pointerY = 0;
	this.context;
	this.step = 0;
	this.steps;
	this.suspended = 0;
	this.current_step;
	this.target;
	this.operation;
	this.help_hooks;
	this.click_icon = $("Base_Help__click_icon");
	this.help_arrow = $("Base_Help__help_arrow");
	this.comment_frame = $("Base_Help__help_comment");
	this.screen = '';
	this.last_keypress = 0;
	this.trigger_search = false;
	this.prompt_next_step = false;

	this.init_help = function() {
		this.check_compatibility();
		Helper.timed_update();
		Event.observe(document, "mousemove", Helper.update);
	}

	this.check_compatibility = function() {
		var has_pointerevents = jQuery("#help_canvas").css('pointer-events');
		if (has_pointerevents=='auto') {
			jQuery("#help_canvas").css('pointer-events', 'none');
			this.context = $("help_canvas").getContext("2d");
			this.compatibility_mode = false;
		} else {
			jQuery("#help_canvas").remove();
			this.compatibility_mode = true;
		}
	}
	
	this.start_tutorial = function(steps) {
		this.step = 0;
		this.steps = steps.split('##');
		for (var i=0;i<this.steps.length; i++)
			this.steps[i] = this.parse_step(i);
		this.hide_menu();
	}

	this.clear_screen = function () {
		if (!this.compatibility_mode)
			this.context.clearRect(0,0,3000,3000);
	}

	this.stop_tutorial = function() {
		$('Base_Help__overlay').style.display = 'none';
		this.help_arrow.style.display = 'none';
		this.comment_frame.style.display = 'none';
		this.step = 0;
		this.steps = 0;
		Helper.clear_screen();
	}

	this.refresh_step = function() {
		this.current_step = this.steps[this.step];
		this.target = this.get_step_target(this.step);
		if (this.target) Event.observe(this.target, 'click', function(){Helper.prompt_next_step = true;});
		this.operation = this.current_step.operation;
		this.screen = jQuery('.Base_Help__screen_name').attr('value');
	}
	this.parse_step = function(step) {
		var res = new Array();
		var tmp = this.steps[step].split(':');
		res.operation = tmp[0];
		if (res.operation[res.operation.length-1] == '?') {
			res.optional = true;
			res.operation = res.operation.substr(0, res.operation.length-1);
		}
		tmp = tmp[1].split('//');
		if (tmp[1]) res.comment = tmp[1];
		tmp = tmp[0].split('->');
		if (tmp[1]) {
			res.target = tmp[1].trim();
			res.screen = tmp[0].trim();
		} else {
			res.target = tmp[0].trim();
			res.screen = '';
		}
		return res;
	}
	this.is_screen = function(step) {
		var step = this.steps[step];
		if (!step) return;
		return (!step.screen || step.screen==this.screen);
	}
	this.get_step_target = function(step) {
		var step = this.steps[step];
		return this.get_help_element(step.target);
	}
	this.timed_update = function() {
		Helper.update();
		setTimeout('Helper.timed_update();', 300);
	}
	this.operation_complete = function() {
		if (this.operation=='click') {
			return this.prompt_next_step;
		}
		if (this.operation=='prompt' || this.operation=='finish') {
			if (this.prompt_next_step) {
				this.prompt_next_step = false;
				if (this.operation=='finish') {
					Helper.stop_tutorial();
				}
				return true;
			}
			return false;
		}
		if (this.operation=='fill') {
			if (!this.target || !this.target.value) return false;
			current = new Date().getTime();
			if ((current - this.last_keypress)<800) return false;
			return true;
		}
		return true;
	}
	this.update = function(e) {
		current = new Date().getTime();
		if (this.trigger_search && (current - this.last_keypress)>800) this.search($('Base_Help__search').value);
		
		if (Helper.compatibility_mode===false)
			jQuery("#help_canvas").css('pointer-events', 'none');
		if (!Helper.steps) return;
		if (typeof(e)=='undefined' && typeof(event)!='undefined') e = event;
		if (typeof(e)!='undefined') {
			Helper.pointerX=e.clientX;
			Helper.pointerY=e.clientY;
		}
		Helper.clear_screen();
		var current = new Date().getTime();
		Helper.click_icon.src = Helper.click_icon.getAttribute('frame'+(current%1000<500?1:2));
		if (Epesi.procOn) {
			Helper.suspended = current+1000;
			Helper.click_icon.style.display = 'none';
		}
		Helper.refresh_step();
		if (!Helper.steps) return;
		while (Helper.operation_complete() && Helper.steps[Helper.step+1] && ((Helper.is_screen(Helper.step+1) && is_visible(Helper.get_step_target(Helper.step+1))) || Helper.steps[Helper.step+1].optional)) {
			Helper.prompt_next_step = false;
			Helper.step += 1;
			while (Helper.steps[Helper.step+1] && Helper.steps[Helper.step].optional)
				Helper.step += 1;
			Helper.refresh_step();
		}
		while(current>=Helper.suspended && Helper.step>0 && (!Helper.is_screen(Helper.step) || !is_visible(Helper.target))) {
			Helper.step -= 1;
			Helper.refresh_step();
		} 
		if (!Epesi.procOn && Helper.target && Helper.is_screen(Helper.step)) {
			Helper.draw_help_arrow(Helper.target);
		}
	}
	this.escape = function() {
		if ($('Base_Help__menu').style.display=="block") this.hide_menu();
		else if (Helper.steps) {
			if (this.steps[this.step].operation == 'finish') this.stop_tutorial();
			else {
				this.steps[this.step].operation = 'finish';
				this.steps[this.step].comment = this.stop_tutorial_message;
			}
		}
	}
	this.menu = function () {
		this.stop_tutorial();
		$('Base_Help__overlay').style.display="block";
		$('Base_Help__menu').style.display="block";
		$('Base_Help__search').value='';
		this.trigger_search = true;
		this.search();
		focus_by_id('Base_Help__search');
	}
	this.document_keydown = function() {
		Helper.last_keypress = new Date().getTime();
	}
	this.search_keypress = function() {
		this.last_keypress = new Date().getTime();
		this.trigger_search = true;
	}
	this.search = function(value) {
		this.trigger_search = false;
		if (!value) {
			$('Base_Help__help_suggestions').style.display='block';
			$('Base_Help__help_links').style.display='none';
			new Ajax.Request('modules/Base/Help/suggestions.php', { 
				method: 'post', 
				parameters:{
					cid: Epesi.client_id
				},
				onComplete: function(t) {
					eval(t.responseText);
				}
			});
		} else {
			$('Base_Help__help_suggestions').style.display='none';
			$('Base_Help__help_links').style.display='block';
			new Ajax.Request('modules/Base/Help/search.php', { 
				method: 'post', 
				parameters:{
					cid: Epesi.client_id,
					keywords: value
				},
				onComplete: function(t) {
					eval(t.responseText);
				}
			});
		}
	}

	this.hide_menu = function () {
		$('Base_Help__overlay').style.display="none";
		$('Base_Help__menu').style.display="none";
	}

	this.get_help_element = function (helpid) {
		if (typeof(this.hooks[helpid])!='undefined') return this.hooks[helpid];
		return jQuery(helpid)[0];
	}

	this.get_all_help_hooks = function() {
		$('Base_Help__button_next').onclick = function(){Helper.prompt_next_step = true;};
		$('Base_Help__button_finish').onclick = function(){Helper.prompt_next_step = true;};
		Helper.hooks = new Array();
		jQuery('[helpID]').each(function(){Helper.hooks[jQuery(this).attr('helpID')] = this});
		return;
	}

	this.draw_help_arrow = function (el) {
		var offset = el.getBoundingClientRect();
		var centerX = (offset.left + offset.right) / 2;
		var centerY = (offset.top + offset.bottom) / 2;
		var width = offset.right - offset.left;
		var height = offset.bottom - offset.top;
		var o_right = offset.right - width/5;
		var o_left = offset.left + width/5;
		var o_bottom = offset.bottom - height/5;
		var o_top = offset.top + height/5;
		if (centerX==0 && centerY==0) return;
		var targetX = centerX;
		var targetY = centerY;
		var sourceX = this.pointerX;
		var sourceY = this.pointerY;
		if (this.pointerX>=o_left && this.pointerX<=o_right) targetX = this.pointerX;
		else if (this.pointerX<o_left) targetX = o_left;
		if (this.pointerX>o_right) targetX = o_right;
		if (this.pointerY>=o_top && this.pointerY<=o_bottom) targetY = this.pointerY;
		else if (this.pointerY<o_top) targetY = o_top;
		if (this.pointerY>o_bottom) targetY = o_bottom;
		var show_click = false;
		if (this.operation=='finish') {
			$('Base_Help__overlay').style.display = 'block';
			$('Base_Help__button_finish').style.display = 'block';
			$('Base_Help__button_next').style.display = 'none';
			this.help_arrow.style.display = "none";
			o_right = (window.innerWidth - this.comment_frame.scrollWidth)/2 - 50;
			o_bottom = (window.innerHeight - this.comment_frame.scrollHeight)/2 - 10;
			targetX = o_right;
			targetY = o_bottom;
			sourceX = targetX+50;
			sourceY = targetY+100;
		} else if (this.operation=='prompt') {
			$('Base_Help__button_next').style.display = 'block';
			$('Base_Help__button_finish').style.display = 'none';
			targetX = o_right;
			targetY = o_bottom;
			sourceX = targetX+50;
			sourceY = targetY+100;
		} else {
			$('Base_Help__button_next').style.display = 'none';
			$('Base_Help__button_finish').style.display = 'none';
			if ((this.pointerX>=offset.left && this.pointerX<=offset.right && this.pointerY>=offset.top && this.pointerY<=offset.bottom) || this.compatibility_mode || (this.operation=='fill' && this.target==document.activeElement)) {
				targetX = o_right;
				targetY = o_bottom;
				sourceX = o_right+15;
				sourceY = o_bottom+15;
				if (this.operation=='click' || (this.operation=='fill' && this.target!=document.activeElement)) {
					this.click_icon.style.left = sourceX+'px';
					this.click_icon.style.top = sourceY+'px';
					show_click = true;
				}
			}
		}
		if (show_click) this.click_icon.style.display="block";
		else this.click_icon.style.display="none";
		if (this.operation!='finish') {
			if (!this.compatibility_mode)
				this.fancy_arrow(this.context, sourceX, sourceY, targetX, targetY);
			else {
				this.help_arrow.style.display = "block";
				this.help_arrow.style.left = targetX+'px';
				this.help_arrow.style.top = targetY+'px';
			}
		}
		if (this.steps[this.step].comment && (this.operation!='fill' || this.target==document.activeElement)) {
			$('Base_Help__help_comment_contents').innerHTML = this.steps[this.step].comment;
			this.comment_frame.style.display = 'block';
			this.comment_frame.style.left = (o_right+50)+'px';
			this.comment_frame.style.top = (o_bottom+10)+'px';
		} else {
			this.comment_frame.style.display = 'none';
		}
	}
	this.fancy_arrow = function(ctx,x1,y1,x2,y2) {
		'use strict';
		ctx.fillStyle = 'rgba(240, 90, 0, 0.8)';

		var angle = 0.45;
		var d    = 40;
		var dist=Math.sqrt((x2-x1)*(x2-x1)+(y2-y1)*(y2-y1));
		var ratio=(dist-d/3)/dist;
		var tox, toy,fromx,fromy;
		tox=x1+(x2-x1)*ratio;
		toy=y1+(y2-y1)*ratio;

		var lineangle=Math.atan2(y2-y1,x2-x1);
		var h=Math.abs(d/Math.cos(angle));

		// Arrow shaft
		if (dist>d) {
			var angle = 20/dist;
			var d    = dist;
			var lineangle=Math.atan2(y2-y1,x2-x1);
			var h=Math.abs(d/Math.cos(angle));
			var angle1=lineangle+Math.PI+angle;
			var topx=x2+Math.cos(angle1)*h;
			var topy=y2+Math.sin(angle1)*h;
			var angle2=lineangle+Math.PI-angle;
			var botx=x2+Math.cos(angle2)*h;
			var boty=y2+Math.sin(angle2)*h;
			var curx=x2-Math.cos(lineangle)*(h-30);
			var cury=y2-Math.sin(lineangle)*(h-30);

			var angle = 0.20;
			var d    = 30;
			var h=Math.abs(d/Math.cos(angle));
			var angle1=lineangle+Math.PI+angle;
			var topx2=x2+Math.cos(angle1)*h;
			var topy2=y2+Math.sin(angle1)*h;
			var angle2=lineangle+Math.PI-angle;
			var botx2=x2+Math.cos(angle2)*h;
			var boty2=y2+Math.sin(angle2)*h;
			
			ctx.save();
			ctx.beginPath();
			ctx.moveTo(topx,topy);
			ctx.lineTo(topx2,topy2);
			ctx.lineTo(botx2,boty2);
			ctx.lineTo(botx,boty);
			ctx.quadraticCurveTo(curx,cury,topx,topy);
			ctx.arc(curx, cury, 6, 0 , 2 * Math.PI, false);
			ctx.fill();
		}

		// Arrow ending
		var angle = 0.45;
		var d    = 40;
		var dist=Math.sqrt((x2-x1)*(x2-x1)+(y2-y1)*(y2-y1));

		var lineangle=Math.atan2(y2-y1,x2-x1);
		var h=Math.abs(d/Math.cos(angle));

		var angle1=lineangle+Math.PI+angle;
		var topx=x2+Math.cos(angle1)*h;
		var topy=y2+Math.sin(angle1)*h;
		var angle2=lineangle+Math.PI-angle;
		var botx=x2+Math.cos(angle2)*h;
		var boty=y2+Math.sin(angle2)*h;
		drawHead(ctx,topx,topy,x2,y2,botx,boty,3);

//		ctx.shadowColor = '#777';
//		ctx.shadowBlur = 12;
//		ctx.shadowOffsetX = 7;
//		ctx.shadowOffsetY = 7;
	}
}

var Helper = new Base_Help();
Helper.init_help();
document.onkeydown = Helper.document_keydown;

