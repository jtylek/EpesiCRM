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
	//
	// eval_js() (runs every render), not eval_js_once(): Base_Box's own output
	// is itself sent under a single span ('main_content', Epesi::go()) like any
	// other module's - whenever that whole rendered string changes (e.g.
	// $moduleindicator's text differs across a plain cross-module navigation),
	// the ENTIRE shell, #top_bar/#ActionBar included, is torn down and
	// replaced by a fresh DOM subtree, same as {$main}. That can happen many
	// times per session, not just once - an eval_js_once()-guarded watch()
	// (as this used to be) only ever attaches on the very first such replace;
	// every later one leaves the ResizeObserver bound to the old, now-detached
	// nodes, so the height vars silently stop tracking and the fixed bars can
	// end up mis-sized against real content (reported as an "invisible
	// toolbar" on mobile, where the navbar's own height is most likely to
	// actually change between screens). The el.__epesiHeightWatched marker
	// keeps this idempotent per DOM node so the (far more common) requests
	// where the shell ISN'T replaced don't pile up duplicate observers on the
	// same still-alive #top_bar/#ActionBar.
	eval_js(
		"(function(){".
			"var wrap=document.documentElement;".
			"function watch(el,prop){".
				"if(!el||el.__epesiHeightWatched)return;".
				"el.__epesiHeightWatched=true;".
				"var sync=function(){wrap.style.setProperty(prop,el.offsetHeight+'px');};".
				"sync();".
				"if(window.ResizeObserver)new ResizeObserver(sync).observe(el);".
				"else window.addEventListener('resize',sync);".
			"}".
			"watch(document.getElementById('top_bar'),'--epesi-header-height');".
			"watch(document.getElementById('ActionBar'),'--epesi-actionbar-height');".
		"})();"
	);

	// Narrow portrait phones collapse #ActionBar entirely when it has nothing
	// but pinned Quick Access launcher icons in it (default.css's own
	// "body.epesi-actionbar-empty" rule) - mainly a Dashboard thing, since
	// that's normally the one screen with no screen actions of its own at
	// all. An earlier version of that rule tried to stay CSS-only, keyed off
	// data-module-type="Base_Dashboard" via :has(), with a second :not(:has(
	// ...)) clause meant to keep the bar visible whenever a real action (e.g.
	// config mode's own "Save", or an individual applet's Save/Back/Restore
	// Defaults - Base_Dashboard/Dashboard_0.php's configure_applet()) was
	// actually present - reported as still hiding the bar live regardless,
	// for reasons never pinned down, so it was dropped in favour of
	// unconditionally hiding on Dashboard, with Save becoming unreachable
	// there below this breakpoint as a documented (not fixed) side effect.
	// Same "CSS :has() chain silently failed in production" shape already
	// hit once before for a near-identical reason (see the epesi-gb-actions-
	// menu isCoreAction() block further down this file's own comments) -
	// owning both ends of the check here instead removes that whole class of
	// mismatch, and generalizing off "does the bar actually contain a real
	// action button" rather than "is this Dashboard" makes the rule correct
	// for any screen that happens to render with none, not just that one.
	// MutationObserver instead of e:load: #ActionBar's own content is
	// shell-adjacent, patched directly by Epesi's AJAX layer on navigation
	// rather than necessarily going through the same "#content was replaced"
	// event GenericBrowser tables rely on - watching the node itself is
	// mechanism-agnostic, so this reacts correctly regardless of exactly how
	// the update happened.
	//
	// eval_js(), guarded by bar.__epesiEmptyWatched rather than eval_js_once():
	// #ActionBar is itself part of Base_Box's own 'main_content'-spanned
	// output, so it can be replaced by a fresh node whenever the shell
	// re-renders (see the --epesi-header-height watch() above for why that's
	// not actually a once-per-session event) - a session-scoped eval_js_once()
	// left every #ActionBar past the first with no observer at all.
	eval_js(
		"(function(){".
			"var bar=document.getElementById('ActionBar');".
			"if(!bar||bar.__epesiEmptyWatched)return;".
			"bar.__epesiEmptyWatched=true;".
			"function sync(){".
				"var hasAction=!!bar.querySelector('.epesi-actionbar-group:not(.epesi-actionbar-launcher-group) .epesi-actionbar-btn');".
				"document.body.classList.toggle('epesi-actionbar-empty',!hasAction);".
			"}".
			"sync();".
			"new MutationObserver(sync).observe(bar,{childList:true,subtree:true});".
		"})();"
	);

	// Below lg, the sidebar is an off-canvas overlay (see default.css) that
	// stays open (body.sidebar-open) across a menu-driven navigation until
	// the user explicitly closes it - since navigating IS what they were
	// trying to do, closing it automatically here saves that extra tap.
	// Delegated on #MenuBar (Base_Menu::build_menu_html()'s own wrapper)
	// rather than bound per-link, so individual menu links don't each need
	// their own listener. #MenuBar is still part of Base_Box's own shell
	// output though (see the --epesi-header-height watch() above), so it CAN
	// be swapped for a fresh node mid-session - eval_js() + the
	// bar.__epesiCloseBound marker re-binds on a new node exactly once
	// instead of assuming (wrongly) the original one lives forever. Excludes
	// a.menu-parent (data-bs-toggle="collapse" submenu headers - clicking one
	// only expands/collapses a submenu, no navigation happens, so the sidebar
	// shouldn't close).
	eval_js(
		"(function(){".
			"var bar=document.getElementById('MenuBar');".
			"if(!bar||bar.__epesiCloseBound)return;".
			"bar.__epesiCloseBound=true;".
			"bar.addEventListener('click',function(e){".
				"var a=e.target.closest('a.nav-link:not(.menu-parent)');".
				"if(!a)return;".
				"if(window.innerWidth<992)document.body.classList.remove('sidebar-open');".
			"});".
		"})();"
	);

	// Moves both halves of {$login} (.logged_as/"User <username>" and
	// .logout_css3_box/Logout - Login_0.php now hands both themes plain
	// data, not pre-built HTML, but its own default.tpl still assembles them
	// into ONE combined string before Base_Box ever sees it as {$login} -
	// that's a container-system boundary, not something Login_0.php's own
	// markup controls) from the navbar's {$login} slot into the sidebar
	// footer, per request (Logout moved there first, in an earlier request;
	// .logged_as joined it later). A DOM relocation since Base_Box can't
	// address either half separately through the container system - moving
	// the actual nodes post-render is the only way to send them somewhere
	// else.
	//
	// eval_js() every render, not eval_js_once(): the navbar/sidebar footer
	// are NOT shell-chrome-that-never-changes - they're part of Base_Box's own
	// 'main_content'-spanned output (Epesi::go()), which gets wholesale
	// replaced with a fresh DOM subtree any time that output's rendered text
	// differs from the last time it was sent this session (routinely true on
	// an ordinary cross-module navigation, since e.g. $moduleindicator's text
	// changes too - see the --epesi-header-height watch() above for the same
	// reasoning in more detail). A one-time eval_js_once() here only ever
	// relocates the VERY FIRST {$login} pair rendered this session; every
	// later shell replace inserts a brand new, not-yet-moved
	// .logged_as/.logout_css3_box pair that this script never touches again
	// (the server considers the JS itself already "sent"), leaving Logout
	// stuck inline in the navbar - reported as "Logout starts appearing in
	// the navbar" after viewing a second record. Re-running this on an
	// already-relocated pair is a harmless no-op (re-inserting a node at the
	// position it's already in). Order matters: logout is inserted at
	// footer.firstChild first (landing ahead of the color-mode toggle
	// already there), then .logged_as is inserted at the new firstChild
	// (landing ahead of logout) - final order is logged_as, logout, toggle.
	// See Base_User_Login/theme_adminltedark/default.css for both elements'
	// now-generic (not #top_bar-scoped) styling, restyled for their new spot,
	// and Base_Box's own default.css for the .sidebar-footer layout rules
	// (flex-wrap + .logged_as's own flex-basis:100%) that put .logged_as on
	// its own line above logout/the toggle.
	eval_js(
		"(function(){".
			"var login=document.querySelector('.logged_as');".
			"var logout=document.querySelector('.logout_css3_box');".
			"var footer=document.querySelector('.sidebar-footer');".
			"if(logout&&footer)footer.insertBefore(logout,footer.firstChild);".
			"if(login&&footer)footer.insertBefore(login,footer.firstChild);".
		"})();"
	);

	// Hides the navbar+ActionBar on scroll below lg, reclaiming vertical
	// space on a phone/small-tablet screen, per request - reappearing only
	// once scrolled back to the very top (not on scroll-up generally, which
	// is the more common pattern for this kind of UI, but not what was
	// asked for here). This was tried once before, reverted per request (in
	// favour of keeping both bars permanently visible/fixed), and is back
	// per a later request - see this file's git history for the earlier
	// round if the reasoning below needs cross-checking again.
	// body.epesi-topbar-hidden (default.css) transforms both bars
	// off-screen; .app-content-header's own transform distance reads
	// --epesi-header-height/--epesi-actionbar-height (the same two
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
				// No visible text label per action (per request) - the panel
				// is a horizontal row of icons now, not a vertical list, and
				// every cloned <a> already carries its own hover tooltip
				// (Utils_TooltipCommon::open_tag_attrs() sets data-tooltip/
				// aria-label/onmouseenter="epesi_tooltip_show(this)" on the
				// original element, all preserved by cloneNode), so a
				// second, redundant text label isn't needed to identify the
				// action - it used to read the (since-removed) title
				// attribute for exactly that, which is why this used to
				// render as icons with empty space where the label used to
				// be rather than nothing at all.
				"actions.forEach(function(a){".
					"m.appendChild(a.cloneNode(true));".
				"});".
				"m.classList.add('show');".
				"var r=btn.getBoundingClientRect();".
				// Opens as a flyout to the RIGHT of the button by default
				// (per request) instead of dropping down below it, which
				// used to overlap/cover the row(s) underneath. A button near
				// the right edge of the viewport (a narrow "actions" column
				// pinned to the right side of a wide table, small phone
				// screen) left the menu running off the right of the screen
				// with no room to reach it, so flip to the LEFT instead
				// whenever there isn't enough room to the right AND there IS
				// enough room to the left; if neither fits (a very wide menu
				// on a very narrow viewport), keep opening to the right and
				// let it clip rather than mis-measure a negative position.
				"var menuWidth=m.offsetWidth;".
				"var menuHeight=m.offsetHeight;".
				"var spaceRight=window.innerWidth-r.right;".
				"var left;".
				"if(spaceRight>=menuWidth+4||r.left<menuWidth+4){".
					"left=r.right+4;".
				"}else{".
					"left=r.left-menuWidth-4;".
				"}".
				"if(left<4)left=4;".
				"m.style.left=left+'px';".
				// Top-aligned with the button by default, nudged up only if
				// that would run the menu off the bottom of the viewport
				// (last few rows of a long table) - mirrors
				// #epesi-gb-actions-menu's own max-height:60vh+overflow-y:auto
				// (default.css) as the final fallback for a menu too tall to
				// fit even flush against the bottom edge.
				"var top=r.top;".
				"if(top+menuHeight>window.innerHeight-4)top=window.innerHeight-menuHeight-4;".
				"if(top<4)top=4;".
				"m.style.top=top+'px';".
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
				// GenericBrowser_0.php's action_icon_tag() marks the glyphs it draws for this
				// module's OWN actions (view/edit/delete/info/print/restore/history, the
				// active/move pairs, expand/collapse) with "action_button_core", so they are
				// recognised here without an <img> to read a filename off. This has to exist
				// at all because a converted action has no <img>: every one of them fell
				// through to "extra" and entire rows collapsed behind the "More actions"
				// toggle, which is what the icon conversion (2026-08-31) silently did to
				// every grid in the app. The src checks below stay for the actions still
				// rendered as images.
				"if(a.querySelector('i.action_button_core'))return true;".
				"var img=a.querySelector('img');".
				"var src=img?(img.getAttribute('src')||''):'';".
				// CRM_Mail's own "copy" row action (actions_for_mails()/copy() in
				// CRM/Mail/Mail_0.php) resolves to CRM/Mail's own theme copy_small.png,
				// not the shared Utils/Attachment icon of the same filename used
				// elsewhere (RecordBrowser's permission-rule "Clone rule", Attachment
				// itself) - per request, kept inline in the Mail list's actions column
				// instead of buried behind More actions, scoped to this exact resolved
				// path so no other module's same-named icon is affected.
				"if(/\\/CRM\\/Mail\\/theme[^\\/]*\\/copy_small\\.png$/.test(src))return true;".
				// Premium_Import's own queue-toggle action (files()/worksheets() in
				// Premium/Import/Import_0.php - "Add to queue"/"Remove from queue")
				// resolves to its own checkbox-on/checkbox-off.png, not a shared
				// GenericBrowser icon - per request, kept inline instead of buried
				// behind More actions, in either toggle state (checkbox-on = not yet
				// queued, checkbox-off = already queued - see Premium/Import/
				// theme_adminltedark/default.css's own comment on the naming), so it
				// doesn't fall back behind the toggle the moment a row's queue state
				// flips.
				"if(/\\/Premium\\/Import\\/theme[^\\/]*\\/checkbox-(on|off)(-off)?\\.png$/.test(src))return true;".
				// GenericBrowser_0.php appends "-off" to a core icon's filename
				// when the action is disabled (add_action(..., $off=true), e.g.
				// RecordBrowser_0.php's "no delete permission" row) - matched here
				// too so a disabled core action still renders inline (grayed out)
				// instead of falling through to "extra" and hiding behind More
				// actions, which is what happened before this (-off)? was added.
				"return /\\/(view|edit|delete|info|print|restore|active-on|active-off|move-up-down|move-up|move-down|history|history_inactive|plus_gray|minus_gray)(-off)?\\.png$/.test(src)||/\\/(expand|collapse)\\.gif$/.test(src);".
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
					// Widest natural content in one column, measured the same detached,
					// auto-layout way naturalWidth() measures a single cell and for the same
					// reason (table-layout:fixed makes a live cell report the COLUMN's width,
					// not its content's). Every cell of the column goes into ONE throwaway table
					// and the browser is asked for the result, rather than cloning and measuring
					// each cell in turn: one forced layout per column instead of one per cell,
					// which matters because this runs on load, on fonts.ready, on e:load and on
					// every debounced resize.
					//
					// Cells are cloned in whatever expanded/collapsed state they are currently in
					// - this runs after the force-collapse pass below, so a collapsed cell
					// measures its one clamped line, which is exactly the width that would stop it
					// showing an ellipsis. Returns 0 when there is nothing to measure, which the
					// caller reads as "no opinion" and leaves that column on its weight alone.
					"function columnNaturalWidth(table,idx){".
					"var rows=table.querySelectorAll('.Utils_GenericBrowser__tbody > .Utils_GenericBrowser__tr');".
					"if(!rows.length)return 0;".
					"var holder=document.createElement('div');".
					"holder.className='epesi-gb';".
					"holder.style.cssText='position:absolute;visibility:hidden;left:-9999px;top:-9999px;';".
					"var mt=document.createElement('div');".
					"mt.className='Utils_GenericBrowser';".
					"mt.style.cssText='table-layout:auto;width:auto;';".
					"var tb=document.createElement('div');".
					"tb.className='Utils_GenericBrowser__tbody';".
					"var any=false;".
					"Array.prototype.forEach.call(rows,function(row){".
					"var cell=row.children[idx];".
					"if(!cell)return;".
					"var tr=document.createElement('div');".
					"tr.className='Utils_GenericBrowser__tr';".
					"tr.appendChild(cell.cloneNode(true));".
					"tb.appendChild(tr);".
					"any=true;".
					"});".
					"if(!any)return 0;".
					"mt.appendChild(tb);".
					"holder.appendChild(mt);".
					"document.body.appendChild(holder);".
					"var w=0;".
					"Array.prototype.forEach.call(tb.children,function(tr){".
					"var cw=tr.firstChild.scrollWidth;".
					"if(cw>w)w=cw;".
					"});".
					"document.body.removeChild(holder);".
					"return w;".
					"}".
					"function cellIndexOf(el){".
						"return Array.prototype.indexOf.call(el.parentNode.children,el);".
					"}".
					"document.querySelectorAll('.Utils_GenericBrowser').forEach(function(table){".
						"var headerCell=table.querySelector('.Utils_GenericBrowser__th.Utils_GenericBrowser__actions');".
						// The actions column used to be this pass's entry ticket: no
						// ".Utils_GenericBrowser__actions" header and the whole table was skipped -
						// so every grid without row actions (Administrator > Login Audit, Shoutbox,
						// Search results, Contacts > Activities, Cron, Reports, Fax, Lang) kept the raw
						// weight-proportional split and its clipped columns, even though nothing in the
						// width math below actually needs an actions column to exist. A grid only gets
						// one from add_action() or, via GenericBrowser_0.php's expand-action fallback,
						// from set_expandable(true) - LoginAudit_0.php calls neither. It is now just one
						// more fixed-width contributor: present, it is measured and reserved exactly as
						// before; absent, it reserves 0 and the content-aware split below runs on the
						// remaining columns unchanged.
						"var actionsWidth=0;".
						"if(headerCell){".
						"var maxWidth=0;".
						"table.querySelectorAll('.Utils_GenericBrowser__tbody .Utils_GenericBrowser__td.Utils_GenericBrowser__actions').forEach(function(cell){".
							"var w=naturalWidth(cell);".
							"if(w>maxWidth)maxWidth=w;".
						"});".
						// maxWidth<=0 means there were no body action cells to measure - an empty grid,
						// or one whose rows have not arrived yet. That used to abandon the table the
						// same way a missing header did; keep the width the column already carries
						// (GenericBrowser_0.php's own inline px guess, or this script's last result)
						// instead, so the reservation stays honest and the columns beside it are still
						// sized.
						"if(maxWidth>0){".
						"actionsWidth=Math.floor(maxWidth+8);".
						"headerCell.style.width=actionsWidth+'px';".
						"headerCell.style.minWidth=actionsWidth+'px';".
						"}else{".
						"actionsWidth=Math.ceil(parseFloat(headerCell.style.width)||headerCell.getBoundingClientRect().width||0);".
						"}".
						"}".
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
								// A column whose PHP-side set_header_properties() width was a real
								// CSS length (e.g. Utils_Attachment's 'edited_on'=>"12em" -
								// Attachment_0.php) arrives here as an inline th.style.width, not
								// the width="NN%" HTML attribute the plain numeric-weight columns
								// get (GenericBrowser_0.php's is_numeric($v['width']) branch).
								// parseFloat("12em") silently drops the "em" and returns 12 - previously
								// fed straight into the percent-weighted split below as if "12" meant
								// "12% of the row", alongside a wide text column's own much larger
								// weight (e.g. "Note"'s default 90-100) - so this column was always
								// squeezed to roughly 12/(12+90) of the available width regardless of
								// container size, never enough to fit a 3-line date/time/user cell.
								//
								// Converting the "12em" itself to a flat px number (an earlier version
								// of this fix) overcorrected: 12em is comfortably more than a short
								// date/time/name actually needs, so on a narrow container - where the
								// "Note" column has the least room to spare - that flat guess left a
								// visible dead gap in this column while squeezing Note. Measuring the
								// column's own real content instead (same naturalWidth() clone-and-
								// measure technique the favs/watchdog icon columns just below already
								// use) sizes it to fit exactly, on any container width, without relying
								// on the author's em guess at all - the em unit only still matters here
								// as the *signal* that this column is fixed-shape, not proportional.
								//
								// That signal only survives long enough to READ once, though: this same
								// branch below (both outcomes) ends by writing a plain "NNpx" back onto
								// th.style.width, so a percent column like "Note" (initially "90%") looks
								// exactly like an absolute-length column ("642px") to this raw/isPercent
								// check on the very next run (window resize, e:load, Expand All...) -
								// mis-routing it into the natural-content measurement branch instead,
								// which then measures Note's own full unwrapped BBCode/HTML content in an
								// unconstrained clone (routinely 1000s of px) and locks the column there.
								// Deciding the column's kind once, on first sight, into a data attribute -
								// same pattern data-epesi-orig-percent already uses for the percent value
								// itself just below - keeps every later run trusting that original
								// classification instead of re-deriving it from this script's own prior
								// output.
								"var colKind=th.getAttribute('data-epesi-col-kind');".
								"if(colKind===null){".
									"var raw0=th.getAttribute('width')||th.style.width||'';".
									"colKind=(raw0.indexOf('%')===-1&&/(em|px|rem)$/.test(raw0))?'absolute':'percent';".
									"th.setAttribute('data-epesi-col-kind',colKind);".
								"}".
								"if(colKind==='absolute'){".
									"var idxAbs=cellIndexOf(th);".
									"var fwAbs=0;".
									"table.querySelectorAll('.Utils_GenericBrowser__tbody > .Utils_GenericBrowser__tr').forEach(function(row){".
										"var cell=row.children[idxAbs];".
										"if(!cell)return;".
										"var wAbs=naturalWidth(cell);if(wAbs>fwAbs)fwAbs=wAbs;".
									"});".
									"var awPx=fwAbs>0?Math.ceil(fwAbs+6):(parseFloat(th.style.width)||th.getBoundingClientRect().width||0);".
									"th.style.width=awPx+'px';".
									"fixedWidth+=awPx;".
								"}else{".
									"var stored=th.getAttribute('data-epesi-orig-percent');".
									"var p=stored!==null?parseFloat(stored):(parseFloat(th.getAttribute('width')||th.style.width||'0')||0);".
									"if(stored===null)th.setAttribute('data-epesi-orig-percent',p);".
									"percentCols.push(th);".
									"percents.push(p);".
									"totalPercent+=p;".
								"}".
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
									// Side padding trimmed 0.25rem -> 0.1rem (2026-08-31, per
									// request: "decrease the column width just to make it
									// fit"), alongside the smaller glyph itself in
									// Utils/GenericBrowser/theme_adminltedark/default.css and
									// the smaller fit buffer below. Vertical padding left
									// alone - it sets this icon's alignment against the text
									// rows beside it, not the column's width.
									"cell.style.padding='0.25rem 0.1rem 0.1rem';".
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
									// The +8 fit buffer these icon columns used to get was set
								// when the cell still had 0.25rem of side padding and a
								// full-size glyph; with both trimmed it was most of what
								// was left of the extra width. +2 still absorbs the
								// sub-pixel difference between this detached clone and the
								// real cell (the reason a buffer exists at all) without
								// padding the column out. The fw<=0 fallback is unchanged:
								// it means there were no body rows to measure.
								"if(fw<=0){fw=parseFloat(th.style.width)||th.getBoundingClientRect().width||24;}else{fw+=2;}fw=Math.ceil(fw);".
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
						// The weight-proportional split above is the whole story only when the
						// weights actually carry information. They usually do not: RecordBrowser
						// hands every ordinary text column the same default (all seven of Contacts:
						// Browse's arrive here as weight 14), so "proportional" degrades to "every
						// column identical, regardless of what is in it" - and a column whose
						// content is simply longer than average clips against its ellipsis while its
						// neighbours sit half empty. Measured on Contacts: Email needed 205px and got
						// 177, while the other six columns held 292px between them that nothing used.
						//
						// So: keep the weighted split as the baseline (it is the only thing that
						// respects a table whose weights ARE meaningful, e.g. a Note column at 90
						// against a date at 12), then move unused width to the columns that are short
						// of their content. A column can only ever donate what it does not need for
						// its own content, which is what keeps this safe on those meaningful-weight
						// tables: a huge Note column has little or no surplus to give away, so nothing
						// is taken from it, and where no column is short the whole pass is a no-op and
						// the layout is byte-for-byte what it was before.
						//
						// Deficits are filled smallest-first (water-filling), not in proportion to how
						// short each column is: a Note column measures its full unwrapped text,
						// routinely thousands of px, so a proportional share would hand it nearly the
						// whole pool and leave a genuinely-fixable 28px shortfall like Email's still
						// clipping. Smallest-first settles every column it can afford outright and
						// gives what is left to the bottomless one.
						"var basePx=[];".
						"var wantPx=[];".
						"percentCols.forEach(function(th,i){".
						"basePx.push(Math.floor((percents[i]/totalPercent)*availableWidth));".
						"wantPx.push(columnNaturalWidth(table,cellIndexOf(th)));".
						"});".
						"var pool=0,wanters=[];".
						"basePx.forEach(function(px,i){".
						"if(wantPx[i]>0&&px>wantPx[i])pool+=px-wantPx[i];".
						"else if(wantPx[i]>px)wanters.push(i);".
						"});".
						"if(pool>0&&wanters.length){".
						"wanters.sort(function(a,b){return (wantPx[a]-basePx[a])-(wantPx[b]-basePx[b]);});".
						"var left=pool;".
						"wanters.forEach(function(idx,n){".
						"var share=Math.floor(left/(wanters.length-n));".
						"var give=Math.min(wantPx[idx]-basePx[idx],share);".
						"if(give>0){basePx[idx]+=give;left-=give;}".
						"});".
						// Reclaim the same total from the donors, in proportion to how much spare
						// each had, so the row still sums to availableWidth.
						"var taken=pool-left;".
						"if(taken>0){".
						"var spare=basePx.map(function(px,i){return (wantPx[i]>0&&px>wantPx[i])?px-wantPx[i]:0;});".
						"var spareTotal=spare.reduce(function(a,b){return a+b;},0);".
						"if(spareTotal>0)basePx.forEach(function(px,i){".
						// Ceil, not round, on the amount each donor gives back: this file's own
						// rounding rule (see the header comment above) is that every piece rounds so
						// the row's real total lands a little UNDER the container, never over, since
						// Chrome and Firefox do not round table columns identically and 1px over is
						// enough to trigger ".table-responsive"'s scrollbar. Here the value works
						// against the total, so it rounds up.
						"if(spare[i]>0)basePx[i]=px-Math.ceil(spare[i]*taken/spareTotal);".
						"});".
						"}".
						"}".
						"percentCols.forEach(function(th,i){".
						"th.style.width=basePx[i]+'px';".
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

	// CRM_Mail's own Threaded/Flat e-mail browse tables (addon_threaded()/
	// addon_flat()/thread_addon() in CRM/Mail/Mail_0.php) show First Date/
	// Last Date/Date columns that are full timestamps - per request, trimmed
	// to date-only in these lists specifically, while a single e-mail's own
	// "Date" field (mails.tpl) keeps its time. RecordBrowser has no
	// per-browse-instance display override: a field's rendered value always
	// goes through Utils_RecordBrowserCommon::get_val(), which resolves the
	// display callback straight from the tab+field's OWN global registration
	// (self::$display_callback_table, backed by the rc_mails/rc_mail_threads
	// _callback DB table) - not from anything RecordBrowser_0.php::
	// set_header_properties() can pass in per call, so switching the field's
	// own display_callback to a date-only formatter would strip the time
	// everywhere that field renders, including the single e-mail/thread
	// record view. Done client-side instead, identifying these specific
	// tables by their header text (First Date+Last Date for Threaded;
	// Message+Archived by, both CRM_Mail-specific labels, for Flat/the
	// per-thread expanded list) rather than any module-specific class, since
	// none exists on the table root (see gb-actions-menu-flyout-direction
	// memory's isCoreAction() for the same "identify by content" reasoning).
	//
	// Strips a trailing time-of-day (with or without seconds, 12h am/pm or
	// 24h - Base_RegionalSettingsCommon::time2reg() always renders "<date>
	// <time>" in that order, one space-separated block each, regardless of
	// which of the site's configurable date/time format strings is active)
	// rather than assuming the date portion itself has no internal spaces -
	// some of Base_RegionalSettingsInstall's own date format choices
	// ("%d %B %Y") do. Modifies the cell's own ".expandable" wrapper div's
	// text, not the <td> itself - GenericBrowser's row cells all render
	// wrapped in that div by default (see epesiSizeGbActions's own comment on
	// it, just above), and overwriting the <td>'s full content would strip
	// the wrapper along with it, breaking that same row-height clamping.
	eval_js_once(
		"(function(){".
			"function stripTime(t){".
				"return t.replace(/\\s+\\d{1,2}:\\d{2}(?::\\d{2})?\\s*(?:[AaPp][Mm])?\\s*$/,'');".
			"}".
			"function headerIndex(headRow,label){".
				"var ths=headRow.children;".
				"for(var i=0;i<ths.length;i++)if(ths[i].textContent.trim()===label)return i;".
				"return -1;".
			"}".
			"function trimColumn(table,headRow,label){".
				"var idx=headerIndex(headRow,label);".
				"if(idx<0)return;".
				"table.querySelectorAll('.Utils_GenericBrowser__tbody > .Utils_GenericBrowser__tr').forEach(function(row){".
					"var cell=row.children[idx];".
					"if(!cell)return;".
					"var target=cell.querySelector('.expandable')||cell;".
					"var t=target.textContent;".
					"var stripped=stripTime(t).trim();".
					"if(stripped!==t.trim())target.textContent=stripped;".
				"});".
			"}".
			"function trimMailDates(){".
				"document.querySelectorAll('.Utils_GenericBrowser').forEach(function(table){".
					"var headRow=table.querySelector('.Utils_GenericBrowser__thead .Utils_GenericBrowser__tr');".
					"if(!headRow)return;".
					"var labels=Array.prototype.map.call(headRow.children,function(th){return th.textContent.trim();});".
					"if(labels.indexOf('First Date')!==-1&&labels.indexOf('Last Date')!==-1){".
						"trimColumn(table,headRow,'First Date');".
						"trimColumn(table,headRow,'Last Date');".
					"}else if(labels.indexOf('Message')!==-1&&labels.indexOf('Archived by')!==-1){".
						"trimColumn(table,headRow,'Date');".
					"}".
				"});".
			"}".
			"try{trimMailDates();}catch(e){}".
			"jQuery(document).on('e:load',function(){try{trimMailDates();}catch(e){}});".
		"})();"
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
			{* $home (icon+"Home" label link to the Dashboard) dropped from this
			   theme's navbar entirely, on every screen size, per request -
			   Box_0.php still assigns it (shared with the default theme), it's
			   just not rendered here any more. The sidebar's own Dashboard menu
			   entry (Base_Menu) stays as the way back to it. *}
			<ul class="navbar-nav align-items-center">
				<li class="nav-item">
					<a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{'Toggle navigation'|t}">
						<i class="bi bi-list"></i>
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
				{* Per request: a permanent navbar icon on both desktop and mobile,
				   positioned as the last (rightmost) item in the row. Reuses
				   Base_ActionBar's own "actionbar_launchpad" Leightbox popup rather
				   than building a second one - any element with class="lbOn"
				   rel="actionbar_launchpad" opens the same popup (leightbox.js's
				   leightbox_reload() binds every ".lbOn" element in the DOM to its
				   own "rel", not just one designated trigger), so this needs no new
				   PHP/JS of its own. ActionBar_0.php's launchpad() no longer renders
				   its own matching button under this theme (guarded by
				   Base_ThemeCommon::is_adminlte_family()) - this navbar icon
				   replaced it, so the ActionBar copy would otherwise be a second
				   button opening the exact same popup. bi-grid-3x3-gap-fill matches
				   Base_BootstrapIcons::resolve()'s 'launcher' mapping (adminlte_
				   icons.php) - the same glyph that ActionBar button used to render. *}
				<li class="nav-item epesi-launchpad-trigger">
					<a class="nav-link lbOn" rel="actionbar_launchpad" href="javascript:void(0)" aria-label="{'Launchpad'|t}">
						<i class="bi bi-grid-3x3-gap-fill"></i>
					</a>
				</li>
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
		<div class="sidebar-footer d-flex align-items-center justify-content-start gap-2 small">
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
