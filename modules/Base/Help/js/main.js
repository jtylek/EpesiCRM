Base_Help = function(){
	var pointerX = 0;
	var pointerY = 0;
	var context;
	var step = 0;
	var steps;
	var suspended = 0;
	var current_step;
	var target;
	var operation;
	var help_hooks;
	this.context = $("help_canvas").getContext("2d");
	this.click_icon = $("Base_Help__click_icon");
	
	this.start_tutorial = function(steps) {
		this.step = 0;
		this.steps = steps.split('##');
		this.hide_menu();
	}

	this.stop_tutorial = function() {
		this.step = 0;
		this.steps = 0;
		Helper.context.clearRect(0,0,3000,3000);
	}

	this.refresh_step = function() {
		this.current_step = this.steps[this.step].split(':');
		this.target = this.get_help_element(this.current_step[1]);
		this.operation = this.current_step[0];
	}
	this.timed_update = function() {
		Helper.update();
		setTimeout('Helper.timed_update();', 200);
	}
	this.update = function(e) {
		if (!Helper.steps) return;
		if (e||event) {
			Helper.pointerX=(e||event).clientX;
			Helper.pointerY=(e||event).clientY;
		}
		Helper.context.clearRect(0,0,3000,3000);
		var current = new Date().getTime();
		Helper.click_icon.src = Helper.click_icon.getAttribute('frame'+(current%1000<500?1:2));
		if (Epesi.procOn) {
			Helper.suspended = current+1000;
			Helper.click_icon.style.display = 'none';
		}
		Helper.refresh_step();
		if (Helper.operation!='prompt' && Helper.steps[Helper.step+1]) {
			if (is_visible(Helper.get_help_element(Helper.steps[Helper.step+1].split(':')[1])))
				Helper.step += 1;
			Helper.refresh_step();
		}
		if (Helper.operation=='finish') {
			Helper.stop_tutorial();
			return;
		}
		while(current>=Helper.suspended && Helper.step>0 && !is_visible(Helper.target)) {
			Helper.step -= 1;
			Helper.refresh_step();
		} 
		if (!Epesi.procOn && Helper.target) {
			Helper.help_arrow(Helper.target);
		}
	}
	
	this.menu = function () {
		$('Base_Help__overlay').style.display="block";
		$('Base_Help__menu').style.display="block";
		this.stop_tutorial();
		$('Base_Help__search').value='';
		this.search();
		focus_by_id('Base_Help__search');
	}
	this.search = function(value) {
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
		return this.hooks[helpid];
	}

	this.get_all_help_hooks = function() {
		this.hooks = new Array();
		var allElements = document.getElementsByTagName('*');
		for (var i = 0; i < allElements.length; i++) {
			attr = allElements[i].getAttribute('helpID');
			if (attr) this.hooks[attr] = allElements[i];
		}
	}

	this.help_arrow = function (el) {
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
		if (this.pointerX>=offset.left && this.pointerX<=offset.right && this.pointerY>=offset.top && this.pointerY<=offset.bottom) {
			targetX = o_right;
			targetY = o_bottom;
			sourceX = o_right+15;
			sourceY = o_bottom+15;
			if (this.operation=='click') {
				this.click_icon.style.left = sourceX+'px';
				this.click_icon.style.top = sourceY+'px';
				show_click = true;
			}
		}
		if (show_click) this.click_icon.style.display="block";
		else this.click_icon.style.display="none";
		this.fancy_arrow(this.context, sourceX, sourceY, targetX, targetY);
	}
	this.fancy_arrow = function(ctx,x1,y1,x2,y2) {
		'use strict';
		ctx.fillStyle = '#A00';

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
Helper.timed_update();
Event.observe(document, "mousemove", Helper.update);
