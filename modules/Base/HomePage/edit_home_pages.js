base_home_page__clearance = 0;
base_home_page__clearance_max = 0;
var base_home_page__initialized;

base_home_page__init_clearance = function (current, max) {
	if (!base_home_page__initialized) 
		base_home_page__clearance = current;
	base_home_page__clearance_max = max;
	if (base_home_page__clearance+1==base_home_page__clearance_max)
		jq('#add_clearance').hide();
	for (i=0; i<max; i++) {
	    if(i<=base_home_page__clearance)
	        jq('#div_clearance_'+i).show();
	    else
	        jq('#div_clearance_'+i).hide();
	}
}
base_home_page__add_clearance = function () {
	base_home_page__clearance++;
	if (base_home_page__clearance+1==base_home_page__clearance_max)
		jq('#add_clearance').hide();
	jq('#div_clearance_'+base_home_page__clearance).show();
}
