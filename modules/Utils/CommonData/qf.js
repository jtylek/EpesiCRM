var Utils_CommonData = function(id,cd,add_empty) {
	var obj = $(id);
	var cd_path = cd.evalJSON();
	for(var i=1,max=cd_path.length-1; i<max; i++)
		Event.observe(eval('obj.form.'+cd_path[i]),'change',function(){obj.options.length=0;obj.disabled=true;});
	Event.observe(eval('obj.form.'+cd_path[cd_path.length-1]),'change',function(e){
		var self = Event.element(e);
		if(self.value=='') {
			obj.disabled=true;
			obj.options.length=0;
			return;
		}
		obj.options.length=0;
		var curr_root = cd_path[0];
		for(var i=1; i<cd_path.length; i++)
			curr_root += '/' + eval('obj.form.'+cd_path[i]).value;
		new Ajax.Request('modules/Utils/CommonData/update.php',{
				method:'post',
				parameters:{
					value: curr_root
				},
				onSuccess:function(t) {
					var new_opts = t.responseText.evalJSON();
					var opts = obj.options;
					opts.length=0;
					if(new_opts.length==0) obj.disabled=true;
					else {
						if(add_empty==1) opts[0] = new Option('---','');
						$H(new_opts).each(function(x) {opts[opts.length] = new Option(x[1],x[0]);});
						obj.disabled=false;
					}
				}
			});

	});
};
