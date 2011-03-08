var Utils_PopupCalendarDatePicker = {
format:null,
re:null,
validate: function(ev,f) {
	var elem = Event.element(ev);
	var val = elem.value;
	var key = ev.which;
	if(!(key>=32 && key<=126)) return;
	var car = this.get_caret(elem);
	val = val.substring(0,car)+String.fromCharCode(key)+val.substring(car);
	this.init_re(f);
	if(!this.re.test(val))
		Event.stop(ev);
	if(!this.re.test(elem.value)) {
		alert('Invalid date - clearing');
		elem.value='';
	}
},
validate_blur: function(ev,f) {
	var elem = Event.element(ev);
	this.init_re(f);
	if(!this.re.test(elem.value)) {
		alert('Invalid date - clearing');
		elem.value='';
	}
},
init_re: function(f) {
	if(this.format!=f) {
		this.re = new RegExp();
//		alert(this.format2regexp(f));
		this.re.compile('^'+this.format2regexp(f)+'$');
		this.format=f;
	}
},
format2regexp: function(f) {
	return f.replace(new RegExp('^([%a-zA-Z/]+)/([%a-zA-Z/]+)$','g'),"$1(/$2)?")
		.replace(new RegExp('^([%a-zA-Z/]+)/([%a-zA-Z/?()]+)$','g'),"$1(/$2)?")
		.replace(new RegExp('^([%a-zA-Z-]+)-([%a-zA-Z-]+)$','g'),"$1(-$2)?")
		.replace(new RegExp('^([%a-zA-Z-]+)-([%a-zA-Z-?()]+)$','g'),"$1(-$2)?")
		.replace(new RegExp('^([%a-zA-Z ,]+) ([%a-zA-Z ]+)$','g'),"$1( $2)?")
		.replace(new RegExp('^([%a-zA-Z ]+),([%a-zA-Z ?()]+)$','g'),"$1(,$2)?")
		.replace(new RegExp('^([%a-zA-Z ]+) ([%a-zA-Z ?(),]+)$','g'),"$1( $2)?")
		.replace('%d','[0-3]?[0-9]?')
		.replace('%m','[0-1]?[0-9]?')
		.replace('%y','[0-9]{0,2}')
		.replace('%Y','[0-9]{0,4}')
		.replace('%b','[a-zA-ZáéíóäëöúàèììùąśżźćółńĄŚŻŹĆÓŁŃ]{0,3}')
		.replace('%B','[a-zA-ZáéíóäëöúàèììùąśżźćółńĄŚŻŹĆÓŁŃ]+');
},
// get_caret method based on work of:
// Author: Mihai Bazon, 2006
// http://www.bazon.net/mishoo/
// This code is (c) Dynarch.com, 2006.
// GNU LGPL. (www.gnu.org/licenses/lgpl.html)
get_caret: function(input) {
	if (Prototype.Browser.Gecko)
		return input.selectionEnd;
	var range = document.selection.createRange();
	var isCollapsed = range.compareEndPoints("StartToEnd", range) == 0;
	if (!isCollapsed)
		range.collapse(false);
	var b = range.getBookmark();
	return b.charCodeAt(2) - 2;
}
}


