var Utils_CommonData = Class.create();
Utils_CommonData.prototype = {
	obj:null,
	path:null,
	add_empty:null,
	def_val:'',
	initialize: function(id,val,cd,ae) {
		this.obj = $(id);
		if(this.obj==null) return;
		this.path = cd.evalJSON();
		this.add_empty = ae;
		this.def_val = val;
		var obj = this.obj;
		var prev_obj = eval('obj.form.'+this.path[this.path.length-1]);
		Event.observe(prev_obj,'change',this.request.bindAsEventListener(this));
		Event.observe(prev_obj,'e_u_cd:load',this.request.bindAsEventListener(this));
		Event.observe(prev_obj,'e_u_cd:clear',function(){obj.options.length=0;obj.fire('e_u_cd:clear');});

		this.first_request_bind = this.first_request.bindAsEventListener(this);
		if(this.path.length==2)
			Event.observe(document,'e:load',this.first_request_bind);
	},

	first_request: function(e) {
		Event.stopObserving(document,'e:load',this.first_request_bind);
//		alert('first');
		this.request(null);
	},
	first_request_bind:null,

	request: function(e) {
		var obj = this.obj;
//		alert('request '+obj.name);
		obj.options.length=0;
		var curr_root = this.path[0];
		for(var i=1; i<this.path.length; i++) {
			var val = eval('obj.form.'+this.path[i]).value;
			if(val=='') {
				this.obj.options.length=0;
				this.obj.fire('e_u_cd:clear');
//				setTimeout(this.obj.fire.bind(this.obj,'e_u_cd:clear'),1);
				return;
			}
			curr_root += '/' + val;
		}
		new Ajax.Request('modules/Utils/CommonData/update.php',{
				method:'post',
				parameters:{
					value: curr_root
				},
				onSuccess: this.on_request.bind(this)
			});
	},
	on_request: function(t) {
		var new_opts = t.responseText.evalJSON();
		var opts = this.obj.options;
		opts.length=0;
		if(new_opts.length==0) {
			this.obj.fire('e_u_cd:clear');
		} else {
			if(this.add_empty==1) opts[0] = new Option('---','');
			$H(new_opts).each(function(x) {opts[opts.length] = new Option(x[1],x[0]);});
			if(this.def_val!='') {
				this.obj.value = this.def_val;
				this.def_val='';
			} else
				this.obj.value = opts[0].value;
//			alert('fire='+this.obj.name+' valyx='+opts[0].value);
//			this.obj.fire('e_u_cd:load');
			setTimeout(this.obj.fire.bind(this.obj,'e_u_cd:load'),1);
		}
	}
};

var Utils_CommonData_freeze = Class.create();
Utils_CommonData_freeze.prototype = {
	obj:null,
	path:null,
	id:null,
	initialize: function(id,cd) {
		this.id = id;
		this.obj = $(id);
		if(this.obj==null) return;
		this.path = cd.evalJSON();
		var obj = this.obj;
		var prev_obj = eval('obj.form.'+this.path[this.path.length-1]);
		if(this.path.length>2)
			Event.observe(prev_obj,'e_u_cd:load',this.request.bindAsEventListener(this));

		this.first_request_bind = this.first_request.bindAsEventListener(this);
		if(this.path.length==2)
			Event.observe(document,'e:load',this.first_request_bind);
	},

	first_request: function(e) {
		Event.stopObserving(document,'e:load',this.first_request_bind);
		//alert('first');
		this.request(null);
	},
	first_request_bind:null,

	request: function(e) {
		var obj = this.obj;
		var curr_root = this.path[0];
		for(var i=1; i<this.path.length; i++) {
			var val = eval('obj.form.'+this.path[i]).value;
			if(val=='') {
				$(this.id+'_label').innerHTML = '---';
				setTimeout(this.obj.fire.bind(this.obj,'e_u_cd:load'),1);
				return;
			}
			curr_root += '/' + val;
		}
		if(this.obj.value=='') {
			$(this.id+'_label').innerHTML = '---';
			setTimeout(this.obj.fire.bind(this.obj,'e_u_cd:load'),1);
			return;
		}
		curr_root += '/' + this.obj.value;
//		alert('request '+obj.name+'; root '+curr_root);
		new Ajax.Request('modules/Utils/CommonData/update_freeze.php',{
				method:'post',
				parameters:{
					value: curr_root
				},
				onSuccess: this.on_request.bind(this)
			});
	},
	on_request: function(t) {
		if (!t.responseText) return;
		var val = t.responseText.evalJSON();
		$(this.id+'_label').innerHTML = val;
		setTimeout(this.obj.fire.bind(this.obj,'e_u_cd:load'),1);
	}
};
