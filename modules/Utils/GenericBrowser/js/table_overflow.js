var utils_genericbrowser__last_td = false;
var utils_genericbrowser__firefox_fix = false;

table_overflow_show = function (e_td, force, evt) {
	var e_tip = document.getElementById("table_overflow");
	if (!e_tip) return;
	// *** firefox fix ***
	if (utils_genericbrowser__firefox_fix == e_td) return; 
	utils_genericbrowser__firefox_fix = e_td;
	// *** firefox fix ***
	if (force || e_td.scrollHeight>e_td.clientHeight || e_td.scrollWidth>e_td.clientWidth) {
		if (utils_genericbrowser__last_td) table_overflow_hide();
		e_tip.style.minWidth = e_td.clientWidth+"px";
		e_tip.style.minHeight = e_td.clientHeight+"px";
        var maxWidth = 400
        if (e_td.clientWidth > maxWidth) {
            maxWidth = e_td.clientWidth;
        }
        e_tip.style.maxWidth = maxWidth + "px";
        // Clone e_td's children into the preview instead of moving them:
        // this is a read-only preview shown *alongside* the cell now that it
        // follows the cursor rather than sitting over the cell it came from
        // (see the cursor-relative positioning below) - moving the real
        // nodes out left the original cell empty until the mouse moved away
        // again, visibly "blanking" it since there was no longer an overlay
        // sitting on top of that same spot to hide the gap. Cloning also
        // drops the old height-preserving stub_element entirely - nothing
        // is ever removed from e_td now, so it never had a height to lose.
        var content = document.getElementById("table_overflow_content");
        content.innerHTML = '';
        for (var i = 0; i < e_td.childNodes.length; i++) {
            content.appendChild(e_td.childNodes[i].cloneNode(true));
        }
		utils_genericbrowser__last_td = e_td;

        // Show the clone's own overflow-expandable content in full inside
        // the preview, regardless of whatever collapsed/expanded state the
        // real row's own toggle (gb_expand/gb_collapse) is currently in -
        // this preview is a full-content view, not meant to inherit a
        // second, independent truncation on top of its own. Only touches
        // the disposable clone, never the original still sitting in e_td.
        jq(content).find('div.expandable').removeClass('collapsed').addClass('expanded');

		// show() must run before positioning, not after: positioning below
		// (both the cursor-relative branch and clonePosition()) reads e_tip's
		// *current* geometry via getBoundingClientRect()/offsetWidth - always
		// zero while display:none (same trap documented for
		// Utils_PopupCalendar's clampToViewport() in bug-patterns.md). For
		// clonePosition() specifically, reading zero for "where e_tip
		// currently is" doesn't just misplace it once - its jQuery offset()
		// setter computes the new inline top/left as a *delta* from that
		// (bogus, zero) current position, so it keeps adding e_td's real
		// offset on top of whatever top/left was already there from the
		// previous call instead of replacing it. One hover looks fine
		// (starting top/left is 0, so the first delta happens to land close
		// to correct); every hover after that compounds further off-screen,
		// reproducing exactly "works once, then every later hover just
		// blanks the cell" - confirmed live (position drifting from the
		// cell's real ~270px down to 9000+, then 34000+px, over three
		// successive hovers).
		jQuery(e_tip).show();

		if (evt) {
			// Per request: tooltip follows the cursor instead of sitting
			// over the cell (which put it directly under the pointer,
			// obscuring the very text being previewed). Anchored to the
			// right of the cursor by default; flipped to the left if that
			// would run past the right edge of the viewport.
			var cursorGap = 14;
			e_tip.style.top = (evt.pageY - 6) + "px";
			// Measure the tooltip's natural width with `left` still
			// unconstrained (wherever it was left at - "0px" at creation,
			// per Utils_GenericBrowser__overflow_div() - never far enough
			// right to matter) before deciding placement. A position:absolute
			// element with only `left` set (no `right`/`width`) uses a
			// shrink-to-fit width bounded by (containing block width -
			// left): measuring *after* setting the tentative far-right
			// "unflipped" left starves that calculation of room and
			// offsetWidth silently clamps to min-width instead of the
			// content's real width - confirmed live (offsetWidth read 115,
			// its min-width, mid-calculation; the tooltip's actual rendered
			// width once positioned sanely was 162) - which fed a wrong
			// number into the flip check below and defeated it.
			var tipWidth = e_tip.offsetWidth;
			var left = evt.pageX + cursorGap;
			if (left + tipWidth > window.innerWidth) {
				left = evt.pageX - cursorGap - tipWidth;
			}
			e_tip.style.left = left + "px";
		} else {
			// Fallback for any caller that doesn't pass the triggering
			// mouse event - positions over the cell like before.
			var is_chrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;
			var leftOffset = -2;
			if (is_chrome)
				leftOffset = -1;
			jQuery(e_tip).clonePosition(e_td,{setHeight: false, setWidth: false, offsetTop: -2, offsetLeft: leftOffset});
		}
		if (e_tip.clientWidth<=e_td.clientWidth && e_tip.clientHeight-3<=e_td.clientHeight) { // 3 pixels because Firefox is getting lost at what height should elements have
			table_overflow_hide(); // Work-around for firefox, because it cannot handle scrollWidth in <td>
		}
		// No auto-hide timer of any kind here - per explicit request, the
		// preview must stay open for exactly as long as the cursor is over
		// the cell, no grace period either way. The cell's own onmouseout
		// (GenericBrowser_0.php) calls table_overflow_hide() directly and
		// immediately, same as #table_overflow's own onmouseout below.
	}
};
table_overflow_hide = function () {
	// *** firefox fix ***
	// table_overflow_show()'s own "popup doesn't actually need to show"
	// branch (e_tip fits inside e_td after all) calls this function
	// directly too, so the guard has to be reset here rather than only at
	// the top of table_overflow_show() - every hide path funnels through
	// this one function.
	utils_genericbrowser__firefox_fix = false;
	// *** firefox fix ***
	var e_tip = document.getElementById("table_overflow");
	if (!e_tip) return;
	// Nothing to restore to the cell any more - table_overflow_show() clones
	// its content into the preview instead of moving it out, so hiding is
	// just clearing the clone and hiding the box.
	document.getElementById("table_overflow_content").innerHTML = '';
	utils_genericbrowser__last_td = false;
	jQuery(e_tip).hide();
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
	// Immediate hide, no grace period - matches the cell's own onmouseout
	// (GenericBrowser_0.php). No onmouseover handler needed any more either:
	// there's no pending timer left to cancel by hovering the preview itself.
	div.onmouseout = table_overflow_hide;
};

