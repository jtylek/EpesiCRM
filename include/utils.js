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

function collect(a,f) {
	var n=[];
	for(var i=0;i<a.length;i++){
		if(a[i].disabled) continue;
		var v=f(a[i]);
		if(v!=null) n.push(v)
	}
	return n
}

function serialize_form(formName){
	f = document.getElementById(formName);
	var g=function(n) {
		return f.getElementsByTagName(n)
	};
	var nv=function(e){
		if(e.name)
			return encodeURIComponent(e.name)+'='+encodeURIComponent(e.value);
		else 
			return ''
	};
	var i=collect(g('input'),function(i) {
		if((i.type!='radio'&&i.type!='checkbox')||i.checked)
			return nv(i)
	});
	var s=collect(g('select'),function(sss) {
		ret = [];
          for(var i=0;i<sss.options.length;i++){
			if(sss.options[i].selected)
				ret.push(encodeURIComponent(sss.name)+'='+encodeURIComponent(sss.options[i].value));
		}
		return ret.join('&')
	});
	var t=collect(g('textarea'),nv);
	return i.concat(s).concat(t).join('&');
};

function addslashes(x){return x.replace(/('|"|\\)/g,"\\$1")}

function wait_while_null(id,action) {
	if(eval('typeof('+id+')') != 'undefined')
		eval(action);
	else
		setTimeout('wait_while_null(\''+addslashes(id)+'\', \''+addslashes(action)+'\')',200);
};
