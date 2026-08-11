{* Was a <table> hand-wrapping every 4 icons into a new <tr> - flex-wrap
   replaces that, same recipe used app-wide for this pattern (see
   AI-shared/adminlte-theme.md). *}
<div id="Base_User_Settings">
	<div class="epesi_label header">{$header}</div>
	<div style="display: flex; flex-wrap: wrap;">
		{foreach key=key item=button from=$buttons}
			{$__link.buttons.$key.link.open}
			<div class="epesi_big_button bigger">
				{if isset($button.icon)}
					<img src="{$button.icon}" border="0" width="32" height="32" align="middle">
				{/if}
				<span>
					{$__link.buttons.$key.link.text}
				</span>
			</div>
			{$__link.buttons.$key.link.close}
			<!-- $key holds name of the module -->
		{/foreach}
	</div>
</div>
