var utils_genericbrowser__hidetip = false;
var utils_genericbrowser__last_td = false;
var utils_genericbrowser__hide_current = 0;
var utils_genericbrowser__firefox_fix = false;

table_overflow_show = function (e_td, force) {
	var e_tip = jq("#table_overflow");
	if (!e_tip.length) return;
	e_td = jq(e_td)[0];
	// *** firefox fix ***
	if (utils_genericbrowser__firefox_fix == e_td) return; 
	utils_genericbrowser__firefox_fix = e_td;
	// *** firefox fix ***
	if (force || e_td.scrollHeight>e_td.clientHeight || e_td.scrollWidth>e_td.clientWidth) {
		if (utils_genericbrowser__last_td) table_overflow_hide(utils_genericbrowser__hide_current);
		e_tip.css({minWidth: e_td.clientWidth+"px", minHeight: e_td.clientHeight+"px"});
        var maxWidth = 400
        if (e_td.clientWidth > maxWidth) {
            maxWidth = e_td.clientWidth;
        }
        e_tip.css({maxWidth: maxWidth + "px"});
        var keep_height = jq(e_td).height();
        while (e_td.childNodes.length>0) {
			jq("#table_overflow_content").append(e_td.firstChild);
		}
		utils_genericbrowser__last_td = e_td;

        // put stub element to keep height of the cell
        var stub_element = document.createElement('div');
        stub_element.style.height = keep_height+"px";
        stub_element.style.margin = stub_element.style.padding = 0;
        e_td.appendChild(stub_element);
        // replace collapsed class name to not expand while overflow is shown
        var exp_el = jq('#table_overflow_content div.expandable');
        if (exp_el.hasClass('collapsed')) exp_el.addClass('collapsed_hold').removeClass('collapsed');
        if (exp_el.hasClass('expanded')) exp_el.addClass('expanded_hold').removeClass('expanded');

		var is_chrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;
		var leftOffset = -2;
		if (is_chrome)
			leftOffset = -1;

		e_tip.clonePosition(e_td,{cloneHeight: false, cloneWidth: false, offsetTop: -2, offsetLeft: leftOffset});
		e_tip.show();
		if (e_tip.width()<=jq(e_td).width() && e_tip.height()-3<=jq(e_td).height()) { // 3 pixels because Firefox is getting lost at what height should elements have
			utils_genericbrowser__hidetip = true;
			table_overflow_hide(utils_genericbrowser__hide_current); // Work-around for firefox, because it cannot handle scrollWidth in <td>
		} else {
			if(navigator.userAgent.match(/(iPad|iPod|iPhone)/i) == null)
				table_overflow_hide_delayed();
		}
	}
};
table_overflow_stop_hide = function() {
	utils_genericbrowser__hidetip = false;
}
table_overflow_hide_delayed = function () {
	// *** firefox fix ***
	utils_genericbrowser__firefox_fix = false;
	// *** firefox fix ***
	utils_genericbrowser__hide_current++;
	utils_genericbrowser__hidetip = true;
	setTimeout("table_overflow_hide("+utils_genericbrowser__hide_current+");", 800);
};
table_overflow_hide = function (current) {
	if (!utils_genericbrowser__hidetip || utils_genericbrowser__hide_current!=current) return;
	var e_tip = jq("#table_overflow");
	if (!e_tip.length) return;
	if (utils_genericbrowser__last_td) {
		jq(utils_genericbrowser__last_td).html('');
        jq('#table_overflow_content div.expandable.collapsed_hold').removeClass('collapsed_hold').addClass('collapsed');
        jq('#table_overflow_content div.expandable.expanded_hold').removeClass('expanded_hold').addClass('expanded');
		while (jq("#table_overflow_content").children().length>0) {
			jq(utils_genericbrowser__last_td).append(jq("#table_overflow_content").children().first());
		}
		utils_genericbrowser__last_td = false;
	}
	e_tip.hide();
};
Utils_GenericBrowser__overflow_div = function() {
	div = document.createElement("div");
	div.id = "table_overflow";
	div.style.position = "absolute";
	div.style.display = "none";
	div.style.zIndex = 9;
	div.style.left = 0;
	div.style.top = 0;
	div.innerHTML = "<div id=\'table_overflow_content\' class=\'Utils_GenericBrowser__overflow_div_content\'></div>";
	div.className = "Utils_GenericBrowser__overflow_div";
	body = document.getElementsByTagName("body");
	body = body[0];
	document.body.appendChild(div);
	div.onmouseout = table_overflow_hide_delayed;
	if(navigator.userAgent.match(/(iPad|iPod|iPhone)/i) == null)
		div.onmouseover = table_overflow_stop_hide;
	else
		div.onmouseover = table_overflow_hide_delayed;
};

