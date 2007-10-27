var Utils_CommonData_group = function(cd_root,name,max_id,add_empty) {
this.request = function(e,mid) {
	var self = Event.element(e);
	if(self.value=='') {
		for(var i=mid, ed=null; ed=$(name+'_'+i);i++) {
			ed.disabled=true;
			ed.options.length=0;
		}
		return;
	}
	var dest = $(name+'_'+mid);
	for(var i=0; i<mid-1; i++)
		cd_root += '/' + $(name+'_'+i).value;
	new Ajax.Request('modules/Utils/CommonData/update.php',{
				method:'post',
				parameters:{
					value:cd_root+'/'+self.value
				},
				onSuccess:function(t) {
					eval(t.responseText);
					var opts = dest.options;
					opts.length=0;
					if(new_opts.length==0) dest.disabled=true;
					else {
						if(add_empty==1) opts[0] = new Option('---','');
						$H(new_opts).each(function(x) {opts[opts.length] = new Option(x[1],x[0]);});
						dest.disabled=false;
					}
					for(var i=mid+1, ed=null; ed=$(name+'_'+i);i++) {
						ed.disabled=true;
						ed.options.length=0;
					}
				}
			});
};
for(var i=1; i<max_id; i++) {
	$(name+'_'+i).disabled=true;
	Event.observe(name+'_'+(i-1),'change',this.request.bindAsEventListener(this,i));
}

};
