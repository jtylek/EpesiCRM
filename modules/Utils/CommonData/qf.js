var Utils_CommonData = Class.create();
Utils_CommonData.prototype = {
	obj:null,
	path:null,
	add_empty:null,
	def_val:'',
	initialize: function(id,val,cd,ae) {
		this.obj = $(id);
		this.path = cd.evalJSON();
		this.add_empty = ae;
		this.def_val = val;
		var obj = this.obj;
//		for(var i=1,max=this.path.length-1; i<max; i++)
//			Event.observe(eval('obj.form.'+this.path[i]),'e_u_cd:clear',function(){obj.options.length=0;obj.disabled=true;alert('clear '+obj.name);});

//		alert('observe '+obj.name);
		var prev_obj = eval('obj.form.'+this.path[this.path.length-1]);
		Event.observe(prev_obj,'change',this.request.bindAsEventListener(this));
		Event.observe(prev_obj,'e_u_cd:load',this.request.bindAsEventListener(this));
		Event.observe(prev_obj,'e_u_cd:clear',function(){obj.options.length=0;obj.disabled=true;});
		
		if(this.path.length==2)
			this.request(null);
	},
	request: function(e) {
		var obj = this.obj;
//		alert('request '+obj.name);
		obj.options.length=0;
		var curr_root = this.path[0];
		for(var i=1; i<this.path.length; i++) {
			var val = eval('obj.form.'+this.path[i]).value;
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
			this.obj.disabled=true;
		} else {
			this.obj.disabled=false;
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