var gb_expandable = {};
var gb_expanded = {};

gb_show_hide_buttons = function (table_id) {
    if(!jq("#expand_all_button_"+table_id).length) return;
    if (typeof gb_expandable[table_id] == "undefined" || Object.keys(gb_expandable[table_id]).length==0) {
        jq("#expand_all_button_"+table_id).hide();
        jq("#collapse_all_button_"+table_id).hide();
        return;
    }
    if (gb_expanded[table_id]>=Object.keys(gb_expandable[table_id]).length) {
        jq("#expand_all_button_"+table_id).hide();
        jq("#collapse_all_button_"+table_id).show();
    } else {
        jq("#expand_all_button_"+table_id).show();
        jq("#collapse_all_button_"+table_id).hide();
    }
}

gb_expand = function(table,id) {
    table_overflow_hide(utils_genericbrowser__hide_current);
    var e = jq("#gb_row_"+table+'_'+id+' div.expandable');
    if(e.length>0) {
        e.height("auto").addClass("expanded").removeClass('collapsed');
        jq("#gb_more_"+table+'_'+id).hide();
        jq("#gb_less_"+table+'_'+id).show();
        if (gb_expandable[table][id])
            gb_expanded[table]++;
    }
    gb_show_hide_buttons(table);
};

gb_expand_all = function(table) {
    for(var n in gb_expandable[table]) gb_expand(table,n);
    gb_expanded[table] = Object.keys(gb_expandable[table]).length;
    gb_show_hide_buttons(table);
};

gb_collapse = function(table,id) {
    table_overflow_hide(utils_genericbrowser__hide_current);
    var e = jq("#gb_row_"+table+'_'+id+' div.expandable')
    if(e.length>0) {
        e.removeClass('expanded').addClass('collapsed');
        jq("#gb_more_"+table+'_'+id).show();
        jq("#gb_less_"+table+'_'+id).hide();
        if (gb_expandable[table][id])
            gb_expanded[table]--;
    }
    gb_show_hide_buttons(table);
};

gb_collapse_all = function(table) {
    for(var n in gb_expandable[table]) gb_collapse(table,n);
    gb_expanded[table] = 0;
    gb_show_hide_buttons(table);
};

gb_expandable_init = function(table,id) {
    var el = jq("#gb_row_"+table+'_'+id+' div.expandable');
    el.removeClass('collapsed'); // expand to calculate height properly
    var heights = el.map(function() {return jq(this).outerHeight(true);});
    if(Math.max.apply(null,heights)<=18) {
        jq("#gb_less_"+table+'_'+id).hide();
        jq("#gb_more_"+table+'_'+id).hide();
        delete gb_expandable[table][id];
        return;
    }
    el.each(function(index) {
        if (jq(this).outerHeight(true) > 18) {
            jq(this).addClass('exceedsHeight');
        }
    });
    gb_expand(table,id);
    gb_expandable[table][id] = id;
    jq("#gb_less_"+table+'_'+id).children('i').replaceWith(gb_collapse_icon);
    jq("#gb_more_"+table+'_'+id).children('i').replaceWith(gb_expand_icon);
    // handlers to expand on click in the empty space of the cell
    el.off('click').click(function (e) {
        if(!getSelection().toString()){
            if (e.target == this) {
                if (jq(this).hasClass("collapsed")) gb_expand(table, id);
                else if (jq(this).hasClass("expanded")) gb_collapse(table, id);
            }
        }
    });
    el.parent("td").off('click').click(function (e) {
        if (e.target == this) {
            jq(this).children("div").click();
        }
    });
};
gb_expandable_hide_actions = function(table) {
    if(Object.keys(gb_expandable[table]).length > 0)return;
    jq('#table_'+table+' .Utils_GenericBrowser__actions').hide();
}
gb_expandable_adjust_action_column = function (table, actions) {
    if (Object.keys(gb_expandable[table]).length > 0) {
        actions = actions + 1;
    }
    var el = jq('#table_' + table + ' th.Utils_GenericBrowser__actions:first');
    el.css('width', ''+(actions * 1.28571429*1.34)+'em');
};