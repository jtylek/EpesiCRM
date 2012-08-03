sort_applet_selection_panel = function () {
	var qty = $("dashboard_applets_new").childNodes.length;
	var last = null;
	for (i=0; i<qty; i++) {
		if (!$("dashboard_applets_new").childNodes[i].getAttribute("order")) continue;
		var last_node = $("dashboard_applets_new").childNodes[last];
		var cur_node = $("dashboard_applets_new").childNodes[i];
		if (last) {
			if (parseInt(last_node.getAttribute("order")) > parseInt(cur_node.getAttribute("order"))) {
				for (j=0; j<i; j++) {
					var candicate_node = $("dashboard_applets_new").childNodes[j];
					if (parseInt(candicate_node.getAttribute("order")) > parseInt(cur_node.getAttribute("order"))) {
						$("dashboard_applets_new").insertBefore(cur_node, candicate_node);
						break;
					}
				}
			}
		}
		last = i;
	}
}

dashboard_activate = function(tab, default_dash) {
	if(!$('dashboard')) return;
	for(var id=0; id<3; id++)
		Sortable.create("dashboard_applets_"+tab+"_"+id,{dropOnEmpty:true,tag:'div',containment:["dashboard_applets_new","dashboard_applets_"+tab+"_0","dashboard_applets_"+tab+"_1","dashboard_applets_"+tab+"_2"],constraint:false, handle: 'handle',ghosting: false,onUpdate:function(c){
			// find the recently added applet
			for (w in c.childNodes)
				if (c.childNodes[w] && c.childNodes[w].id && c.childNodes[w].id.indexOf("ab_item_new_")!=-1) {
					var appletCopy = c.childNodes[w].cloneNode(true);
					$("dashboard_applets_new").appendChild(appletCopy);
					c.childNodes[w].id = "copy_"+c.childNodes[w].id;
					var links = c.childNodes[w].getElementsByTagName('A');
					for (i=0;i<links.length;i++) if (links[i].id.substr(0,24)=="dashboard_remove_applet_") {
						links[i].setAttribute("id","copy_"+links[i].id);
					}
					var divs = c.childNodes[w].getElementsByTagName('DIV');
					for (i=0;i<divs.length;i++) if (divs[i].id.substr(0,25)=="dashboard_applet_content_") {
						divs[i].setAttribute("id","copy_"+divs[i].id);
					}
					appletCopy.style.opacity = 1;
					appletCopy.style.top = 0;
					appletCopy.style.left = 0;
					sort_applet_selection_panel();
				}
			new Ajax.Request("modules/Base/Dashboard/update.php",{
				method: "post",
				parameters: { 
					data: Sortable.serialize(c.id), 
					default_dash: default_dash,
					tab: tab,
					call: 'base'
				},
				onSuccess:function(t) {
					eval(t.responseText);
				}
			});
		}});

	if ($("dashboard_applets_new"))
		Sortable.create("dashboard_applets_new",{dropOnEmpty:true,tag:'div',containment:["dashboard_applets_new","dashboard_applets_"+tab+"_0","dashboard_applets_"+tab+"_1","dashboard_applets_"+tab+"_2"],constraint:false, handle: 'handle',ghosting: false, onUpdate:function(c){
			// remove instanced applets from the list
			for (w in c.childNodes)
				if (c.childNodes[w] && c.childNodes[w].id && c.childNodes[w].id.indexOf("ab_item_new_")==-1)
					c.childNodes[w].style.display="none";
			sort_applet_selection_panel();
			new Ajax.Request("modules/Base/Dashboard/update.php",{
				method: "post",
				parameters: { 
					data: Sortable.serialize(c.id), 
					default_dash: default_dash,
					tab: tab,
					call: 'new'
				},
				onSuccess:function(t) {
					eval(t.responseText);
				}
			});
		}});

	var applets = $A(document.getElementsByClassName('applet', 'dashboard'));
	applets.each(function (appl) {
			var content = appl.getElementsByClassName('content')[0]
			var toggle = appl.getElementsByClassName('toggle')[0];
			if(toggle)
				Event.observe(toggle, 'click', function (e) { Effect.toggle(content, 'slide', {duration:0.3}); },false);
	});
}

remove_applet = function(id, default_dash) {
	if ($("ab_item_"+id))
		Effect.Fade("ab_item_"+id);
	new Ajax.Request("modules/Base/Dashboard/remove_applet.php",{
		method: "post",
		parameters: { 
			id: id, 
			default_dash: default_dash
		},
		onSuccess:function(t) {
			eval(t.responseText);
		}
	});
}

get_new_dashboard_tab_name = function(query, error, id) {
	var name = prompt(query, '');
	if (name == null) return false;
	if (name == '') {
		alert(error);
		return false;
	}
	$('dashboard_tab_name').value = name;
	$('dashboard_tab_id').value = id;
	return true;
}

dashboard_prepare_filter_box = function(focus, message) {
	if (focus && $("dashboard_applets_filter").value == message) {
		$("dashboard_applets_filter").style.color = "";
		$("dashboard_applets_filter").value = "";
	} 
	if (!focus && $("dashboard_applets_filter").value == "") {
		$("dashboard_applets_filter").style.color = "#555";
		$("dashboard_applets_filter").value = message;
	}
}

dashboard_filter_applets = function() {
	var str = $("dashboard_applets_filter").value.toLowerCase();
	var nodes = $("dashboard_applets_new").childNodes;
	var qty = nodes.length;
	for (i=0; i<qty; i++) {
		if (nodes[i] && nodes[i].id && nodes[i].id.indexOf("ab_item_new_")!=-1) {
			var searchkey = nodes[i].getAttribute('searchkey');
			if (searchkey.toLowerCase().indexOf(str)!=-1) nodes[i].style.display="";
			else nodes[i].style.display="none";
		}
	}
}
