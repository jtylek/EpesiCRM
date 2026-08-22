function Utils_ChainedSelect(dest_id,prev_ids,params,def_val) {
	this.loads = 0;
	var dest = document.getElementById(dest_id);
	if(dest==null)return;
	this.prev_ids = prev_ids;
	this.dest_id = dest_id;
	this.params = params;
	this.default_val = def_val;
	var prev_obj = document.getElementById(prev_ids[prev_ids.length-1]);
	this.request_f = this.request.bind(this);
	this.clear_f = this.clear.bind(this);
	jQuery(prev_obj).on('change',this.request_f);
	jQuery(prev_obj).on('e_cs:load',this.request_f);
	jQuery(prev_obj).on('e_cs:clear',this.clear_f);
	this.stop_f = this.stop.bind(this);
	jQuery(document).on('e:load',this.stop_f);
	if(prev_ids.length==1) {
		this.load_def_f = this.load_def.bind(this);
		jQuery(document).on('e:load',this.load_def_f);
	}
}

Utils_ChainedSelect.prototype.load_def = function() {
	this.request();
};

Utils_ChainedSelect.prototype.clear = function(){
	obj.options.length=0;
	obj.fire('e_cs:clear');
};

Utils_ChainedSelect.prototype.stop = function(){
	this.loads++;
	if(this.loads==2) {
		var prev_obj = document.getElementById(this.prev_ids[this.prev_ids.length-1]);
		if(prev_obj!=null) {
			jQuery(prev_obj).off('change',this.request_f);
			jQuery(prev_obj).off('e_cs:load',this.request_f);
			jQuery(prev_obj).off('e_cs:clear',this.clear_f);
		}
		if(this.prev_ids.length==1)
			jQuery(document).off('e:load',this.load_def_f);
		jQuery(document).off('e:load',this.stop_f);
	}
};

Utils_ChainedSelect.prototype.request = function() {
		var vals = {};
		if(this.default_val!=null) {
			var def_val = this.default_val;
			this.default_val = null;
		}
		for(x in this.prev_ids) {
			var p = document.getElementById(this.prev_ids[x]);
			if(p==null) return;
			vals[this.prev_ids[x]] = p.value;
		}
		var dest_id = this.dest_id;
		jQuery.ajax('modules/Utils/ChainedSelect/req.php', {
			method: 'post',
			data:{
				values:JSON.stringify(vals),
				dest_id:dest_id,
				parameters:JSON.stringify(this.params),
				defaults:JSON.stringify(def_val),
				cid: Epesi.client_id
			},
			dataType: 'text',
			success:function(responseText) {
				var new_opts = JSON.parse(responseText);
				var obj = document.getElementById(dest_id);
				if(!jq(obj).is('select')){
				    return;
				}
				var opts = obj.options;
                if(new_opts == false) {
                    obj.setAttribute("oldDisplayValue", obj.style.display);
                //    obj.style.display = "none";
                    while(opts.length > 0) obj.remove(0);
                    jQuery(obj).trigger('e_cs:clear');
                    obj.disabled = true;
                    return;
                } else {
                 //   var val = obj.getAttribute("oldDisplayValue");
                 //   if(val != undefined)
                        obj.style.display = "block";
                }
				while(opts.length > 0) obj.remove(0);
				if(new_opts.length==0) {
					jQuery(obj).trigger('e_cs:clear');
                    obj.disabled = true;
				} else {
                    obj.disabled = false;
					if(Array.isArray(new_opts)) {
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
					setTimeout(function(){jQuery(obj).trigger('e_cs:load');},1);
				}
			}
		});
};