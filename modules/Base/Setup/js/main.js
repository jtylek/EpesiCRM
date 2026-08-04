var mentioned = new Array;
var base_setup__reinstall_warning = '';
var base_setup__uninstall_warning = '';
function get_deps(mod) {
	var arr = new Array;
	if(mentioned[mod] == undefined) {
		arr.push(mod);
		mentioned[mod] = true;
	}
	if(deps[mod] == undefined) return arr;
	for(var i = 0; i < deps[mod].length; i++) {
		arr = arr.concat(get_deps(deps[mod][i]));
	}
	return arr;
};
function show_alert(x, mod) {
	if(x.options[x.selectedIndex].value == -2) {
		if(!showed) {
			if (typeof epesi_alert === 'function') epesi_alert(base_setup__reinstall_warning);
			else alert(base_setup__reinstall_warning);
		}
		showed=true;
		return;
	}
	if(x.selectedIndex != 0) {
		original_select[mod] = x.options[x.selectedIndex].value;
		return;
	}
	mentioned = new Array;
	var arr = get_deps(mod);
	if(arr.length == 1) return;
	var str = arr.length < 11 ? " - "+arr.join("\n - ") : arr.join(", ");
	var revert = function() {
		var ind = 0;
		for(; ind < x.options.length; ind++) if(x.options[ind].value == original_select[mod]) break;
		x.selectedIndex = ind;
	};
	var proceed = function() {
		for(var i = 0; i < arr.length; i++) {
			var el = document.getElementsByName("installed["+arr[i]+"]")[0];
			el.selectedIndex=0;
		}
	};
	if (typeof epesi_confirm === 'function') {
		epesi_confirm(base_setup__uninstall_warning + "\n" + str, proceed, revert);
		return;
	}
	if(confirm(base_setup__uninstall_warning + "\n" + str) == false) {
		revert();
		return;
	}
	proceed();
}
