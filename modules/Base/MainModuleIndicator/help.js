var help = {
max:0,
sw:function(id) {
for(var i=0; i<help.max; i++)
	document.getElementById('help_'+i).style.display = 'none';
document.getElementById('help_'+id).style.display = '';
help.resize_frame(id);
},
resize_frame:function(id) {
var d = document.getElementById('help_'+id+'_frame');
if(!d.contentDocument) {
	setTimeout('help.resize_frame("'+id+'")',300);
	return;
}
d.height = Math.max(d.contentDocument.body.offsetHeight,d.contentDocument.body.scrollHeight)+30;
},
};
