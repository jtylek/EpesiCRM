var Utils_CommonData = Class.create();
Utils_CommonData.prototype = {
	obj:null,
	path:null,
	add_empty:null,
	def_val:null,
	initialize: function(id,val,cd,ae) {
		this.obj = $(id);
		this.path = cd.evalJSON();
		this.add_empty = ae;
		this.def_val = val;
		var obj = this.obj;
		for(var i=1,max=this.path.length-1; i<max; i++)
			Event.observe(eval('obj.form.'+this.path[i]),'change',function(){obj.options.length=0;obj.disabled=true;});

//		alert('observe '+obj.name);
		Event.observe(eval('obj.form.'+this.path[this.path.length-1]),'change',this.request.bindAsEventListener(this));
	
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
			if(val=='') {
				obj.disabled=true;
				obj.options.length=0;
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
		if(new_opts.length==0) this.obj.disabled=true;
		else {
			if(this.add_empty==1) opts[0] = new Option('---','');
			$H(new_opts).each(function(x) {opts[opts.length] = new Option(x[1],x[0]);});
			this.obj.disabled=false;
			if(this.def_val!=null) {
				this.obj.value = this.def_val;
				this.def_val=null;
	//			alert('def is '+this.def_val);
			}
			this.obj.fire('change'); //doesn't work now, but with new prototype it will work!:P
		}
	}
};
