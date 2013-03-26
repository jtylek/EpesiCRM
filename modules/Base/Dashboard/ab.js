sort_applet_sort_fn = function(a,b) {
    return jq(a).attr('searchkey')>jq(b).attr('searchkey');
}

sort_applet_selection_panel = function () {
    var a = jq('#dashboard_applets_new');
    a.children().sort(sort_applet_sort_fn).appendTo(a);
}

dashboard_activate = function(tabs, default_dash) {
  if(!jq('#dashboard').length) return;
  sort_applet_selection_panel();
  var new_applets = jq('#dashboard_applets_new');

  jq(tabs).each(function(i,tab) {
    var cols = jq("#dashboard_applets_"+tab+"_0, #dashboard_applets_"+tab+"_1, #dashboard_applets_"+tab+"_2, #dashboard_applets_new");
    for(var id=0; id<3; id++) {
        jq("#dashboard_applets_"+tab+"_"+id).attr('tab',tab).attr('col_id',id).sortable({handle:'.handle',connectWith:cols,update:function(ev,ui){
            var t = jq(this);

            t.children().each(function(ii,ee) {
               ee = jq(ee);
               if(ee.attr('id').indexOf('ab_item_new_')!=-1) {
                   var appletCopy = ee.get(0).cloneNode(true);
                   new_applets.append(appletCopy);
                   appletCopy.style.opacity = 1;
                   appletCopy.style.top = 0;
                   appletCopy.style.left = 0;
                   sort_applet_selection_panel();

                   ee.attr('id','copy_'+ee.attr('id'));
                   ee.find('a').each(function(jj,link){
                      if(link.id.substr(0,24)=="dashboard_remove_applet_")
                        link.setAttribute('id','copy_'+link.id);
                   });
                   ee.find('div').each(function(jj,div){
                      if(div.id.substr(0,25)=="dashboard_applet_content_")
                        div.setAttribute('id','copy_'+div.id);
                   });
               }
            });

            jq.post("modules/Base/Dashboard/update.php",{
                    data:t.sortable('serialize',{expression:'(ab_item)_(.+)'}),
                    default_dash: default_dash,
                    tab: tab,
                    col:t.attr('col_id')
                },function(t) {
                    eval(t);
                }
            );
        }});
    }
  });
  if (new_applets.length>0)
        new_applets.sortable({handle:'.handle',connectWith:jq('#dashboard > .ui-sortable'),update:function(ev,ui){
            var t = jq(this);
            // remove instanced applets from the list
            t.children().each(function(ii,ee) {
                ee = jq(ee);
                if(ee.attr('id').indexOf('ab_item_new_')==-1) {
                    ee.hide();
                }
            });
            jq.post("modules/Base/Dashboard/update.php",{
                    data: t.sortable('serialize',{expression:'(ab_item)_(.+)'}),
                    default_dash: default_dash,
                    tab: '',
                    col: 'new'
                }, function(t) {
                    eval(t);
                }
            );
        }});

  //applet toggle buttons
  jq('#dashboard .applet').each(function(i,a) {
        var aa = jq(a);
        aa.find('a.toggle').click(function(b) {
            aa.find('.content').toggle('blind');
        });
  });
}

remove_applet = function(id, default_dash) {
    var elem = jq("#ab_item_"+id);
	if (elem.length)
		elem.hide('fade');
    jq.post("modules/Base/Dashboard/remove_applet.php",{
        id: id,
        default_dash: default_dash
    },function(t) {
        eval(t);
    });
}

get_new_dashboard_tab_name = function(query, error, id) {
	var name = prompt(query, '');
	if (name == null) return false;
	if (name == '') {
		alert(error);
		return false;
	}
	jq('#dashboard_tab_name').val(name);
	jq('#dashboard_tab_id').val(id);
	return true;
}

dashboard_prepare_filter_box = function(focus, message) {
	if (focus && jq("#dashboard_applets_filter").val() == message) {
		jq("#dashboard_applets_filter").css('color',"");
		jq("#dashboard_applets_filter").val("");
	} 
	if (!focus && jq("#dashboard_applets_filter").val() == "") {
		jq("#dashboard_applets_filter").css('color',"#555");
		jq("#dashboard_applets_filter").val(message);
	}
}

dashboard_filter_applets = function() {
	var str = jq("#dashboard_applets_filter").val().toLowerCase();
	var nodes = jq("#dashboard_applets_new").children();
    nodes.each(function(i,nodeObj) {
        var node = jq(nodeObj);
        if(node.attr('searchkey').indexOf(str)!=-1) node.show(); else node.hide();
    });
}
