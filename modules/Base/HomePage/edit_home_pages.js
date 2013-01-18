base_home_page__clearance = 0;
base_home_page__clearance_max = 0;
var base_home_page__initialized;

base_home_page__init_clearance = function (current, max) {
	if (!base_home_page__initialized) 
		base_home_page__clearance = current;
	base_home_page__clearance_max = max;
	if (base_home_page__clearance+1==base_home_page__clearance_max)
		$('add_clearance').style.display = 'none';
	for (i=0; i<max; i++)
		$('div_clearance_'+i).style.display = (i<=base_home_page__clearance)?'':'none';
}
base_home_page__add_clearance = function () {
	base_home_page__clearance++;
	if (base_home_page__clearance+1==base_home_page__clearance_max)
		$('add_clearance').style.display = 'none';
	$('div_clearance_'+base_home_page__clearance).style.display = '';
}
