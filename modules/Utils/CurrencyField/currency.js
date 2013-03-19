var Utils_CurrencyField = {
format:null,
re:null,
validate: function(ev,f) {
	var elem = Event.element(ev);
	var val = elem.value;
	var key = ev.which;
	if(!(key>=32 && key<=126)) return;
	var Ecar = jq(elem).caret().end;
	var Scar = jq(elem).caret().start;
	val = val.substring(0,Scar)+String.fromCharCode(key)+val.substring(Ecar);
	this.init_re(f);
	if(!this.re.test(val))
		Event.stop(ev);
	if(!this.re.test(elem.value)) {
		elem.value='';
	}
},
validate_blur: function(ev,f) {
	var elem = Event.element(ev);
	this.init_re(f);
	if(!this.re.test(elem.value)) {
		elem.value='';
	}
},
init_re: function(f) {
	if(this.format!=f) {
		this.re = new RegExp();
		this.re.compile('^'+f+'$');
		this.format=f;
	}
},
};


(function($,len,createRange,duplicate){
	$.fn.caret=function(options,opt2){
		var start,end,t=this[0],browser=$.browser.msie;
		if(typeof options==="object" && typeof options.start==="number" && typeof options.end==="number") {
			start=options.start;
			end=options.end;
		} else if(typeof options==="number" && typeof opt2==="number"){
			start=options;
			end=opt2;
		} else if(typeof options==="string"){
			if((start=t.value.indexOf(options))>-1) end=start+options[len];
			else start=null;
		} else if(Object.prototype.toString.call(options)==="[object RegExp]"){
			var re=options.exec(t.value);
			if(re != null) {
				start=re.index;
				end=start+re[0][len];
			}
		}
		if(typeof start!="undefined"){
			if(browser){
				var selRange = this[0].createTextRange();
				selRange.collapse(true);
				selRange.moveStart('character', start);
				selRange.moveEnd('character', end-start);
				selRange.select();
			} else {
				this[0].selectionStart=start;
				this[0].selectionEnd=end;
			}
			this[0].focus();
			return this
		} else {
           if(browser){
				var selection=document.selection;
                if (this[0].tagName.toLowerCase() != "textarea") {
                    var val = this.val(),
                    range = selection[createRange]()[duplicate]();
                    range.moveEnd("character", val[len]);
                    var s = (range.text == "" ? val[len]:val.lastIndexOf(range.text));
                    range = selection[createRange]()[duplicate]();
                    range.moveStart("character", -val[len]);
                    var e = range.text[len];
                } else {
                    var range = selection[createRange](),
                    stored_range = range[duplicate]();
                    stored_range.moveToElementText(this[0]);
                    stored_range.setEndPoint('EndToEnd', range);
                    var s = stored_range.text[len] - range.text[len],
                    e = s + range.text[len]
                }
            } else {
				var s=t.selectionStart,
					e=t.selectionEnd;
			}
			var te=t.value.substring(s,e);
			return {start:s,end:e,text:te,replace:function(st){
				return t.value.substring(0,s)+st+t.value.substring(e,t.value[len])
			}}
		}
	}
})(jQuery,"length","createRange","duplicate");
