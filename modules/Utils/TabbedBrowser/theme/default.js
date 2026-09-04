// Sizes a group toggle to match its popup's natural width (which itself
// shrinks/grows to its widest sub-tab) so the button isn't narrower than the
// flyout it opens - and, called eagerly from TabbedBrowser_0.php::body() via
// eval_js() right after render, so the toggle is already full width on first
// paint instead of visibly growing the first time a user hovers/focuses it.
// Guarded by data-tb-width-synced so the later call from
// tabbedbrowser_show_submenu() (kept as a fallback, e.g. for markup not
// rendered through body()'s eval_js loop) is a no-op once this has run.
tabbedbrowser_sync_width = function(id) {
	var el = document.getElementById('tabbedbrowser_'+id+'_popup');
	var toggle = document.getElementById('tabbed_browser_submenu_'+id);
	if (!el || !toggle || toggle.getAttribute('data-tb-width-synced')) return;
	toggle.setAttribute('data-tb-width-synced', '1');
	// el is normally display:none - make it measurable without flashing it
	// visible (visibility:hidden keeps it out of the paint, still in layout).
	var prevDisplay = el.style.display, prevVisibility = el.style.visibility;
	el.style.visibility = 'hidden';
	el.style.display = 'block';
	var popupWidth = el.offsetWidth;
	el.style.display = prevDisplay;
	el.style.visibility = prevVisibility;
	if (popupWidth > toggle.offsetWidth) {
		// box-sizing set explicitly so the min-width below (an offsetWidth,
		// i.e. border-box measurement) lands as the toggle's actual
		// border-box width regardless of any page-wide box-sizing default.
		toggle.style.boxSizing = 'border-box';
		toggle.style.minWidth = popupWidth + 'px';
		toggle.style.justifyContent = 'center';
		toggle.style.textAlign = 'center';
	}
}

tabbedbrowser_show_submenu = function(id) {
	tabbedbrowser_sync_width(id);
	var el = document.getElementById('tabbedbrowser_'+id+'_popup');
	var toggle = document.getElementById("tabbed_browser_submenu_"+id);
	el.style.display="";
	jQuery(el).clonePosition(toggle,{setWidth:false,setHeight:false,offsetTop:toggle.offsetHeight-1});
}

tabbedbrowser_hide_submenu = function(id) {
	var el = document.getElementById('tabbedbrowser_'+id+'_popup');
	el.style.display="none";
}
