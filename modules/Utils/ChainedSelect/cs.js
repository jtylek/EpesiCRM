var ChainedSelect = Class.create();
ChainedSelect.prototype = {
	prev_ids:null,
	req_url:'',
	dest_id:null,
	params:null,
	default_val:null,
	initialize:function(dest_id,prev_ids,req_url,params,def_val) {
		if($(dest_id)==null)return;
		this.prev_ids = prev_ids;
		this.req_url = req_url;
		this.dest_id = dest_id;
		this.params = params;
		this.default_val = def_val;
		var prev_obj = prev_ids[prev_ids.length-1];
		Event.observe(prev_obj,'change',this.request.bindAsEventListener(this));
		Event.observe(prev_obj,'e_cs:load',this.request.bindAsEventListener(this));
		Event.observe(prev_obj,'e_cs:clear',this.clear.bindAsEventListener(this));
		if(prev_ids.length==1)
			Event.observe(document,'e:load',this.load_def.bindAsEventListener(this));
	},
	load_def:function() {
		this.request(this.default_val);
	},
	clear:function(){
		obj.options.length=0;
		obj.fire('e_cs:clear');
		obj.disabled=true;
	},
	request:function(def_val) {
		var vals = new Hash();
		for(x in this.prev_ids) {
			var p = $(this.prev_ids[x]);
			if(p==null) return;
			vals.set(this.prev_ids[x],p.value);
		}
		var dest_id = this.dest_id;
		new Ajax.Request('modules/Utils/ChainedSelect/req.php', {
			method: 'post',
			parameters:{
				values:Object.toJSON(vals),
				req_url:this.req_url,
				parameters:Object.toJSON(this.params),
				cid: Epesi.client_id
			},
			onSuccess:function(t) {
				var new_opts = t.responseText.evalJSON();
				var obj = $(dest_id);
				var opts = obj.options;
				opts.length=0;
				if(new_opts.length==0) {
					obj.fire('e_cs:clear');
					obj.disabled=true;
				} else {
					obj.disabled=false;
					for(y in new_opts) {
						opts[opts.length] = new Option(new_opts[y],y);
					}
					setTimeout(obj.fire.bind(obj,'e_cs:load'),1);
				}
				if(typeof def_val != 'undefined')
					obj.value = def_val;
			}
		});
	}
};