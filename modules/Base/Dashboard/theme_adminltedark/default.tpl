{* The applet "box" chrome shared by every dashboard widget - both the real
   grid (Base_Dashboard::display_dashboard()) and the config-mode "add new
   applet" picker panel (Base_DashboardCommon::get_installed_applets_html())
   render through this one template, so theming it here covers both.

   Preserves every class/id the JS (Base_Dashboard/ab.js) depends on:
   .applet (outer wrapper, printed by the caller, not this template),
   {$handle_class} ("handle" - jQuery UI sortable drag handle, kept on the
   title only, not the action icons, so buttons stay clickable), .toggle/
   .remove/.href/.configure (own click handlers), .content (toggled via
   .toggle('blind')), and $__link.remove's id="dashboard_remove_applet_<id>"
   (looked up directly by id in remove_applet()). *}
<div class="card mb-3 epesi-applet-card{if $color} epesi-applet-{$color}{/if}">
	<div class="card-header epesi-applet-header d-flex align-items-center justify-content-between gap-2">
		<span class="epesi-applet-title {$handle_class}{if $fixed} fixed{/if}">{$caption}</span>
		<div class="epesi-applet-actions">
			{foreach item=action from=$actions}
				{$action}
			{/foreach}
			{if isset($href)}
				{$__link.href.open}<i class="bi bi-arrows-fullscreen"></i>{$__link.href.close}
			{/if}
			{if isset($toggle)}
				{* Starts expanded (card-body has no collapsed state on render) - chevron-up
				   means "click to collapse"; ab.js's toggle click handler flips this to
				   bi-chevron-down (and back) in step with the .content blind toggle. *}
				{$__link.toggle.open}<i class="bi bi-chevron-up"></i>{$__link.toggle.close}
			{/if}
			{if isset($configure)}
				{$__link.configure.open}<i class="bi bi-gear"></i>{$__link.configure.close}
			{/if}
			{if isset($remove)}
				{$__link.remove.open}<i class="bi bi-x-lg"></i>{$__link.remove.close}
			{/if}
		</div>
	</div>
	<div class="card-body epesi-applet-body p-2">{$content}</div>
</div>