var gb_expandable = {};
var gb_expanded = {};

gb_show_hide_buttons = function (table_id) {
    if(document.getElementById("expand_all_button_"+table_id) == null) return;
    if (typeof gb_expandable[table_id] == "undefined" || Object.keys(gb_expandable[table_id]).length==0) {
        jQuery("#expand_all_button_"+table_id).hide();
        jQuery("#collapse_all_button_"+table_id).hide();
        return;
    }
    if (gb_expanded[table_id]>=Object.keys(gb_expandable[table_id]).length) {
        jQuery("#expand_all_button_"+table_id).hide();
        jQuery("#collapse_all_button_"+table_id).show();
    } else {
        jQuery("#expand_all_button_"+table_id).show();
        jQuery("#collapse_all_button_"+table_id).hide();
    }
}

gb_expand = function(table,id) {
    table_overflow_hide();
    var e = jq("#gb_row_"+table+'_'+id+' div.expandable');
    if(e.length>0) {
        e.height("auto").addClass("expanded").removeClass('collapsed');
        jQuery("#gb_more_"+table+'_'+id).hide();
        jQuery("#gb_less_"+table+'_'+id).show();
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
    table_overflow_hide();
    var e = jq("#gb_row_"+table+'_'+id+' div.expandable')
    if(e.length>0) {
        // gb_expand() sets an inline height:auto (e.height("auto")) so the
        // "expanded" state always shows full content regardless of any
        // per-column collapsed-height override (e.g. Utils_RecordBrowser__tallpreview's
        // 72px) - an inline style beats any external stylesheet rule
        // outright, no matter its specificity. Once a row had ever been
        // expanded, re-collapsing it left that inline style in place forever
        // (only the class was toggled back), permanently defeating whatever
        // height the .collapsed CSS was supposed to apply. Clear it back to
        // the stylesheet-driven height on every collapse.
        e.css('height', '');
        e.removeClass('expanded').addClass('collapsed');
        jQuery("#gb_more_"+table+'_'+id).show();
        jQuery("#gb_less_"+table+'_'+id).hide();
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
        jQuery("#gb_less_"+table+'_'+id).hide();
        jQuery("#gb_more_"+table+'_'+id).hide();
        delete gb_expandable[table][id];
        return;
    }
    el.each(function(index) {
        if (jq(this).outerHeight(true) > 18) {
            jq(this).addClass('exceedsHeight');
        }
    });
    gb_collapse(table,id);
    gb_expandable[table][id] = id;
    document.getElementById("gb_less_"+table+'_'+id).childNodes[0].src = gb_collapse_icon;
    document.getElementById("gb_more_"+table+'_'+id).childNodes[0].src = gb_expand_icon;
    // handlers to expand on click in the empty space of the cell - for a
    // 'tall_preview' column (e.g. Utils_Attachment's Note field - see
    // AttachmentInstall.php), click-to-toggle instead works from anywhere in
    // the cell (except real links/buttons), not just empty padding: it's the
    // only expand/collapse control on mobile, where there's no room for the
    // per-row action icons (they only surface inside the "more actions"
    // kebab there) or the Expand All/Collapse All buttons.
    el.unbind().each(function() {
        var tallPreview = jq(this).closest('.Utils_GenericBrowser__td').hasClass('Utils_RecordBrowser__tallpreview');
        jq(this).click(function (e) {
            if(getSelection().toString()) return;
            var onSelf = (e.target == this);
            var onTallPreviewContent = tallPreview && !jq(e.target).is('a,button,input,textarea,select') && !jq(e.target).closest('a,button').length;
            if (onSelf || onTallPreviewContent) {
                if (jq(this).hasClass("collapsed")) gb_expand(table, id);
                else if (jq(this).hasClass("expanded")) gb_collapse(table, id);
            }
        });
    });
    el.parent(".Utils_GenericBrowser__td").unbind().click(function (e) {
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
    var el = jq('#table_' + table + ' .Utils_GenericBrowser__th.Utils_GenericBrowser__actions:first');
    el.css('width', actions * 16 + 6);
};