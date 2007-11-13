var CRM_Clendar_Utils_Sidetip = {
	sidetips: new Array(),
	opened: new Array(),
	reset: function() {
		for(i = 0; i < CRM_Clendar_Utils_Sidetip.sidetips.length; i++) {
			var id = CRM_Clendar_Utils_Sidetip.sidetips[i].activator;
			if(document.getElementById(id+'_f')) {
				//alert('deleting');
				document.body.removeChild(document.getElementById(id+'_f'));
			}
		}
		CRM_Clendar_Utils_Sidetip.sidetips = new Array();
	},
	
	getY: function( oElement ) {
		var iReturnValue = 0;
		while( oElement != null ) {
			iReturnValue += oElement.offsetTop + 1;
			oElement = oElement.offsetParent;
		}
		return iReturnValue - 2;
	},	
	getX: function( oElement ) {
		var iReturnValue = 0;
		while( oElement != null ) {
			iReturnValue += oElement.offsetLeft + 1;
			oElement = oElement.offsetParent;
		}
		return iReturnValue - 2;
	},
	
	action_off: function(tip) {
		if($(tip))
			$(tip).style.visibility = 'hidden';
	},
	
	create: function(field_activator, field_anchor, field_text, field_style) {
		CRM_Clendar_Utils_Sidetip.sidetips.push({activator: field_activator, anchor: field_anchor, text: field_text, style: field_style});
	},
	
	sidetip: function(activator, anchor, text, style) {
		this.activator = activator;
		this.anchor = anchor;
		this.text = text;
		this.style = style;
		
		this.tip = document.createElement('span');
		this.tip.id = this.activator+'_f';
		this.tip.innerHTML = this.text;
		this.tip.style.zIndex = 100000;
		this.tip.className = 'crm_calendar_view_week__eventsfull';
		
		document.body.appendChild(this.tip);
		this.action_on = function(event) {
			//if($(this.id)) {
				for(i = 0; i < CRM_Clendar_Utils_Sidetip.opened.length; i++) {
					CRM_Clendar_Utils_Sidetip.action_off(CRM_Clendar_Utils_Sidetip.opened[i]);
				}
				CRM_Clendar_Utils_Sidetip.opened.push(this.tip.id);
				if(this.to)
					clearTimeout(this.to);
				x = CRM_Clendar_Utils_Sidetip.getX($(this.anchor));
				y = CRM_Clendar_Utils_Sidetip.getY($(this.anchor));
	
				this.tip.style.position = 'absolute';
				this.tip.style.visibility = 'visible';
				if(this.style == 'horizontal') {
					if(x + $(this.anchor).offsetWidth + 200 < document.body.clientWidth - 10) {
						this.tip.style.left = eval(x+$(this.anchor).offsetWidth)+'px';
					} else {
						this.tip.style.left = eval(x-$(this.tip.id).offsetWidth)+'px';
					}
					this.tip.style.top = y+'px';
				} else if(this.style == 'vertical') {
					this.tip.style.left = x+'px';
					this.tip.style.top = eval(y+$(this.anchor).offsetHeight)+'px';
					//this.tip.style.top = y+'px';
					this.tip.style.width = $(this.anchor).offsetWidth+'px';
				} else {
					this.tip.style.left = eval(30+x)+'px';
					this.tip.style.top = eval(y+$(this.anchor).offsetHeight)+'px';
					//this.tip.style.top = y+'px';
					this.tip.style.width = eval($(this.anchor).offsetWidth - 30)+'px';
				}
			//}
		};
		this.action_off = function(event) {
			this.to = setTimeout('CRM_Clendar_Utils_Sidetip.action_off("'+this.tip.id+'")', 200);
		};
		this.actionOn   = this.action_on.bindAsEventListener(this);
		Event.observe(this.activator, 'mouseover', this.actionOn);
		this.actionOff   = this.action_off.bindAsEventListener(this);
		Event.observe(this.activator, 'mouseout', this.actionOff);

		Event.observe(this.tip.id, 'mouseover', this.actionOn);
		Event.observe(this.tip.id, 'mouseout', this.actionOff);
	},
	
	create_all: function() {
		for(i = 0; i < CRM_Clendar_Utils_Sidetip.sidetips.length; i++) {
			new CRM_Clendar_Utils_Sidetip.sidetip(
				CRM_Clendar_Utils_Sidetip.sidetips[i]['activator'], 
				CRM_Clendar_Utils_Sidetip.sidetips[i]['anchor'], 
				CRM_Clendar_Utils_Sidetip.sidetips[i]['text'],
				CRM_Clendar_Utils_Sidetip.sidetips[i]['style']
			);
		}
	}	
};
