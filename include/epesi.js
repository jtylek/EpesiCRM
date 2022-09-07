/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence MIT
 */
jQuery.noConflict();

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
	default_indicator:'loading...',
	procOn:0,
	client_id:0,
	process_file:'process.php',
	indicator:'epesiStatus',
	indicator_text:'epesiStatusText',
	confirmLeave: {
		// object of form ids which require confirmation for leaving the page
        // store changed fields to pass through submit state
		forms:{},
        // store currently submitted form. If it'll fail to validate then we
        // have list of changed fields
		forms_freezed:{},
		message:'Leave page?',
		//checks if leaving the page is approved
		check: function() {
			//remove non-existent forms from the array
			jQuery.each(this.forms, function(f) {
				if (!jQuery('#'+f).length) Epesi.confirmLeave.deactivate(f);
			});
			jQuery.each(this.forms_freezed, function(f) {
				if (!jQuery('#'+f).length) Epesi.confirmLeave.deactivate(f);
			});
			//check if there is any not freezed form with changed values to confirm leave
            var requires_confirmation = false;
            if (Object.keys(this.forms).length) {
                for (var form in this.forms) {
                    if (jQuery('#' + form + ' .changed-input').length) {
                        requires_confirmation = true;
                        break;
                    }
                }
            }
			if (requires_confirmation) {
				//take care if user disabled alert messages
				var openTime = new Date();
				try {
					var confirmed = confirm(this.message);
				} catch(e) {
					var confirmed = true;
				}
				var closeTime = new Date();
				if ((closeTime - openTime) > 350 && !confirmed) return false;
				this.deactivate();
			}
			return true;
		},
		activate: function(f, m) {
            this.message = m;
            // add form or restore from freezed state - form is freezed for submit
            if (!(f in this.forms)) {
                if (f in this.forms_freezed) {
                    this.forms[f] = this.forms_freezed[f];
                    delete this.forms_freezed[f];
                } else {
                    this.forms[f] = {};
                }
            }
            // apply class to all changed inputs - required for validation failure
            for (var key in this.forms[f]) {
                jQuery('#' + f + ' [name="' + key + '"]').addClass('changed-input');
            }
            // on change add changed-input class
            jQuery('#' + f).on('change', 'input, textarea, select', function (e) {
				if (e.originalEvent === undefined) return;
                var el = jQuery(this);
                el.addClass('changed-input');
                var form = f in Epesi.confirmLeave.forms ? Epesi.confirmLeave.forms[f] : Epesi.confirmLeave.forms_freezed[f];
                form[el.attr('name')] = true;
            });
			//take care if user refreshing or going to another page
			jQuery(window).unbind('beforeunload').on('beforeunload', function() {
                if (jQuery('.changed-input').length) {
                    return Epesi.confirmLeave.message;
                }
			});
		},
		deactivate: function(f) {
			if (arguments.length) {
                delete this.forms[f];
                delete this.forms_freezed[f];
			} else {
                this.forms = {};
                this.forms_freezed = {};
            }
				
			if (!Object.keys(this.forms).length) jQuery(window).unbind('beforeunload');
		},
        freeze: function(f) {
            if (f in this.forms) {
                this.forms_freezed[f] = this.forms[f];
                delete this.forms[f];
            }
        }
	},
	updateIndicator: function() {
		var s = $(Epesi.indicator);
		if(s) s.style.visibility = Epesi.procOn ? 'visible' : 'hidden';
		if (!Epesi.procOn) $('main_content').style.display = '';
	},
	updateIndicatorText: function(text) {
		$(Epesi.indicator_text).innerHTML = text;
	},
	history_on:1,
	history_add:function(id){
		Epesi.history_on=-1;
		unFocus.History.addHistory(id);
	},
	get_ie_version:function() {
		var rv = -1; // Return value assumes failure.
	        if (navigator.appName == 'Microsoft Internet Explorer') {
		        var ua = navigator.userAgent;
			var re = new RegExp("MSIE ([0-9]{1,}[\.0-9]{0,})");
			if (re.exec(ua) != null)
			        rv = parseFloat(RegExp.$1);
		}
		return rv;
	},
	ie:false,
	init:function(cl_id,path,params) {
		var ie_ver = Epesi.get_ie_version();
		if (ie_ver!=-1) {
			if(ie_ver<8.0) {
				alert("Sorry but your version of Internet Explorer browser is not supported.\nYou should upgrade it or install Mozilla Firefox.");
				window.location = "http://www.mozilla.com/firefox/";
			} else {
				Epesi.ie = true;
			}
		}

		Epesi.client_id=cl_id;
		Epesi.process_file=path;

		Epesi.history_add(0);
		if(typeof params == 'undefined')
			params = '';
		Epesi.request(params,0);
		unFocus.History.addEventListener('historyChange',function(history_id){
       		switch(Epesi.history_on){
			    case -1: Epesi.history_on=1;
		    		return;
				case 1: Epesi.request('',history_id);
			}
		});
	},
	request: function(url,history_id) {
		Epesi.procOn++;
		Epesi.updateIndicator();
		var keep_focus_field = null;
		new Ajax.Request(Epesi.process_file, {
			method: 'post',
			parameters: {
				history: history_id,
				url: url
			},
			onComplete: function(t) {
				Epesi.procOn--;
				Epesi.append_js('Event.fire(document,\'e:load\');Epesi.updateIndicator();');
				if(keep_focus_field!=null) {
                    Epesi.append_js('jQuery("#'+keep_focus_field+':visible").focus();');
                }
			},
			onSuccess: function(t) {
				if(typeof document.activeElement != "undefined") keep_focus_field = document.activeElement.getAttribute("id");
				Event.fire(document,'e:loading');
			},
			onException: function(t,e) {
				throw(e);
			},
			onFailure: function(t) {
				alert('Failure ('+t.status+')');
				Epesi.text(t.responseText,'error_box','p');
			}
		});
	},
	href: function(url,indicator,mode,disableConfirmLeave) {
		if (typeof disableConfirmLeave == 'undefined' && !Epesi.confirmLeave.check()) return;
		if(Epesi.procOn==0 || mode=='allow'){
			if(indicator=='') indicator=Epesi.default_indicator;
			Epesi.updateIndicatorText(indicator);
			Epesi.request(url);
		} else if(mode=='queue')
			setTimeout('Epesi.href("'+url+'", "'+indicator+'", "'+mode+'")',500);
	},
	submit_form: function(formName, modulePath, indicator) {
		action = jQuery.param({'__action_module__': encodeURIComponent(modulePath)});
		Epesi.confirmLeave.freeze(formName);
		jQuery('form[name="' + formName + '"] input[name="submited"]').val(1);
		_chj(jQuery('form[name="'+formName+'"]').serialize() +'&' + action, indicator, '');
		jQuery('form[name="' + formName + '"] input[name="submited"]').val(0);
	},
	text: function(txt,idt,type) {
		var t=$(idt);
		if(!t) return;
		if(type=='i')//instead
			t.innerHTML = txt;
		else if(type=='p')//prepend
			t.innerHTML = txt+t.innerHTML;
		else if(type=='a')//append
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
function getTotalTopOffet(e) {
	var ret=0;
	while (e!=null) {
		ret += e.offsetTop;
		e = e.offsetParent;
	}
	return ret;
};
is_visible = function(element) {
	if (!element) return false;
	var display = jQuery(element).css('display');
	if (display == "none") return false;
	if (element.parentNode && element.parentNode.style) {
		xxx = element.parentNode;
		return is_visible(element.parentNode);
	}
	return true;
};
jq=jQuery;