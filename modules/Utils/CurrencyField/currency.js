var Utils_CurrencyField = {
format:null,
re:null,
currencies:null,
format_amount:function(val,currency){
	var currency = Utils_CurrencyField.currencies[currency];
	var prec = Math.pow(10, currency['dec_digits']);
	var all=Math.round(val*prec);
	if(isNaN(all)) return '';
	var all=all.toString(10);
	var first=all.substr(0,all.length-currency['dec_digits']);
    // get string padded at the beggining with zeros
	var second=("0000000000" + all).substr(-currency['dec_digits']);
	if(first=='') first = '0';
	return first+currency['decp']+second;
},
format_currency:function(val,currency) {
	var currency = Utils_CurrencyField.currencies[currency];
	var prec = Math.pow(10, currency['dec_digits']);
	var all=Math.round(val*prec);
	if(isNaN(all)) return '';
	var all=all.toString(10);
	var first=all.substr(0,all.length-currency['dec_digits']);
    // get string padded at the beggining with zeros
	var second=("0000000000" + all).substr(-currency['dec_digits']);
	var thsd = first==''?0:parseInt(first);
	var first_clean = '';
	do {
		var rest = thsd-parseInt(thsd/1000)*1000;
		thsd = parseInt(thsd/1000);
		if (first_clean!=='') first_clean = currency['thop']+first_clean;
		while ((rest.length<3 || rest===0) && thsd>0)rest="0"+rest;
		first_clean = rest+first_clean;
	} while (thsd>0);
	return currency['symbol_before']+' '+first_clean+currency['decp']+second+' '+currency['symbol_after'];
},
round:function(val,currency) {
	if(val=='') return '';
	var currency = Utils_CurrencyField.currencies[currency];
	var prec = Math.pow(10, currency['dec_digits']);
	var all=Math.round(val*prec);
	if(isNaN(all)) return '';
	return all/prec;
},
validate: function(ev) {
	var elem = ev.target;
	var currency = Utils_CurrencyField.currencies[jq('#__'+elem.id+'__currency').val()];
	var val = elem.value;
	var key = ev.which;
	if(!(key>=32 && key<=126)) return;
	var Ecar = jq(elem).caret().end;
	var Scar = jq(elem).caret().start;
	val = val.substring(0,Scar)+String.fromCharCode(key)+val.substring(Ecar);
	Utils_CurrencyField.init_re(currency.regex);
	if(!Utils_CurrencyField.re.test(val)) {
		ev.preventDefault();
	}
	if(!Utils_CurrencyField.re.test(elem.value)) {
		elem.value='';
	}
},
validate_blur: function(ev,f) {
	var elem = ev.target;
	var currency = Utils_CurrencyField.currencies[jq('#__'+elem.id+'__currency').val()];
	Utils_CurrencyField.init_re(currency.regex);
	if(!Utils_CurrencyField.re.test(elem.value)) {
		elem.value='';
	}
},
init_re: function(f) {
	if(Utils_CurrencyField.format!=f) {
		Utils_CurrencyField.re = new RegExp();
		Utils_CurrencyField.re.compile('^'+f+'$');
		Utils_CurrencyField.format=f;
	}
},
};


(function($,len,createRange,duplicate){
	$.fn.caret=function(options,opt2){
		var start,end,t=this[0];
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
			this[0].selectionStart=start;
			this[0].selectionEnd=end;
			this[0].focus();
			return this
		} else {
			var s=t.selectionStart,
					e=t.selectionEnd;
			var te=t.value.substring(s,e);
			return {start:s,end:e,text:te,replace:function(st){
				return t.value.substring(0,s)+st+t.value.substring(e,t.value[len])
			}}
		}
	}
})(jQuery,"length","createRange","duplicate");
