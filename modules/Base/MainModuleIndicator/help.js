var help = {
max:0,
sw:function(id) {
for(var i=0; i<help.max; i++)
	jq('#help_'+i).hide();
jq('#help_'+id).show();
help.resize_frame(id);
},
resize_frame:function(id) {
var d = jq('#help_'+id+'_frame').get(0);
if(!d.contentDocument) {
	setTimeout('help.resize_frame("'+id+'")',300);
	return;
}
d.height = Math.max(d.contentDocument.body.offsetHeight,d.contentDocument.body.scrollHeight)+30;
},
};
