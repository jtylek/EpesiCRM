function Utils_CommonData(id,val,cd,ae,order) {
    this.initialize(id,val,cd,ae,order);
}
Utils_CommonData.prototype = {
	obj:null,
	path:null,
	add_empty:null,
	order:'',
	def_val:'',
	initialize: function(id,val,cd,ae,order) {
		var _this = this;
		this.obj = jq('#'+id);
		if(!this.obj.length) return;
		this.obj = this.obj.get(0);
		this.path = jq.parseJSON(cd);
		this.add_empty = ae;
		this.def_val = val;
		this.order = order;
		var obj = this.obj;
		var prev_obj = eval('obj.form.'+this.path[this.path.length-1]);
		jq(prev_obj).on('change',function(e){_this.request.call(_this,e)}).on('e_u_cd:load',function(e){_this.request.call(_this,e)}).on('e_u_cd:clear',function(){obj.options.length=0;jq(obj).trigger('e_u_cd:clear');});

		if(this.path.length==2)
			jq(document).one('e:load',function(e){_this.request.call(_this,null)});
	},

	request: function(e) {
		var obj = this.obj;
		obj.options.length=0;
		var curr_root = this.path[0];
		for(var i=1; i<this.path.length; i++) {
			var val = eval('obj.form.'+this.path[i]).value;
			if(val=='') {
				this.obj.options.length=0;
				jq(this.obj).trigger('e_u_cd:clear');
//				setTimeout(function(){jq(this.obj).trigger('e_u_cd:clear')},1);
				return;
			}
			curr_root += '/' + val;
		}
		var _this = this;
		jq.ajax('modules/Utils/CommonData/update.php',{
				method:'post',
				data:{
					value: curr_root,
					order: this.order
				},
				success: function(a,b,c){_this.on_request.call(_this,a,b,c)}
			});
	},
	on_request: function(t) {
		if (!t) return;
		var new_opts = jq.parseJSON(t);
		var opts = this.obj.options;
		opts.length=0;
		if(new_opts.length==0) {
			jq(this.obj).trigger('e_u_cd:clear');
		} else {
			if(this.add_empty==1) opts[0] = new Option('---','');
			jq.each(new_opts, function(index, value) {opts[opts.length] = new Option(value,index);});
			if(this.def_val!='') {
				this.obj.value = this.def_val;
				this.def_val='';
			} else
				this.obj.value = opts[0].value;
			setTimeout(function(){jq(this.obj).trigger('e_u_cd:load')},1);
			jq(this.obj).change();
		}
	}
};

function Utils_CommonData_freeze(id,cd){
    this.initialize(id,cd);
}
Utils_CommonData_freeze.prototype = {
	obj:null,
	path:null,
	id:null,
	initialize: function(id,cd) {
		var _this = this;
		this.id = id;
		this.obj = jq('#'+id);
		if(!this.obj.length) return;
		this.obj = this.obj.get(0);
		this.path = jq.parseJSON(cd);
		var obj = this.obj;
		var prev_obj = eval('obj.form.'+this.path[this.path.length-1]);
		if(this.path.length>2)
			jq(prev_obj).on('e_u_cd:load',function(e){_this.request.call(_this,e)});

		if(this.path.length==2)
			jq(document).one('e:load',function(e){_this.request.call(_this,null)});
	},

	request: function(e) {
		var obj = this.obj;
		var curr_root = this.path[0];
		for(var i=1; i<this.path.length; i++) {
			var val = eval('obj.form.'+this.path[i]).value;
			if(val=='') {
				jq('#'+this.id+'_label').html('---');
				setTimeout(function(){jq(this.obj).trigger('e_u_cd:load')},1);
				return;
			}
			curr_root += '/' + val;
		}
		if(this.obj.value=='') {
			jq('#'+this.id+'_label').html('---');
			setTimeout(function(){jq(this.obj).trigger('e_u_cd:load')},1);
			return;
		}
		curr_root += '/' + this.obj.value;
		var _this = this;
		jq.ajax('modules/Utils/CommonData/update_freeze.php',{
				method:'post',
				data:{
					value: curr_root
				},
				success: function(a,b,c){_this.on_request.call(_this,a,b,c)}
			});
	},
	on_request: function(t) {
		if (!t) return;
		var val = jq.parseJSON(t);
		jq('#'+this.id+'_label').html(val);
		setTimeout(function(){jq(this.obj).trigger('e_u_cd:load')},1);
	}
};
