{if !$logged}

<div id="Base_Box__login">
	<div class="status">{$status}</div>
	<div class="entry">{$login}</div>
</div>

{else}

{php}
	eval_js_once('document.body.id=null'); //pointer-events:none;

	// --epesi-header-height (padding-top on .app-main, min-height on the
	// sidebar brand box) is a static guess. The navbar can grow taller than it
	// whenever the still-default-themed search/filter/help/login widgets it
	// hosts wrap onto another row (see the .navbar-nav comment in this theme's
	// default.css) - without this, .app-main's fixed offset falls short and
	// the (now taller) fixed navbar overlaps the page's own content. Likewise
	// --epesi-actionbar-height (the ActionBar is now docked fixed under the
	// navbar, always visible - see the .app-content-header comment) varies
	// with however many actions the current screen registers. Both are kept
	// in sync continuously, not just once at load, since either element's
	// content can change size after an AJAX navigation without the shell
	// itself (this template) re-rendering.
	//
	// Set on document.documentElement (:root), not .epesi-adminlte - Leightbox
	// popups (modules/Libs/Leightbox/leightbox.js) append themselves directly
	// to <body>, as siblings of .epesi-adminlte rather than descendants, so a
	// var written only on that wrapper would never reach them by inheritance.
	// :root sees every element in the document, .epesi-adminlte's descendants
	// included, so declaring the variables there (see this theme's default.css)
	// instead costs nothing for the shell itself.
	eval_js_once(
		"(function(){".
			"var wrap=document.documentElement;".
			"function watch(el,prop){".
				"if(!el)return;".
				"var sync=function(){wrap.style.setProperty(prop,el.offsetHeight+'px');};".
				"sync();".
				"if(window.ResizeObserver)new ResizeObserver(sync).observe(el);".
				"else window.addEventListener('resize',sync);".
			"}".
			"watch(document.getElementById('top_bar'),'--epesi-header-height');".
			"watch(document.getElementById('ActionBar'),'--epesi-actionbar-height');".
		"})();"
	);

	// Below lg, the sidebar is an off-canvas overlay (see default.css) that
	// stays open (body.sidebar-open) across a menu-driven navigation until
	// the user explicitly closes it - since navigating IS what they were
	// trying to do, closing it automatically here saves that extra tap.
	// Delegated on #MenuBar (Base_Menu::build_menu_html()'s own wrapper, not
	// re-rendered by ordinary AJAX navigation) rather than bound per-link, so
	// this doesn't need to be re-attached after anything. Excludes
	// a.menu-parent (data-bs-toggle="collapse" submenu headers - clicking one
	// only expands/collapses a submenu, no navigation happens, so the sidebar
	// shouldn't close).
	eval_js_once(
		"(function(){".
			"var bar=document.getElementById('MenuBar');".
			"if(!bar)return;".
			"bar.addEventListener('click',function(e){".
				"var a=e.target.closest('a.nav-link:not(.menu-parent)');".
				"if(!a)return;".
				"if(window.innerWidth<992)document.body.classList.remove('sidebar-open');".
			"});".
		"})();"
	);

	// Moves the Logout link (.logout_css3_box - Login_0.php now hands both
	// themes plain data, not pre-built HTML, but its own default.tpl still
	// assembles logged_as+logout into ONE combined string before Base_Box
	// ever sees it as {$login} - that's a container-system boundary, not
	// something Login_0.php's own markup controls) from the navbar's
	// {$login} slot into the sidebar footer, per request. A DOM relocation
	// since Base_Box can't address the logout half separately through the
	// container system - moving the actual node post-render is the only way
	// to send just it somewhere else. Runs once - the
	// navbar/sidebar footer are shell chrome, not re-rendered by ordinary
	// AJAX navigation (only #main_content swaps), so the moved node stays
	// moved. See default.css for this element's now-generic (not #top_bar-
	// scoped) styling, restyled for its new spot.
	eval_js_once(
		"(function(){".
			"var logout=document.querySelector('.logout_css3_box');".
			"var footer=document.querySelector('.sidebar-footer');".
			"if(logout&&footer)footer.insertBefore(logout,footer.firstChild);".
		"})();"
	);

	// Hides the navbar+ActionBar on scroll below lg, reclaiming vertical
	// space on a phone/small-tablet screen, per request - reappearing only
	// once scrolled back to the very top (not on scroll-up generally, which
	// is the more common pattern for this kind of UI, but not what was
	// asked for here). body.epesi-topbar-hidden (default.css) transforms
	// both bars off-screen; .app-content-header's own transform distance
	// reads --epesi-header-height/--epesi-actionbar-height (the same two
	// variables the ResizeObserver above keeps in sync) rather than a fixed
	// guess, so it stays exactly off-screen regardless of how tall either
	// bar actually renders. window.innerWidth is re-checked on every
	// scroll event (not just once at load) so this still behaves correctly
	// if the viewport crosses the breakpoint later (e.g. a device rotation
	// - see the portrait-lock overlay above, which already forces phones
	// back to this width range anyway).
	eval_js_once(
		"(function(){".
			"var hidden=false;".
			"window.addEventListener('scroll',function(){".
				"if(window.innerWidth>=992){".
					"if(hidden){document.body.classList.remove('epesi-topbar-hidden');hidden=false;}".
					"return;".
				"}".
				"var y=window.scrollY||document.documentElement.scrollTop;".
				"if(y<=0){".
					"if(hidden){document.body.classList.remove('epesi-topbar-hidden');hidden=false;}".
				"}else if(!hidden){".
					"document.body.classList.add('epesi-topbar-hidden');hidden=true;".
				"}".
			"},{passive:true});".
		"})();"
	);

	// Below the same 991.98px breakpoint the sidebar itself goes off-canvas
	// (Base_Box/theme_adminlte/default.css), per request: collapse every
	// GenericBrowser/RecordBrowser row's own row of action icons (view/edit/
	// delete/favourite/watchdog/...) into a single kebab (bi-three-dots-
	// vertical) toggle button, to give that screen space back to the table's
	// other columns instead. ensureToggles() below moves the row's real <a>
	// icons into a "epesi-gb-actions-icons" wrapper span it creates itself
	// and inserts the toggle button next to - both are this script's own
	// classes, not selectors matched against GenericBrowser_0.php's
	// server-rendered markup (an earlier version tried hiding
	// "td.Utils_GenericBrowser__actions > a" directly by CSS selector and
	// it silently failed to match in production for reasons never fully
	// pinned down - owning both ends of the class name here removes that
	// whole class of mismatch). The CSS half (Utils/GenericBrowser/
	// theme_adminlte/default.css, "---- mobile actions menu ----") hides
	// .epesi-gb-actions-icons and shows the toggle button below that
	// breakpoint, and styles #epesi-gb-actions-menu, the one shared
	// floating panel this script populates and positions on click.
	//
	// The panel gets a CLONE of each wrapped <a> (not the real node moved
	// out and back, unlike this same file's older table_overflow.js/
	// Utils_GenericBrowser__overflow_div pattern for a similar clipping
	// problem) - simpler here since there's nothing to restore afterwards,
	// and every one of these actions is a plain HTML href/onclick attribute
	// (GenericBrowser_0.php's own tag_attrs strings), which cloneNode
	// preserves and re-fires exactly like the original on click. Each
	// action's own accessible label - already present as a title attribute
	// via Utils_TooltipCommon::open_tag_attrs() for every one of these icons
	// - is appended as visible text next to its icon, since a touch user has
	// no hover to reveal a title tooltip the way a desktop one would.
	//
	// Appended straight to <body>, not nested anywhere in the table, so
	// position:fixed places it over the row regardless of the table's own
	// inline overflow:hidden (see adminlte-css-conflicts memory, point 9) -
	// the same reason table_overflow.js's own floating box lives at the body
	// level. Toggle injection re-runs on "e:load" (a table's rows can be
	// wholesale replaced by paging/sorting/filtering) and is idempotent per
	// cell (skips any cell that already has one), same guard style as this
	// file's other "e:load" observers.
	eval_js_once(
		"(function(){".
			"var MENU_ID='epesi-gb-actions-menu';".
			"function getMenu(){".
				"var m=document.getElementById(MENU_ID);".
				"if(!m){m=document.createElement('div');m.id=MENU_ID;document.body.appendChild(m);}".
				"return m;".
			"}".
			"function closeMenu(){".
				"var m=document.getElementById(MENU_ID);".
				"if(m)m.classList.remove('show');".
				// Was scoped to ".epesi-gb-actions-toggle.epesi-gb-actions-open" only -
				// the "More actions" toggle (.epesi-gb-more-toggle, added for the
				// desktop extra-actions grouping) opens the exact same shared menu and
				// gets the exact same "epesi-gb-actions-open" class, but this selector
				// never matched it, so closing the menu never cleared ITS open state.
				// openMenuFor()'s own "if already open, just close" toggle logic reads
				// that leftover class on the next click and short-circuits before ever
				// reopening the menu - reported as "activates once, then does nothing"
				// on repeat clicks of the More actions button specifically (the mobile
				// kebab was never affected, since it IS a .epesi-gb-actions-toggle).
				// Any element can only ever carry this state class while it's the one
				// open toggle, so matching on the state class alone is enough - no need
				// to also name every toggle class that might carry it.
				"var b=document.querySelector('.epesi-gb-actions-open');".
				"if(b)b.classList.remove('epesi-gb-actions-open');".
			"}".
			"function openMenuFor(btn,wrap){".
				"var wasOpen=btn.classList.contains('epesi-gb-actions-open');".
				"closeMenu();".
				"if(wasOpen)return;".
				"var actions=wrap.querySelectorAll('a');".
				"if(!actions.length)return;".
				"var m=getMenu();".
				"m.innerHTML='';".
				"actions.forEach(function(a){".
					"var clone=a.cloneNode(true);".
					// data-epesi-mobile-label (RowObject.php's add_info(), Record ID
					// only) stands in for the full title text where one is present -
					// today that's just the info icon, whose title is a whole "Record
					// ID / Created by / Edited by / ..." dump meant for a hover
					// tooltip, not a menu item label.
					"var label=a.getAttribute('data-epesi-mobile-label')||a.getAttribute('title');".
					"if(label){var span=document.createElement('span');span.textContent=label;clone.appendChild(span);}".
					"m.appendChild(clone);".
				"});".
				"m.classList.add('show');".
				"var r=btn.getBoundingClientRect();".
				// A row near the bottom of the viewport (last few rows of a long
				// table, small phone screen) left the menu opening downward
				// unconditionally, running off the bottom of the screen with no
				// way to reach the actions past whatever happened to fit above the
				// fold. Flip to open upward instead whenever there isn't enough
				// room below AND there IS enough room above; if neither direction
				// fits (a very long action list on a very short viewport), keep
				// opening downward and let #epesi-gb-actions-menu's own
				// max-height:60vh + overflow-y:auto (default.css) make it
				// scrollable instead of clipped.
				"var menuHeight=m.offsetHeight;".
				"var spaceBelow=window.innerHeight-r.bottom;".
				"var top;".
				"if(spaceBelow>=menuHeight+4||r.top<menuHeight+4){".
					"top=r.bottom+4;".
				"}else{".
					"top=r.top-menuHeight-4;".
				"}".
				"if(top<4)top=4;".
				"m.style.top=top+'px';".
				"var left=r.right-m.offsetWidth;".
				"if(left<4)left=4;".
				"m.style.left=left+'px';".
				"btn.classList.add('epesi-gb-actions-open');".
			"}".
			// A row's action <a>s fall into two groups: the "core" ones built
			// straight into RecordBrowser_0.php/GenericBrowser_0.php and used
			// across the app regardless of module (view/edit/delete/info/
			// print/history, the plus_gray/minus_gray expandable pair, and -
			// per request, since these were missing here and are exactly what
			// admin screens like Home Page ordering or Field management lean
			// on most - active-on/active-off, move-up/move-down/move-up-down),
			// and whatever a specific module bolts on top via its own
			// add_action() calls with the module's OWN icon (CRM_Contacts'
			// "New Meeting"/"New Task"/"New Phonecall"/"New Note",
			// CRM_Companies' equivalents, etc - genuinely open-ended, one
			// module at a time). Classified by icon filename rather than a
			// list of known module actions, since that list can never be
			// complete - anything NOT recognized as core is treated as an
			// "extra" action, so a future module's own icon groups correctly
			// without this file needing to know about it.
			"function isCoreAction(a){".
				// RecordBrowser_0.php's inline "add new record" row (add_in_table)
				// submits via a real <input type="submit"> nested in its own action
				// <a> instead of an <img>-based icon (it has to actually submit the
				// row's form, not just navigate) - with no <img> to match below it
				// fell through to "extra" and got buried behind the More actions
				// toggle for something that's core to RecordBrowser itself, used on
				// every table with this feature on, not a module bolt-on.
				"if(a.querySelector('input[type=\"submit\"]'))return true;".
				"var img=a.querySelector('img');".
				"var src=img?(img.getAttribute('src')||''):'';".
				"return /\\/(view|edit|delete|info|print|restore|active-on|active-off|move-up-down|move-up|move-down|history|history_inactive|plus_gray|minus_gray)\\.png$/.test(src)||/\\/(expand|collapse)\\.gif$/.test(src);".
			"}".
			"function ensureToggles(){".
				"document.querySelectorAll('.Utils_GenericBrowser .Utils_GenericBrowser__td.Utils_GenericBrowser__actions').forEach(function(cell){".
					"if(cell.querySelector('.epesi-gb-actions-toggle'))return;".
					"var links=Array.prototype.slice.call(cell.querySelectorAll('a'));".
					"if(!links.length)return;".
					// The "extra" actions (per request) group into their own
					// "More actions" toggle FIRST, independent of screen width - a
					// module-heavy table (Contacts/Companies) stays down to the
					// same core icon set as every other table on desktop too, not
					// just on mobile. Reuses openMenuFor()/the one shared floating
					// menu - the only difference from the mobile case below is
					// which wrapper's <a>s it's asked to clone.
					"var extra=links.filter(function(a){return !isCoreAction(a);});".
					"if(extra.length){".
						"var extraWrap=document.createElement('span');".
						"extraWrap.className='epesi-gb-actions-extra';".
						"extra.forEach(function(a){extraWrap.appendChild(a);});".
						"var moreBtn=document.createElement('button');".
						"moreBtn.type='button';".
						"moreBtn.className='epesi-gb-more-toggle';".
						"moreBtn.setAttribute('aria-label','More actions');".
						"moreBtn.addEventListener('click',function(e){".
							"e.stopPropagation();".
							"openMenuFor(moreBtn,extraWrap);".
						"});".
						"cell.appendChild(moreBtn);".
						"cell.appendChild(extraWrap);".
					"}".
					// Moving the real <a>s (now just the core ones, plus the "More
					// actions" toggle and its own extras wrapper if either exists)
					// into a wrapper this script owns end to end - rather than
					// hiding them in place via a CSS selector matched against
					// GenericBrowser_0.php's own class list - means the CSS half
					// only ever has to say ".epesi-gb-actions-icons{display:none}"
					// with nothing else to get right: no assumption about which
					// classes the <td> carries, whether the <a>s are direct
					// children, or any specificity contest with a rule elsewhere.
					// The wrapper still holds the exact same live nodes (moved,
					// not cloned), so their href/onclick/tooltip attributes and
					// any state carry over untouched. On mobile this wrapper
					// (including the "More actions" toggle nested inside it) is
					// hidden as a whole and this cell's OWN kebab takes over -
					// openMenuFor(btn,wrap) still finds every <a>, core and extra
					// alike, via querySelectorAll('a') reaching into the nested
					// .epesi-gb-actions-extra span regardless of depth.
					"var wrap=document.createElement('span');".
					"wrap.className='epesi-gb-actions-icons';".
					"while(cell.firstChild)wrap.appendChild(cell.firstChild);".
					"var btn=document.createElement('button');".
					"btn.type='button';".
					"btn.className='epesi-gb-actions-toggle';".
					"btn.setAttribute('aria-label','Actions');".
					"btn.addEventListener('click',function(e){".
						"e.stopPropagation();".
						"openMenuFor(btn,wrap);".
					"});".
					"cell.appendChild(btn);".
					"cell.appendChild(wrap);".
				"});".
			"}".
			"document.addEventListener('click',function(e){".
				"var menu=document.getElementById(MENU_ID);".
				"if(menu&&menu.classList.contains('show')&&!menu.contains(e.target)&&!e.target.closest('.epesi-gb-actions-toggle'))closeMenu();".
			"});".
			"document.addEventListener('keydown',function(e){if(e.key==='Escape')closeMenu();});".
			"window.addEventListener('resize',closeMenu);".
			"try{ensureToggles();}catch(e){}".
			"jQuery(document).on('e:load',function(){try{closeMenu();ensureToggles();}catch(e){}});".
		"})();"
	);

	// Sizes GenericBrowser's Actions column (view/edit/delete/... icons) to
	// fit whatever icons a given table actually has, per request - a flat
	// CSS width was previously tried and reverted (too narrow for tables
	// with many actions, too wide - dead space - for tables with few), and
	// no single CSS value can be right for every table, since the actual
	// icon count varies per table and only PHP knows it
	// (GenericBrowser_0.php's $max_actions - itself computed for the
	// default theme's differently-sized <img> sprite icons, not this
	// theme's Bootstrap Icons glyphs, which is what started this whole
	// problem). Measures each table's own actions cells directly instead of
	// guessing a per-icon pixel value: cell.scrollWidth reports the full
	// natural (unwrapped) content width regardless of the column's current,
	// possibly too-narrow allocated width or the table's own inline
	// overflow:hidden clipping it - .Utils_GenericBrowser__td already sets
	// white-space:nowrap (Utils/GenericBrowser/theme_adminlte/default.css),
	// so no extra style toggling is needed for an accurate reading. Sets
	// the *header* cell's width (table-layout:fixed takes column widths
	// from the first row, so this is what actually resizes the column) via
	// direct inline style, matching how GenericBrowser_0.php's own
	// PHP-computed width is set today - CSS must not add width/min-width
	// !important overrides for this column any more, that would fight this
	// script's inline value the same way it fought PHP's.
	//
	// Once the actions column has its own pixel width, the OTHER columns'
	// widths (GenericBrowser_0.php's own inline width="X%" attributes,
	// computed as a percentage of the non-actions columns only - see
	// function.html_table_epesi.php) sum to 100% of the table's own width on
	// top of that extra pixel column, so table-layout:fixed renders the
	// table wider than its ".table-responsive" wrapper and the wrapper's own
	// overflow-x:auto shows a horizontal scrollbar. Fixed by also rescaling
	// every other header cell's width in px so the whole row - actions
	// column plus the rest - sums to exactly the wrapper's own clientWidth.
	// Each column's ORIGINAL percentage is cached in a data attribute the
	// first time this runs (so re-running on "e:load" rescales from the
	// true original ratio, not from an already-rescaled px value).
	//
	// Not every non-actions column is a percentage column, though - e.g.
	// Utils_RecordBrowser's favourite/watchdog icon columns
	// (RecordBrowser_0.php: 'width'=>'24px') carry a non-numeric width, which
	// GenericBrowser_0.php's own header-building loop (its
	// "!is_numeric($v['width'])" branch) renders as a literal
	// style="width:24px" instead of a width="X%" attribute, and excludes
	// from its own $all_width percentage-weight total entirely. Tables using
	// those (RecordBrowser's Contacts/Companies, etc.) broke this rescale
	// the first time it was written: treating that "24" as if it were a
	// percentage share, alongside real percentages that already summed to
	// ~100 on their own, wildly overstated the demanded total and defeated
	// the whole fit-to-container calculation again.
	//
	// First attempt at classifying "other" headers checked whether their
	// width="X%" attribute was actually a percentage - reverted, it broke
	// EVERY table, not just Contacts/Companies. Root cause: GenericBrowser's
	// own js/col_resizable.js (the column-drag-resize plugin, active on
	// every one of these tables - see Utils/GenericBrowser/theme_adminlte/
	// default.tpl's own comment on it) runs its init immediately after the
	// table renders and, for every header cell regardless of column type,
	// converts its width="X%" attribute into a plain pixel jq(...).width()
	// - removing the width ATTRIBUTE entirely (col_resizable.js's own
	// createGrips(), "c.width(c.w).removeAttr('width')"). By the time this
	// script runs (on "e:load", after that init already executed), no
	// column's width attribute contains "%" any more, so checking for one
	// misclassified every real percentage column as "fixed" too, and each
	// got blown out to its own full unwrapped text width instead of a
	// proportional share - hence the universal overflow. Classify by CSS
	// class instead - Utils_RecordBrowser__favs/__watchdog (RecordBrowser_0.php's
	// column 'attrs') are the only actual fixed-icon columns, and col_resizable
	// only touches width/style, never class, so this survives its rewrite.
	// Every other "other" column goes back through the original ratio-based
	// share (this never actually needed the value to be a true percentage -
	// dividing by totalPercent normalizes away the unit, so it works
	// whether the cached number came from a real width="X%" or from
	// col_resizable's own hardened pixel value).
	//
	// The 24px itself is also wrong, for the same reason the old flat
	// Actions-column width was: it's sized for the default theme's 16x16
	// <img> sprite icon, not this theme's larger Bootstrap Icons glyph, so
	// favourite/watchdog icons were left cramped. These two columns are
	// therefore measured the same way the actions column is above - not
	// trusted at their PHP-declared px value at all - by walking each body
	// row's cell at the SAME column position (cellIndexOf(th); these columns'
	// <td>s carry no distinguishing class, unlike .Utils_GenericBrowser__actions)
	// and taking the largest scrollWidth, falling back to the declared
	// width only if a table somehow has no body rows yet. The measured
	// width becomes both the column's own style.width (so its icon isn't
	// cramped) and its contribution to fixedWidth (so the percentage
	// columns still share only the space that's actually left over).
	//
	// All the widths above are rounded deliberately (Math.floor for
	// actionsWidth and each percent column's share, Math.ceil for a fixed
	// column's own measured width, plus a 2px buffer subtracted from
	// availableWidth) rather than left as exact floats, because Chrome and
	// Firefox don't sub-pixel-round table column layout identically -
	// unrounded math that summed to exactly containerWidth in Chrome still
	// left Firefox 1-2px over, just enough to trigger ".table-responsive"'s
	// own scrollbar even though the table visually looked like it fit.
	// Rounding every piece down (ceiling only for the one value working
	// against the total, a fixed column's own footprint) guarantees the
	// real sum is always a little UNDER containerWidth, never over, in
	// either browser's rounding behaviour.
	//
	// Re-run on "e:load" (Epesi's own "AJAX content patch just finished"
	// event, main.js/index.js - the entire content of a GenericBrowser
	// table can be replaced by paging/sorting/filtering without a full page
	// reload) and on window resize (sidebar toggle/orientation change alter
	// the wrapper's clientWidth), not just once at initial load - wrapped in
	// try/catch per this theme's own established rule (see
	// adminlte-css-conflicts memory, point 8) that an uncaught exception in
	// any one "e:load" observer aborts the whole shared script line it runs
	// in, including the StatusBar-hiding call right after it.
	eval_js_once(
		"(function(){".
			"function epesiSizeGbActions(forceCollapse){".
				"if(forceCollapse===undefined)forceCollapse=true;".
				"try{".
					"function naturalWidth(cell){".
						// cell.scrollWidth can't be used directly here: col_resizable.js
						// forces table-layout:fixed on every one of these tables, which
						// makes a body cell's rendered box (and therefore its scrollWidth,
						// since that's never less than the box itself) track the COLUMN'S
						// current width, not the content's - including whatever width THIS
						// very function assigned last time it ran. Measuring that and
						// adding a buffer on top of it compounds every call (every resize,
						// and every debounced repeat while a window is still being
						// dragged) into an unbounded feedback loop - reproduced as a
						// column growing by a fixed ~10px per call with NO actual resize
						// at all. Cloning the cell into a detached, auto-layout table
						// (kept under the same .epesi-gb/.Utils_GenericBrowser
						// classes so the icon ::before/hidden-<img> rules in this theme's
						// own default.css still apply) measures the content alone,
						// independent of whatever width the live column currently has.
						"var holder=document.createElement('div');".
						"holder.className='epesi-gb';".
						"holder.style.cssText='position:absolute;visibility:hidden;left:-9999px;top:-9999px;';".
						"var mt=document.createElement('div');".
						"mt.className='Utils_GenericBrowser';".
						"mt.style.cssText='table-layout:auto;width:auto;';".
						"var tb=document.createElement('div');".
						"tb.className='Utils_GenericBrowser__tbody';".
						"var tr=document.createElement('div');".
						"tr.className='Utils_GenericBrowser__tr';".
						"tr.appendChild(cell.cloneNode(true));".
						"tb.appendChild(tr);".
						"mt.appendChild(tb);".
						"holder.appendChild(mt);".
						"document.body.appendChild(holder);".
						"var w=tr.firstChild.scrollWidth;".
						"document.body.removeChild(holder);".
						"return w;".
					"}".
					// A div has no native .cellIndex (that's an HTMLTableCellElement-
					// only property, always undefined on a plain element) - this grid
					// is now divs laid out via CSS table-display, not real <td>/<th>
					// elements, so the column position has to be computed from DOM
					// position among the row's own children instead.
					"function cellIndexOf(el){".
						"return Array.prototype.indexOf.call(el.parentNode.children,el);".
					"}".
					"document.querySelectorAll('.Utils_GenericBrowser').forEach(function(table){".
						"var headerCell=table.querySelector('.Utils_GenericBrowser__th.Utils_GenericBrowser__actions');".
						"if(!headerCell)return;".
						"var maxWidth=0;".
						"table.querySelectorAll('.Utils_GenericBrowser__tbody .Utils_GenericBrowser__td.Utils_GenericBrowser__actions').forEach(function(cell){".
							"var w=naturalWidth(cell);".
							"if(w>maxWidth)maxWidth=w;".
						"});".
						"if(maxWidth<=0)return;".
						"var actionsWidth=Math.floor(maxWidth+8);".
						"headerCell.style.width=actionsWidth+'px';".
						"headerCell.style.minWidth=actionsWidth+'px';".
						"var container=table.closest('.table-responsive')||table.parentElement;".
						"if(!container)return;".
						"var containerWidth=container.clientWidth;".
						"if(containerWidth<=0)return;".
						"var others=table.querySelectorAll('.Utils_GenericBrowser__thead .Utils_GenericBrowser__th:not(.Utils_GenericBrowser__actions)');".
						"var percentCols=[];".
						"var percents=[];".
						"var totalPercent=0;".
						"var fixedWidth=0;".
						"others.forEach(function(th){".
							"var isFixedIcon=th.classList.contains('Utils_RecordBrowser__favs')||th.classList.contains('Utils_RecordBrowser__watchdog');".
							"if(!isFixedIcon){".
								"var stored=th.getAttribute('data-epesi-orig-percent');".
								"var p=stored!==null?parseFloat(stored):(parseFloat(th.getAttribute('width')||th.style.width||'0')||0);".
								"if(stored===null)th.setAttribute('data-epesi-orig-percent',p);".
								"percentCols.push(th);".
								"percents.push(p);".
								"totalPercent+=p;".
							"}else{".
								"var idx=cellIndexOf(th);".
								// Below the mobile breakpoint this whole column is hidden
								// (Utils/GenericBrowser/theme_adminltedark/default.css's own
								// "@media (max-width: 991.98px)" block, keyed off this same
								// class) - the <th> already carries it straight from
								// RecordBrowser_0.php's server-rendered markup, so by the time
								// this script runs the CSS has already decided and
								// getComputedStyle can just be asked. When hidden, the column
								// reserves none of the available width (a hidden cell takes
								// none in the real rendered layout either) and the natural-width
								// measurement below is skipped - measuring a display:none clone
								// would read back 0 anyway, which used to fall through to the
								// "no body rows yet" fallback and wrongly reserve a phantom
								// ~24px for a column that isn't actually showing.
								"var favClass=th.classList.contains('Utils_RecordBrowser__favs')?'Utils_RecordBrowser__favs':'Utils_RecordBrowser__watchdog';".
								"var hiddenCol=getComputedStyle(th).display==='none';".
								"var fw=0;".
								"table.querySelectorAll('.Utils_GenericBrowser__tbody > .Utils_GenericBrowser__tr').forEach(function(row){".
									"var cell=row.children[idx];".
									"if(!cell)return;".
									// This <td> has no class or attribute of its own
									// (RecordBrowserCommon_0.php's get_fav_button()/
									// WatchdogCommon_0.php's get_change_subscription_icon() build
									// it bare - the cellIndexOf(th) walk above is already working
									// around exactly that) - added here, the one place that
									// already knows this row's cell IS the favourite/watchdog
									// column, so default.css's mobile-hide rule above (keyed off
									// this same class) reaches the header AND every body cell
									// consistently instead of just blanking the header alone.
									"cell.classList.add(favClass);".
									"if(hiddenCol)return;".
									// Bootstrap's default ".table" cell padding (.5rem each side)
									// was left in full around a single small icon, reported as
									// "extra white space" around the star/eye columns. Set inline
									// here instead, before naturalWidth() clones this same cell,
									// so the measurement and the applied padding always agree
									// with each other.
									"cell.style.padding='0.25rem 0.25rem 0.1rem';".
									"cell.style.textAlign='center';".
									// Same reasoning as the Actions column's own vertical-align:top
									// override (Utils/GenericBrowser/theme_adminlte/default.css) -
									// this cell has no class of its own for that CSS rule to
									// reach, so it's set inline here instead. Without it this icon
									// defaults to vertical-align:middle and visibly floats below
									// the row's top edge on any row where another column wraps to
									// more than one line, out of line with both the text columns
									// and the Actions column right next to it.
									"cell.style.verticalAlign='top';".
									"var w2=naturalWidth(cell);if(w2>fw)fw=w2;".
								"});".
								"if(!hiddenCol){".
									"if(fw<=0){fw=parseFloat(th.style.width)||th.getBoundingClientRect().width||24;}else{fw+=8;}fw=Math.ceil(fw);".
									"th.style.width=fw+'px';".
									"fixedWidth+=fw;".
								"}".
							"}".
						"});".
						// RecordBrowser tables default to $expandable_rows=true (RecordBrowser_0.php),
						// so every data cell renders server-side as <div class="expandable
						// expanded"> (GenericBrowser_0.php) - meant to be a transient starting
						// point that js/table_overflow.js's gb_expandable_init() (called per
						// row via GenericBrowser_0.php's own eval_js()) immediately measures and
						// either leaves alone (content already <=18px) or swaps to "collapsed"
						// (clamped to one line, overflow hidden) for. Reported by request as
						// "Browse renders one line per contact only after clicking Collapse
						// All" - i.e. that swap wasn't happening by default, leaving every row
						// at its full unclamped height. Rather than chase why that per-row init
						// isn't taking effect (gb_expandable[] is seeded by an eval_js_once()
						// call keyed on this table's own md5 path - a value stable across an
						// entire login session, so - unlike the per-row gb_expandable_init()
						// calls themselves, plain eval_js() and unaffected - that one-time seed
						// never re-sends after this table's first render in a session, a
						// plausible culprit but not one worth fully re-deriving GenericBrowser's
						// legacy state machine to confirm), this does the same clamp directly:
						// anything still "expanded" wider than one line gets force-collapsed,
						// independent of whatever state gb_expandable[] itself is in and of
						// whether this table even has any percentage columns to size (ahead of
						// the totalPercent<=0 bailout below, so it always runs), measured after
						// the width pass above so it's at each column's real, final width, not
						// a transient narrower one.
						//
						// Only wanted on a genuinely fresh render (this function's own initial
						// call, the one-off document.fonts.ready re-check, and e:load - a
						// table's rows can be wholesale replaced by paging/sorting/filtering),
						// gated behind forceCollapse for exactly that reason - the resize
						// listener further down now also calls this function (both on a real
						// window resize and, via gb_expand_all()'s own patched dispatch below,
						// right after Expand All/a single row's own expand toggle runs) purely
						// to redo the WIDTH math against the container's current clientWidth,
						// not to touch row state. Without this guard, that resize-triggered
						// call ran this same block unconditionally and found the very
						// div.expandable the user had just clicked "expand" on still
						// legitimately taller than 18px - exactly the condition this block
						// treats as "never got collapsed by default, force it" - and
						// re-collapsed it a moment later, reported as "expands then snaps
						// back to collapsed" for both the per-row toggle and Expand All.
						"if(forceCollapse)table.querySelectorAll('div.expandable.expanded').forEach(function(div){".
							"if(div.scrollHeight>18){".
								"div.classList.remove('expanded');".
								"div.classList.add('collapsed');".
								"var row=div.closest('.Utils_GenericBrowser__tr');".
								"if(row){".
									"var more=row.querySelector(\"a[id^='gb_more_']\");".
									"var less=row.querySelector(\"a[id^='gb_less_']\");".
									"if(more)more.style.display='';".
									"if(less)less.style.display='none';".
								"}".
							"}".
						"});".
						"if(totalPercent<=0)return;".
						"var availableWidth=containerWidth-actionsWidth-fixedWidth-2;".
						"if(availableWidth<0)availableWidth=0;".
						"percentCols.forEach(function(th,i){".
							"var px=Math.floor((percents[i]/totalPercent)*availableWidth);".
							"th.style.width=px+'px';".
						"});".
					"});".
				"}catch(e){}".
			"}".
			"epesiSizeGbActions();".
			// The very first call above can run before the bootstrap-icons webfont
			// has finished loading - measured directly (see font_timing_test in
			// this fix's own investigation): an icon glyph's ::before content
			// renders in a fallback font at first (12-13px), only reaching its
			// real ~16px once the icon font is actually ready, and the shortfall
			// compounds per icon. On a table with only 2-3 actions that's a few
			// px, invisible; on one with 7-8 (RecordBrowser tables that add "New
			// Meeting"/"New Task"/etc via add_action()) it was enough for the
			// column epesiSizeGbActions() had just measured too narrow to still
			// fit them, wrapping the icons onto a second line and making every
			// row in the table visibly taller - "looks expanded" even though
			// nothing about row content actually was. Re-running once
			// document.fonts.ready resolves re-measures with the icon font's
			// real metrics and corrects it without needing a window resize to
			// accidentally trigger the same recovery.
			"if(document.fonts&&document.fonts.ready)document.fonts.ready.then(epesiSizeGbActions);".
			"jQuery(document).on('e:load',epesiSizeGbActions);".
			"var epesiGbResizeTimer=null;".
			"window.addEventListener('resize',function(){".
				"if(epesiGbResizeTimer)clearTimeout(epesiGbResizeTimer);".
				"epesiGbResizeTimer=setTimeout(function(){epesiSizeGbActions(false);},150);".
			"});".
		"})();"
	);

	// "Expand All" (gb_expand_all(), js/table_overflow.js) uncollapses every
	// row's tall/rich cell content at once, which can grow the page past the
	// viewport and bring in the browser's own vertical scrollbar - eating
	// ~15-17px of width that no CSS media query or resize event fires for.
	// epesiSizeGbActions() above already recomputes each table's column
	// widths to fit its container's CURRENT clientWidth, but only runs on
	// load/e:load/window resize - none of which gb_expand_all() itself
	// triggers, since it only toggles classes on already-rendered rows. Left
	// unpatched, the table's pixel column widths stay sized for the
	// pre-scrollbar container width, overflowing the now-narrower
	// .table-responsive by exactly the scrollbar's width - reported as a
	// horizontal scrollbar appearing under the table (alongside the page's
	// own new vertical one) right after clicking Expand All. Patches
	// gb_expand/gb_collapse/gb_expand_all/gb_collapse_all (table_overflow.js,
	// loaded by GenericBrowserCommon_0.php - not guaranteed loaded by the
	// time this shell script runs, hence wait_while_null rather than a
	// direct patch like the Autocompleter.Base one above) to dispatch a real
	// 'resize' event after the original runs, reusing that same listener's
	// existing 150ms debounce instead of calling epesiSizeGbActions()
	// directly - it isn't exposed outside its own closure.
	eval_js_once(
		"wait_while_null('gb_expand_all',".
			"\"(function(){".
				"if(window.__epesiGbExpandPatched)return;".
				"window.__epesiGbExpandPatched=true;".
				"['gb_expand','gb_collapse','gb_expand_all','gb_collapse_all'].forEach(function(name){".
					"var orig=window[name];".
					"window[name]=function(){".
						"var r=orig.apply(this,arguments);".
						"window.dispatchEvent(new Event('resize'));".
						"return r;".
					"};".
				"});".
			"})();\"".
		");"
	);
{/php}
	{* Base_Help's overlay is an independent absolutely-positioned system, not part
	   of the shell being replaced - carried over unchanged so the tutorials keep
	   working. *}
	<canvas class="Base_Help__tools" style="height:3000px;width:3000px;" id="help_canvas" width="3000px" height="3000px"></canvas>
	<img class="Base_Help__tools" style="display: none;" id="Base_Help__help_arrow" src="{$theme_dir}/Base/Help/arrow.png" />
	<div class="Base_Help__tools comment" style="display: none;" id="Base_Help__help_comment"><div id="Base_Help__help_comment_contents"></div><div class="button_next" id="Base_Help__button_next">{'Next'|t}</div><div class="button_next" id="Base_Help__button_finish">{'Finish'|t}</div></div>

