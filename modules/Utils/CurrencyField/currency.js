function varDump(variable, maxDeep)
{
	var deep = 0;
	var maxDeep = maxDeep || 5;

	function fetch(object, parent)
	{
		var buffer = '';
		deep++;

		for (var i in object) {
			if (parent) {
				objectPath = parent + '.' + i;
			} else {
				objectPath = i;
			}

			buffer += objectPath + ' (' + typeof object[i] + ')';

			if (typeof object[i] == 'object') {
				buffer += "\n";
				if (deep < maxDeep) {
					buffer += fetch(object[i], objectPath);
				}
			} else if (typeof object[i] == 'function') {
				buffer += "\n";
			} else if (typeof object[i] == 'string') {
				buffer += ': "' + object[i] + "\"\n";
			} else {
				buffer += ': ' + object[i] + "\n";
			}
		}

		deep--;
		return buffer;
	}

	if (typeof variable == 'object') {
		return fetch(variable);
	}

	return '(' + typeof variable + '): ' + variable + "\n";
}


var Utils_CurrencyField = {
format:null,
re:null,
validate: function(ev,f) {
	var elem = Event.element(ev);
	var val = elem.value;
	var key = ev.which;
	if(!(key>=32 && key<=126)) return;
	var Ecar = this.get_caret_end(elem);
	var Scar = this.get_caret_start(elem);
	val = val.substring(0,Scar)+String.fromCharCode(key)+val.substring(Ecar);
	this.init_re(f);
	if(!this.re.test(val))
		Event.stop(ev);
	if(!this.re.test(elem.value)) {
//		alert('Invalid date - clearing');
		elem.value='';
	}
},
validate_blur: function(ev,f) {
	var elem = Event.element(ev);
	this.init_re(f);
	if(!this.re.test(elem.value)) {
//		alert('Invalid date - clearing');
		elem.value='';
	}
},
init_re: function(f) {
	if(this.format!=f) {
		this.re = new RegExp();
//		alert(f);
		this.re.compile('^'+f+'$');
		this.format=f;
	}
},
// get_caret_end method based on work of:
// Author: Mihai Bazon, 2006
// http://www.bazon.net/mishoo/
// This code is (c) Dynarch.com, 2006.
// GNU LGPL. (www.gnu.org/licenses/lgpl.html)
get_caret_end: function(input) {
	if (Prototype.Browser.Gecko)
		return input.selectionEnd;
	return input.value.length;
},
get_caret_start: function(input) {
	if (Prototype.Browser.Gecko)
		return input.selectionStart;
	return input.value.length;
}
}


