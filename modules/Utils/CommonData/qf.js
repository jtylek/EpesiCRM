function Utils_CommonData(id,val,cd,ae,order) {
	this.obj = document.getElementById(id);
	if(this.obj==null) return;
	this.path = JSON.parse(cd);
	this.add_empty = ae;
	this.def_val = val;
	this.order = order;
	var obj = this.obj;
	var prev_obj = eval('obj.form.'+this.path[this.path.length-1]);
	jQuery(prev_obj).on('change',this.request.bind(this));
	jQuery(prev_obj).on('e_u_cd:load',this.request.bind(this));
	jQuery(prev_obj).on('e_u_cd:clear',function(){obj.options.length=0;jQuery(obj).trigger('e_u_cd:clear');});

	this.first_request_bind = this.first_request.bind(this);
	if(this.path.length==2)
		jQuery(document).on('e:load',this.first_request_bind);
}

Utils_CommonData.prototype.first_request = function(e) {
	jQuery(document).off('e:load',this.first_request_bind);
//	alert('first');
	this.request(null);
};

Utils_CommonData.prototype.request = function(e) {
	var obj = this.obj;
//	alert('request '+obj.name);
	obj.options.length=0;
	var curr_root = this.path[0];
	for(var i=1; i<this.path.length; i++) {
		var val = eval('obj.form.'+this.path[i]).value;
		if(val=='') {
			this.obj.options.length=0;
			jQuery(this.obj).trigger('e_u_cd:clear');
//			setTimeout(this.obj.fire.bind(this.obj,'e_u_cd:clear'),1);
			return;
		}
		curr_root += '/' + val;
	}
	jQuery.ajax('modules/Utils/CommonData/update.php',{
			method:'post',
			data:{
				value: curr_root,
				order: this.order
			},
			dataType: 'text',
			success: this.on_request.bind(this)
		});
};

Utils_CommonData.prototype.on_request = function(responseText) {
	if (!responseText) return;
	var new_opts = JSON.parse(responseText);
	var opts = this.obj.options;
	opts.length=0;
	if(new_opts.length==0) {
		jQuery(this.obj).trigger('e_u_cd:clear');
	} else {
		if(this.add_empty==1) opts[0] = new Option('---','');
		jq.each(new_opts, function(index, value) {opts[opts.length] = new Option(value,index);});
		if(this.def_val!='') {
			this.obj.value = this.def_val;
			this.def_val='';
		} else
			this.obj.value = opts[0].value;
//		alert('fire='+this.obj.name+' valyx='+opts[0].value);
//		this.obj.fire('e_u_cd:load');
		var obj = this.obj;
		setTimeout(function(){jQuery(obj).trigger('e_u_cd:load');},1);
		jq(this.obj).change();
	}
};

function Utils_CommonData_freeze(id,cd) {
	this.id = id;
	this.obj = document.getElementById(id);
	if(this.obj==null) return;
	this.path = JSON.parse(cd);
	var obj = this.obj;
	var prev_obj = eval('obj.form.'+this.path[this.path.length-1]);
	if(this.path.length>2)
		jQuery(prev_obj).on('e_u_cd:load',this.request.bind(this));

	this.first_request_bind = this.first_request.bind(this);
	if(this.path.length==2)
		jQuery(document).on('e:load',this.first_request_bind);
}

Utils_CommonData_freeze.prototype.first_request = function(e) {
	jQuery(document).off('e:load',this.first_request_bind);
	//alert('first');
	this.request(null);
};

Utils_CommonData_freeze.prototype.request = function(e) {
	var obj = this.obj;
	var curr_root = this.path[0];
	for(var i=1; i<this.path.length; i++) {
		var val = eval('obj.form.'+this.path[i]).value;
		if(val=='') {
			document.getElementById(this.id+'_label').innerHTML = '---';
			var obj = this.obj;
			setTimeout(function(){jQuery(obj).trigger('e_u_cd:load');},1);
			return;
		}
		curr_root += '/' + val;
	}
	if(this.obj.value=='') {
		document.getElementById(this.id+'_label').innerHTML = '---';
		var obj = this.obj;
		setTimeout(function(){jQuery(obj).trigger('e_u_cd:load');},1);
		return;
	}
	curr_root += '/' + this.obj.value;
//	alert('request '+obj.name+'; root '+curr_root);
	jQuery.ajax('modules/Utils/CommonData/update_freeze.php',{
			method:'post',
			data:{
				value: curr_root
			},
			dataType: 'text',
			success: this.on_request.bind(this)
		});
};

Utils_CommonData_freeze.prototype.on_request = function(responseText) {
	if (!responseText) return;
	var val = JSON.parse(responseText);
	document.getElementById(this.id+'_label').innerHTML = val;
	var obj = this.obj;
	setTimeout(function(){jQuery(obj).trigger('e_u_cd:load');},1);
};
