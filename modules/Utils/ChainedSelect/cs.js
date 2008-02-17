var ChainedSelect = Class.create();
ChainedSelect.prototype = {
	prev_ids:null,
	req_url:'',
	dest_id:null,
	initialize:function(dest_id,prev_ids,req_url) {
		this.prev_ids = prev_ids;
		this.req_url = req_url;
		this.dest_id = dest_id;
		var prev_obj = prev_ids[prev_ids.length-1];
		this.request();
		Event.observe(prev_obj,'change',this.request.bindAsEventListener(this));
		Event.observe(prev_obj,'e_cs:load',this.request.bindAsEventListener(this));
		Event.observe(prev_obj,'e_cs:clear',this.clear.bindAsEventListener(this));
	},
	clear:function(){
		obj.options.length=0;
		obj.fire('e_cs:clear');
		obj.disabled=true;
	},
	request:function() {
		var vals = new Hash();
//		var vals = new Array();
		for(x in this.prev_ids) {
			vals.set(this.prev_ids[x],$(this.prev_ids[x]).value);
//			vals[x] = $(this.prev_ids[x]).value;
			//if is undefined disable dest_id
		}
		var dest_id = this.dest_id;
		new Ajax.Request('modules/Utils/ChainedSelect/req.php', {
			method: 'post',
			parameters:{
				values:Object.toJSON(vals),
				req_url:this.req_url
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
			}
		});
	}
};