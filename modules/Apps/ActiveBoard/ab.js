activeboard_activate = function() {
	for(var id=0; id<3; id++)
		Sortable.create("activeboard_applets_"+id,{dropOnEmpty:true,tag:'div',containment:["activeboard_applets_0","activeboard_applets_1","activeboard_applets_2"],constraint:false, ghosting: true, handle: 'handle',onUpdate:function(c){
		        new Ajax.Request("modules/Apps/ActiveBoard/update.php",{
					method: "post",
					parameters: { data: Sortable.serialize(c.id) }
			});
		}});

	var applets = document.getElementsByClassName('applet', 'activeboard');
	applets.each(function (appl) {
			var content = document.getElementsByClassName('content',appl)[0];
			var toggle = document.getElementsByClassName('toggle',appl)[0];
			Event.observe(toggle, 'click', function (e) { Effect.toggle(content, 'blind'); },false);
	});
}