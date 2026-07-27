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
	eval_js_once(
		"(function(){".
			"var wrap=document.querySelector('.epesi-adminlte');".
			"if(!wrap)return;".
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
				<li class="nav-item" id="filter_box">{$filter}</li>
				<li class="nav-item top_bar_help">{$help}</li>
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
		</div>
		<div class="sidebar-wrapper" id="MenuBar">
			{$menu}
		</div>
		<div class="sidebar-footer text-center small">
			<a href="http://epe.si" target="_blank"><b>EPESI</b> powered</a>
			<div class="version">{$version_no}</div>
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
