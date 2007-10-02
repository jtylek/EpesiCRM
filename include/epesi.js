/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
var loaded_csses="";
function _lcss(file) {
	if (loaded_csses.indexOf(file)!=-1) return false;
	fileref=document.createElement("link")
	fileref.setAttribute("rel", "stylesheet");
	fileref.setAttribute("type", "text/css");
	fileref.setAttribute("href", file);
	document.getElementsByTagName("head").item(0).appendChild(fileref);
	loaded_csses += file+" ";
	return true;
};
load_css = _lcss;

function focus_by_id(idd) {
	xx = document.getElementById(idd);
	if(xx) setTimeout('xx.focus();',200);
};

var loaded_jss="";
function _ljs(file) {
	if (loaded_jss.indexOf(file)!=-1) return false;
	fileref=document.createElement("script")
	fileref.setAttribute("type", "text/javascript");
	fileref.setAttribute("src", file);
	document.getElementsByTagName("head").item(0).appendChild(fileref);
	loaded_jss += file+" ";
	return true;
};
load_js = _ljs;

function _ajs(texti) {
	fileref=document.createElement("script")
	fileref.setAttribute("type", "text/javascript");
	fileref.text = texti;
	document.body.appendChild(fileref);
};
append_js = _ajs;

function addslashes(x){return x.replace(/('|"|\\)/g,"\\$1")}

function wait_while_null(id,action) {
	if(eval('typeof('+id+')') != 'undefined')
		eval(action);
	else
		setTimeout('wait_while_null(\''+addslashes(id)+'\', \''+addslashes(action)+'\')',200);
};

var Epesi = {
	procOn:0,
	client_id:0,
	process_file:'',
	indicator:'epesiStatus',
	updateIndicator: function(){
		var s = $(Epesi.indicator);
		if(s) s.style.visibility = Epesi.procOn ? 'visible' : 'hidden';
	},
	updateIndicatorText: function(text){
		$(Epesi.indicator).innerHTML = text;
	},
	request: function(url,history_id) {
		Epesi.procOn++;
		Epesi.updateIndicator();
		new Ajax.Request(Epesi.process_file, { 
			method: 'post', 
			parameters: {
				history: history_id,
				url: url
			},
			onComplete: function(t) {
				Epesi.procOn--;
				Epesi.updateIndicator();
			},
			onException: function(t,e) {
				throw(e);
			},
			onFailure: function(t) {
				alert('Failure');
			}
		});
	},
	href: function(url,indicator,mode) {
		if(Epesi.procOn==0 || mode=='allow'){
			if(indicator=='') indicator='loading...';
			Epesi.updateIndicatorText(indicator);
			Epesi.request(url);
		} else if(mode=='queue') 
			setTimeout('Epesi.href("'+href+'", "'+indicator+'", "'+mode+'")',500);
	},
	text: function(txt,idt,type) {
		var t=$(idt);
		if(!t) return;
		if(type=='i')//instead
			t.innerHTML = txt;
		else if(type=='p')//prepend
			t.innerHTML = txt+t.innerHTML;
		else if($type=='a')//append
			t.innerHTML += txt;
	}
};
_chj=Epesi.href;
Ajax.Responders.register({onCreate: function(x,y) { //hack
	if (typeof x.options.requestHeaders == 'undefined')
		x.options.requestHeaders = ['client_id', Epesi.client_id];
	else if (typeof x.options.requestHeaders.push == 'function')
		x.options.requestHeaders.push('client_id',Epesi.client_id);
	else
		x.options.requestHeaders = $H(x.options.requestHeaders).merge({client_id: Epesi.client_id});
}});
