containments = new Array();
add_cointainment = function(id) {
	alert(id);
	containments.push(id);
}
create_containment = function(id) {
   Sortable.create(id,
     {dropOnEmpty:true,containment:containments,constraint:false});
}
