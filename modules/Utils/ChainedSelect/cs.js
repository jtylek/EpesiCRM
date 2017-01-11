function ChainedSelect(dest_id,prev_ids,params,def_val) {
    this.initialize(dest_id,prev_ids,params,def_val);
}
ChainedSelect.prototype = {
	prev_ids:null,
	dest_id:null,
	params:null,
	request_f:null,
	clear_f:null,
	load_def_f:null,
	stop_f:null,
	default_val:null,
	loads:0,
	initialize:function(dest_id,prev_ids,params,def_val) {
		var dest = jq('#'+dest_id).get(0);
		if(dest==null)return;
		this.prev_ids = prev_ids;
		this.dest_id = dest_id;
		this.params = params;
		this.default_val = def_val;
		var prev_obj = prev_ids[prev_ids.length-1];
		var _this = this;
		this.request_f = function(){_this.request.call(_this)};
		this.clear_f = function(){_this.clear.call(_this)};
		this.stop_f = function(){_this.stop.call(_this)};
		this.load_def_f = function(){_this.load_def.call(_this)};
		jq('#'+prev_obj).on('change',this.request_f);
		jq('#'+prev_obj).on('e_cs:load',this.request_f);
		jq('#'+prev_obj).on('e_cs:clear',this.clear_f);
		jq(document).on('e:load',this.stop_f);
		if(prev_ids.length==1) {
			jq(document).on('e:load',this.load_def_f);
		}
	},
	load_def:function() {
		this.request();
	},
	clear:function(){
		var obj = jq('#'+this.dest_id);
		obj.get(0).options.length=0;
		obj.trigger('e_cs:clear');
	},
	stop:function(){
		this.loads++;
		if(this.loads==2) {
			var prev_obj = this.prev_ids[this.prev_ids.length-1];
			var prev = jq('#'+prev_obj);
			if(prev.length) {
				prev.off('change',this.request_f);
				prev.off('e_cs:load',this.request_f);
				prev.off('e_cs:clear',this.clear_f);
			}
			if(this.prev_ids.length==1)
				jq(document).off('e:load',this.load_def_f);
			jq(document).off('e:load',this.stop_f);
		}
	},
	request:function() {
		var vals = {};
		if(this.default_val!=null) {
			var def_val = this.default_val;
			this.default_val = null;
		}
		for(x in this.prev_ids) {
			var p = jq('#'+this.prev_ids[x]);
			if(!p.length) return;
			vals[this.prev_ids[x]] = p.val();
		}
		var dest_id = this.dest_id;
		jq.ajax({url: 'modules/Utils/ChainedSelect/req.php',
			method: 'post',
			data:{
				values:JSON.stringify(vals),
				dest_id:dest_id,
				parameters:JSON.stringify(this.params),
				defaults:JSON.stringify(def_val),
				cid: Epesi.client_id
			},
			success:function(t) {
				var new_opts = jq.parseJSON(t);
				var obj = jq('#'+dest_id);
				var opts = obj.get(0).options;
				opts.length=0;
				if(new_opts.length==0) {
					obj.trigger('e_cs:clear');
					obj.attr("disabled", true);
				} else {
					obj.attr("disabled", false);
					if(jq.isArray(new_opts)) {
						for(y=0; y<new_opts.length; y++) {
							if(typeof new_opts[y].key != "undefined" && typeof new_opts[y].caption != "undefined")
								opts[opts.length] = new Option(new_opts[y].caption,new_opts[y].key);
							else
								opts[opts.length] = new Option(new_opts[y],y);
						}
					} else {
						for(y in new_opts) {
							opts[opts.length] = new Option(new_opts[y],y);
						}
					}
					if(typeof def_val != 'undefined')
						obj.val(def_val);
					else
						obj.val('');
					jq(obj).change();
					setTimeout(function(){obj.trigger('e_cs:load');},1);
				}
			}
		});
	}
};