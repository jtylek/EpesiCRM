base_acl__clearance = 0;
base_acl__clearance_max = 0;
var base_acl__initialized;

base_acl__init_clearance = function (current, max) {
	if (!base_acl__initialized) 
		base_acl__clearance = current;
	base_acl__clearance_max = max;
	if (base_acl__clearance+1==base_acl__clearance_max)
		jq('#add_clearance').hide();
	for (i=0; i<max; i++) {
        if(i<=base_acl__clearance)
		    jq('#div_clearance_'+i).show();
        else
            jq('#div_clearance_'+i).hide();
    }
}
base_acl__add_clearance = function () {
	base_acl__clearance++;
	if (base_acl__clearance+1==base_acl__clearance_max)
		jq('#add_clearance').hide();
	jq('#div_clearance_'+base_acl__clearance).show();
}
