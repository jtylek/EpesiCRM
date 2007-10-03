dashboard_activate = function() {
	if(!$('dashboard')) return;
	for(var id=0; id<3; id++)
		Sortable.create("dashboard_applets_"+id,{dropOnEmpty:true,tag:'div',containment:["dashboard_applets_0","dashboard_applets_1","dashboard_applets_2"],constraint:false, ghosting: true, handle: 'handle',onUpdate:function(c){
		        new Ajax.Request("modules/Base/Dashboard/update.php",{
					method: "post",
					parameters: { data: Sortable.serialize(c.id) }
			});
		}});

	var applets = document.getElementsByClassName('applet', 'dashboard');
	applets.each(function (appl) {
			var content = document.getElementsByClassName('content',appl)[0];
			var toggle = document.getElementsByClassName('toggle',appl)[0];
			if(toggle)
				Event.observe(toggle, 'click', function (e) { Effect.toggle(content, 'blind'); },false);
	});
}
