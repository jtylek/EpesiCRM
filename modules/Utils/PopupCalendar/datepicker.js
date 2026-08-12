var Utils_PopupCalendarDatePicker = {
format:null,
re:null,
validate: function(ev,f) {
	var elem = ev.target;
	var val = elem.value;
	var key = ev.which;
	if(!(key>=32 && key<=126)) return;
	var car = this.get_caret(elem);
	val = val.substring(0,car)+String.fromCharCode(key)+val.substring(car);
	this.init_re(f);
	if(!this.re.test(val))
		{ ev.preventDefault(); ev.stopPropagation(); }
	// The redundant elem.value re-check that used to live here (alert()+
	// clear the whole field whenever the value-so-far didn't fully match)
	// fired on every single keystroke of a still-incomplete date - e.g.
	// typing "0" for the month before reaching the "/" separator, which
	// alone never matches - not just on genuinely invalid input. preventDefault()
	// above already blocks this keystroke outright whenever the prospective
	// value doesn't match, so elem.value only ever holds characters this same
	// regex already accepted a keystroke earlier via that same guard;
	// re-testing it here just re-flagged an in-progress, not-yet-complete
	// date as "invalid" - reported as the "Invalid date - clearing" alert
	// reappearing while creating a contact. Validating the settled value
	// belongs in validate_blur() (on leaving the field), not interrupting
	// every keystroke while the user is still mid-entry.
},
validate_blur: function(ev,f) {
	var elem = ev.target;
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
		this.re.compile('^('+this.format2regexp(f)+')?$');
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
	if ('selectionStart' in input)
		return input.selectionEnd;
	var range = document.selection.createRange();
	var isCollapsed = range.compareEndPoints("StartToEnd", range) == 0;
	if (!isCollapsed)
		range.collapse(false);
	var b = range.getBookmark();
	return b.charCodeAt(2) - 2;
}
}


