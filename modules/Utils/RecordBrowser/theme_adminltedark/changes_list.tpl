{* AdminLTE-dark override of ../theme/changes_list.tpl. Rendered into the
   Tooltip module's popup (theme_adminltedark/default.css's
   .epesi-tooltip-popup, styled there under "table" selectors) via
   ajax_open_tag_attrs(..., $safe_html=true) - see
   Utils_TooltipCommon::to_safe_html()'s $keep_table param and
   theme_adminltedark/tooltip.js. A real <table> instead of the legacy
   theme's CSS-grid <div role="table">: browsers align real table columns
   for free, and each edit's who/when is emitted as its own full-width
   heading row ABOVE that edit's field changes (was a trailing row after,
   per request) so multiple edits read as separate groups instead of one
   run-on list. $header (the "Field/Old value/New value" column captions)
   is deliberately left unrendered here - per request, redundant once
   old/new values already carry their own diff-style red/green shading
   below.

   The explicit <colgroup> matches table-layout:fixed on
   .epesi-tooltip-popup table (theme_adminltedark/default.css): with
   table-layout:auto (the default), a long unbroken run of field text has
   an unbounded max-content/preferred width (word-wrap:break-word only
   affects layout once a width is already fixed, not intrinsic-size
   calculation) - inside .epesi-tooltip-popup's own width:max-content, that
   let the table refuse to shrink to the popup's max-width:480px and spill
   its content outside the popup's background/border instead of wrapping.
   Fixed layout needs a definite per-column width to size against; without
   this <colgroup>, a table whose first row happens to be one of the
   colspan="3" group_header/message rows (no per-column info at all) would
   size its columns ambiguously depending on browser fallback behavior. *}
<table>
	<colgroup>
		<col style="width:22%">
		<col style="width:39%">
		<col style="width:39%">
	</colgroup>
	{foreach from=$events item=e}
		{if isset($e.who) && $e.who !== ''}
			<tr>
				<td colspan="3" class="group_header">{$e.who}{if isset($e.when) && $e.when !== ''}, {$e.when}{/if}</td>
			</tr>
		{/if}
		{if is_string($e.what)}
			<tr>
				<td colspan="3" class="message">{$e.what}</td>
			</tr>
		{else}
			{foreach from=$e.what item=r}
				<tr>
					<td class="field">{$r.0}</td>
					<td class="data old_value">{$r.1}</td>
					<td class="data new_value">{$r.2}</td>
				</tr>
			{/foreach}
		{/if}
	{/foreach}
</table>
