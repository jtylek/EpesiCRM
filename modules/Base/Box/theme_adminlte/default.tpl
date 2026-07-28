{* Portrait-mode block for phone-width screens - see default.css for the
   media query. A fixed, full-viewport overlay rather than hiding sibling
   elements: renders once here, ahead of both branches below, so it covers
   the login screen too without needing to know anything about how deep
   either branch's markup ends up nested by the time it reaches <body>. *}
<div id="epesi-rotate-prompt">
	<i class="bi bi-phone-landscape"></i>
	<p>{'Please rotate your device to landscape mode.'|t}</p>
</div>

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

	// Fixes the autocomplete/autoselect suggestion dropdown
	// (Libs/QuickForm/FieldTypes/autocomplete, e.g. a Contact's "Company
	// Name" field) rendering far off its input - reported as appearing
	// below the whole table. Root cause: script.aculo.us's
	// Ajax.Autocompleter (controls.js) makes its suggestion <div>
	// position:absolute and sets its top/left via Position.clone(), which
	// computes the *page-relative* offset of the input field - correct only
	// if the suggestion div's own containing block is the page/body itself.
	// But the suggestion div is printed as a plain sibling of the input,
	// inside RecordBrowser's .data cell, which is position:relative (for
	// its own unrelated .help icon overlay - View_entry.css) - CSS
	// position:relative on ANY ancestor establishes a new containing block,
	// so the computed page-relative top/left ends up applied relative to
	// that .data cell's own edge instead, landing the dropdown wherever
	// .data happens to sit on the page (usually far below, deep in a table
	// row) rather than under the input. Not something a plain CSS override
	// can fix without breaking .data's own need for position:relative
	// elsewhere - relocating the suggestion div itself to <body> (this
	// theme's second use of that technique - see the Logout-relocation
	// script above) removes the offending ancestor from its containing-
	// block chain entirely, so Position.clone()'s page-relative math
	// resolves correctly again. Patches Autocompleter.Base.prototype.show
	// (not Ajax.Autocompleter's own prototype - Prototype.js's Class.create
	// does real prototypal inheritance via `new subclass`, not a method
	// copy, so patching the shared base reaches every subclass, including
	// Ajax.Autocompleter, without needing to know every place this widget
	// gets instantiated) to move the update <div> to <body> immediately
	// before its normal positioning logic runs, wrapping - not replacing -
	// the original method so its IE-fix/effects logic is untouched.
	// controls.js loads lazily (only pages with an autocomplete field ever
	// load_js() it, and possibly after this shell script has already run),
	// so this waits on the shared wait_while_null() helper (include/
	// epesi.js) already used elsewhere in this codebase for the same
	// "patch a class once it exists" need - checked as the bare identifier
	// "Autocompleter" (safe to typeof-check even if undefined; the dotted
	// "Autocompleter.Base" is not, if Autocompleter itself doesn't exist
	// yet).
	eval_js_once(
		"wait_while_null('Autocompleter',".
			"\"(function(){".
				"if(!Autocompleter.Base||Autocompleter.Base.__epesiPatched)return;".
				"var origShow=Autocompleter.Base.prototype.show;".
				"Autocompleter.Base.prototype.show=function(){".
					"if(this.update&&this.update.parentNode&&this.update.parentNode!==document.body){".
						"document.body.appendChild(this.update);".
					"}".
					"return origShow.apply(this,arguments);".
				"};".
				"Autocompleter.Base.__epesiPatched=true;".
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

{* data-bs-theme is pinned because AdminLTE's JS follows the OS
   prefers-color-scheme and would otherwise flip Bootstrap to its dark palette.
   The theme is designed light throughout; dark support would need the whole
   shell (and the module screens inside it) styled for it. *}
<div class="epesi-adminlte app-wrapper" data-bs-theme="light">

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
				{if $quick_access_menu}
					<li class="nav-item quick-access-bar d-none d-lg-block">{$quick_access_menu}</li>
				{/if}
				<li class="nav-item" id="search_box">{$search}</li>
				{* Dropped below lg (same breakpoint quick-access-bar already uses
				   above) per request - mobile has much less navbar width to spend,
				   and Perspective/Help are the two least essential items in this
				   row (the sidebar's own menu/search stay reachable regardless). *}
				<li class="nav-item d-none d-lg-block" id="filter_box">{$filter}</li>
				<li class="nav-item top_bar_help d-none d-lg-block">{$help}</li>
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
		</div>
		{* "EPESI powered"/version dropped from here per request - already
		   reachable from the About section (Base_About's credits/EULA
		   popup). Base_Box_0.php still assigns $version_no (shared with the
		   default theme), just unused here now. The Logout link (relocated
		   here by the eval_js_once above) is the only, and so last, thing
		   left in the sidebar footer. *}
		<div class="sidebar-footer text-center small"></div>
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
