/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */

function focus_by_id(idd) {
	xx = document.getElementById(idd);
	if(xx) setTimeout('xx.focus();',200);
};

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
		new Ajax.Request('process.php', {
			method: 'post',
			parameters: {
				history: history_id,
				url: url
			},
			onComplete: function(t) {
				Epesi.procOn--;
				Epesi.updateIndicator();
				Epesi.append_js('Event.fire(document,\'e:load\');');
			},
			onException: function(t,e) {
				throw(e);
			},
			onFailure: function(t) {
				alert('Failure ('+t.status+'): '+t.responseText);
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
	},
	//js loader
	loaded_jss:new Array(),
	to_load_jss:new Array(),
	to_append_jss:new Array(),
	js_loader_running:false,
	load_js:function(file) {
		if (Epesi.loaded_jss.indexOf(file)!=-1) return;
		Epesi.to_load_jss[Epesi.to_load_jss.size()] = file;
		if(Epesi.js_loader_running==false) {
			Epesi.js_loader_running=true;
			Epesi.js_loader();
		}
	},
	append_js:function(texti) {
		if(Epesi.js_loader_running==false) {
			Epesi.append_js_script(texti);
		} else
			Epesi.to_append_jss[Epesi.to_append_jss.size()] = texti;
	},
	append_js_script:function(texti) {
		fileref=document.createElement("script");
		fileref.setAttribute("type", "text/javascript");
		fileref.text = texti;
		document.getElementsByTagName("head").item(0).appendChild(fileref);
	},
	js_loader:function() {
		file = Epesi.to_load_jss.first();
		if(typeof file != 'undefined') {
			fileref=document.createElement("script")
			fileref.setAttribute("type", "text/javascript");
			fileref.setAttribute("src", file);
			fileref.onload=fileref.onreadystatechange=function() {
				if (fileref.readyState && fileref.readyState != 'loaded' && fileref.readyState != 'complete')
					return;
				Epesi.loaded_jss[Epesi.loaded_jss.size()] = file;
				Epesi.js_loader();
			}
			document.getElementsByTagName("head").item(0).appendChild(fileref);
			Epesi.to_load_jss = Epesi.to_load_jss.without(file);
		} else {
			for(var i=0; i<Epesi.to_append_jss.size(); i++) {
				var texti = Epesi.to_append_jss[i];
				Epesi.append_js_script(texti);
			}
			Epesi.to_append_jss.clear();
			Epesi.js_loader_running = false;
		}
	},
	//csses
	loaded_csses:new Array(),
	load_css:function(file) {
		if (Epesi.loaded_csses.indexOf(file)!=-1) return false;
		fileref=document.createElement("link")
		fileref.setAttribute("rel", "stylesheet");
		fileref.setAttribute("type", "text/css");
		fileref.setAttribute("href", file);
		document.getElementsByTagName("head").item(0).appendChild(fileref);
		Epesi.loaded_csses[Epesi.loaded_csses.size()] = file;
		return true;
	}
};
_chj=Epesi.href;
Ajax.Responders.register({
onCreate: function(x,y) { //hack
	if (typeof x.options.requestHeaders == 'undefined')
		x.options.requestHeaders = ['X-Client-ID', Epesi.client_id];
	else if (typeof x.options.requestHeaders.push == 'function')
		x.options.requestHeaders.push('X-Client-ID',Epesi.client_id);
	else
		x.options.requestHeaders = $H(x.options.requestHeaders).merge({'X-Client-ID': Epesi.client_id});
},
onException: function(req, e){
	alert(e);
}});
