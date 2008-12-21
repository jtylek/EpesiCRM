var help = {
max:0,
sw:function(id) {
for(var i=0; i<help.max; i++)
	$('help_'+i).hide();
$('help_'+id).show();
}
};
