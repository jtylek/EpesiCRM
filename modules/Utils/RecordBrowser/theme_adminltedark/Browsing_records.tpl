{* Per request: the module icon + caption (e.g. "Companies") are dropped
   from this screen - $caption/$icon are still assigned by RecordBrowser_0.php
   (shared with the default theme), just not rendered here. The
   isset($caption) check around the view-mode select is kept only because
   that's what governed its visibility in the original markup (both lived in
   the same <td>), not because the caption itself is still needed - so
   disable_headline() still hides the view-mode picker exactly as before. *}
<div class="epesi-br">
	<div class="epesi-br-top">
		<div class="epesi-br-left">
			{if isset($caption) && isset($form_data)}
				<div class="epesi-br-viewmode">
					{$form_open}
					{$form_data.browse_mode.html}
					{$form_close}
				</div>
			{/if}
			{if isset($expand_collapse)}
				<div class="btn-group btn-group-sm" role="group">
					<a id="{$expand_collapse.e_id}" class="btn btn-outline-secondary" {$expand_collapse.e_href}><i class="bi bi-arrows-expand"></i> {$expand_collapse.e_label}</a>
					<a id="{$expand_collapse.c_id}" class="btn btn-outline-secondary" {$expand_collapse.c_href}><i class="bi bi-arrows-collapse"></i> {$expand_collapse.c_label}</a>
				</div>
			{/if}
		</div>
		{if isset($filters.controls)}
			<div class="epesi-br-filters">
				{$filters.controls}
			</div>
		{/if}
	</div>
	{if isset($filters.elements)}
		<div class="epesi-br-filter-elements">
			{$filters.elements}
		</div>
	{/if}
</div>

{$table}