{* data-bs-theme is pinned to the active theme (rather than left to
   AdminLTE's own OS-prefers-color-scheme detection) so it always matches
   whichever Epesi theme is actually installed - see is_dark_theme() in
   Base_ThemeCommon. *}
<div class="epesi-adminlte app-wrapper" data-bs-theme="{if $theme_name=='adminltedark'}dark{else}light{/if}">

	<nav id="top_bar" class="app-header navbar navbar-expand nonselectable">
		<div class="container-fluid">
			<ul class="navbar-nav align-items-center">
				<li class="nav-item">
					<a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{'Toggle navigation'|t}">
						<i class="bi bi-list"></i>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link home-bar" {$home.href}>
						<i class="bi bi-house-door me-1"></i>
						<span class="d-none d-sm-inline">{$home.label}</span>
					</a>
				</li>
			</ul>

			{* Kept as its own element with the original id: Base_MainModuleIndicator
			   and the AJAX layer address it directly. *}
			<div id="module-indicator" class="navbar-text mx-2 text-truncate">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div>

			<ul class="navbar-nav ms-auto align-items-center">
				<li class="nav-item" id="search_box">{$search}</li>
				{* Dropped below lg per request - mobile has much less navbar width
				   to spend, and Perspective/Help are the two least essential items
				   in this row (the sidebar's own menu/search stay reachable
				   regardless). *}
				<li class="nav-item d-none d-lg-block" id="filter_box">{$filter}</li>
				{* $donate ("Support EPESI!") dropped from this theme's navbar to
				   keep the row to one line - Box_0.php still assigns it (shared
				   with the default theme), it's just not rendered here. *}
				<li class="nav-item login">{$login}</li>
			</ul>
		</div>
	</nav>

	<aside class="app-sidebar shadow" id="epesi_sidebar">
		<div class="sidebar-brand">
			<div class="brand-logo">{$logo}</div>
			{* Duplicate of the navbar's own data-lte-toggle (line ~65) - below the
			   lg breakpoint the open sidebar is a same-z-index-stack, higher-
			   priority overlay (z-index 1040) sitting on top of the navbar
			   (z-index 1038, see Base_Box/theme_adminlte/default.css), covering
			   the only other toggle and leaving no way to close the menu again.
			   d-lg-none: desktop never has this problem (the sidebar there pushes
			   .app-main over rather than overlaying the navbar), so hidden there
			   to avoid a redundant second button. *}
			<a class="sidebar-toggle-inline d-lg-none" data-lte-toggle="sidebar" href="#" role="button" aria-label="{'Toggle navigation'|t}">
				<i class="bi bi-list"></i>
			</a>
		</div>
		<div class="sidebar-wrapper" id="MenuBar">
			{$menu}
			{* Help's actual clickable entry is a real item in {$menu} now
			   (Base_HelpCommon::menu(), merged into the sidebar's existing
			   "Support" submenu) - {$help} here renders nothing visible any
			   more, just Base_Help's overlay/search-popup markup, which is
			   position:fixed regardless of where in the DOM it sits. Kept here
			   rather than moved back to the navbar purely so both pieces of
			   Base_Help's own template output stay in one predictable place. *}
			{$help}
		</div>
		{* "EPESI powered"/version dropped from here per request - already
		   reachable from the About section (Base_About's credits/EULA
		   popup). Base_Box_0.php still assigns $version_no (shared with the
		   default theme), just unused here now. The Logout link (relocated
		   here by the eval_js_once above) shares this one-line row with the
		   color-mode toggle below it. *}
		<div class="sidebar-footer d-flex align-items-center justify-content-center gap-2 small">
			{* adminltedark-only: a single icon-only toggle rather than the
			   light/dark pair a dropdown would need. adminlte.min.js's built-in
			   color-mode toggler ("Me" class) still does the actual work -
			   listens for clicks on any [data-bs-theme-value] element and
			   persists the choice to localStorage['lte-theme'] itself - but
			   Me has no notion of "toggle to the opposite of whatever's active
			   now", only "set to this literal value", so this element's own
			   data-bs-theme-value has to be kept pointing at the OPPOSITE of
			   the current theme at all times. Base_ThemeCommon::
			   load_theme_assets() owns that: it sets the initial value (and
			   the initial icon, via the same data-lte-theme-icon attribute
			   Me's own _showActiveTheme() toggles ".d-none" on after every
			   later click - both icons are kept in the DOM, only one visible)
			   when it seeds/resolves the starting theme, then flips this
			   element's data-bs-theme-value again on every "changed.lte.color-
			   mode" event Me dispatches after a click. No custom click handler
			   needed either way, matching this theme's convention of using
			   AdminLTE's own JS over hand-rolled listeners. *}
			<a href="#" class="epesi-theme-toggle" data-bs-theme-value="light" role="button" aria-label="{'Toggle color mode'|t}" title="{'Toggle color mode'|t}">
				<i class="bi bi-sun-fill" data-lte-theme-icon="light"></i>
				<i class="bi bi-moon-stars-fill d-none" data-lte-theme-icon="dark"></i>
			</a>
		</div>
	</aside>

	<main class="app-main">
		<div class="app-content-header" id="ActionBar">
			<div class="container-fluid d-flex flex-wrap align-items-center gap-2">
				<div class="icons flex-grow-1">{$actionbar}</div>
				{* Both ids are required: Base_ActionBar reveals them with Prototype's
				   $(id).style.display, which throws if the element is absent. *}
				<div id="launchpad_button_section_spacing" style="display:none;"></div>
				<div class="icons_launchpad" id="launchpad_button_section" style="display:none;">{$launchpad}</div>
			</div>
		</div>

		{* #content / #content_body / the span inside {$main} are the AJAX patch
		   targets used by Epesi.text() and display_module() - ids preserved. *}
		<div class="app-content" id="content">
			<div id="content_body">
				<div class="container-fluid">{$main}</div>
			</div>
		</div>
	</main>

</div>

{$status}

{/if}
