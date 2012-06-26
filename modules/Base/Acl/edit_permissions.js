base_acl__clearance = 0;
base_acl__clearance_max = 0;
var base_acl__initialized;

base_acl__init_clearance = function (current, max) {
	if (!base_acl__initialized) 
		base_acl__clearance = current;
	base_acl__clearance_max = max;
	if (base_acl__clearance+1==base_acl__clearance_max)
		$('add_clearance').style.display = 'none';
	for (i=0; i<max; i++)
		$('div_clearance_'+i).style.display = (i<=base_acl__clearance)?'':'none';
}
base_acl__add_clearance = function () {
	base_acl__clearance++;
	if (base_acl__clearance+1==base_acl__clearance_max)
		$('add_clearance').style.display = 'none';
	$('div_clearance_'+base_acl__clearance).style.display = '';
}
