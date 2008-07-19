dashboard_activate = function(default_dash) {
	if(!$('dashboard')) return;
	for(var id=0; id<3; id++)
		Sortable.create("dashboard_applets_"+id,{dropOnEmpty:true,tag:'div',containment:["dashboard_applets_0","dashboard_applets_1","dashboard_applets_2"],constraint:false, handle: 'handle',ghosting: true,onUpdate:function(c){
		        new Ajax.Request("modules/Base/Dashboard/update.php",{
					method: "post",
					parameters: { data: Sortable.serialize(c.id), default_dash: default_dash }
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
