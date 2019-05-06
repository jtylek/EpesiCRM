var Utils_ChainedSelect = Class.create();
Utils_ChainedSelect.prototype = {
	prev_ids:null,
	dest_id:null,
	params:null,
	default_val:null,
	request_f:null,
	clear_f:null,
	load_def_f:null,
	stop_f:null,
	loads:0,
	initialize:function(dest_id,prev_ids,params,def_val) {
		var dest = $(dest_id);
		if(dest==null)return;
		this.prev_ids = prev_ids;
		this.dest_id = dest_id;
		this.params = params;
		this.default_val = def_val;
		var prev_obj = prev_ids[prev_ids.length-1];
		this.request_f = this.request.bindAsEventListener(this);
		this.clear_f = this.clear.bindAsEventListener(this);
		Event.observe(prev_obj,'change',this.request_f);
		Event.observe(prev_obj,'e_cs:load',this.request_f);
		Event.observe(prev_obj,'e_cs:clear',this.clear_f);
		this.stop_f = this.stop.bindAsEventListener(this);
		Event.observe(document,'e:load',this.stop_f);
		if(prev_ids.length==1) {
			this.load_def_f = this.load_def.bindAsEventListener(this);
			Event.observe(document,'e:load',this.load_def_f);
		}
	},
	load_def:function() {
		this.request();
	},
	clear:function(){
		obj.options.length=0;
		obj.fire('e_cs:clear');
	},
	stop:function(){
		this.loads++;
		if(this.loads==2) {
			var prev_obj = this.prev_ids[this.prev_ids.length-1];
			if($(prev_obj)!=null) {
				Event.stopObserving(prev_obj,'change',this.request_f);
				Event.stopObserving(prev_obj,'e_cs:load',this.request_f);
				Event.stopObserving(prev_obj,'e_cs:clear',this.clear_f);
			}
			if(this.prev_ids.length==1)
				Event.stopObserving(document,'e:load',this.load_def_f);
			Event.stopObserving(document,'e:load',this.stop_f);
		}
	},
	request:function() {
		var vals = new Hash();
		if(this.default_val!=null) {
			var def_val = this.default_val;
			this.default_val = null;
		}
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
				dest_id:dest_id,
				parameters:Object.toJSON(this.params),
				defaults:Object.toJSON(def_val),
				cid: Epesi.client_id
			},
			onSuccess:function(t) {
				var new_opts = t.responseText.evalJSON();
				var obj = $(dest_id);
				if(!jq(obj).is('select')){
				    return;
				}
				var opts = obj.options;
                if(new_opts == false) {
                    obj.setAttribute("oldDisplayValue", obj.style.display);
                //    obj.style.display = "none";
                    return;
                } else {
                 //   var val = obj.getAttribute("oldDisplayValue");
                 //   if(val != undefined)
                        obj.style.display = "block";
                }
				while(opts.length > 0) obj.remove(0);
				if(new_opts.length==0) {
					obj.fire('e_cs:clear');
                    obj.disabled = true;
				} else {
                    obj.disabled = false;
					if(Object.isArray(new_opts)) {
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
						obj.value = def_val;
					else
						obj.value = '';
					jq(obj).change();
					setTimeout(obj.fire.bind(obj,'e_cs:load'),1);
				}
			}
		});
	}
};