var Utils_CommonData = {
request:function(e,cd_root,name,max_id) {
	var self = Event.element(e);
	var dest = $(name+'_'+max_id);
	for(var i=0; i<max_id-1; i++)
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
						$H(new_opts).each(function(x,y) {opts[y] = new Option(x[1],x[0]);});
						dest.disabled=false;
					}
					for(var i=max_id+1, ed=null; ed=$(name+'_'+i);i++) {
						ed.disabled=true;
						ed.options.length=true;
					}
				}
			});
},
init:function(cd_root,name,max_id) {
for(var i=1; i<max_id; i++) {
	$(name+'_'+i).disabled=true;
	Event.observe(name+'_'+(i-1),'change',this.request.bindAsEventListener(this,cd_root,name,i));
}
}
};
