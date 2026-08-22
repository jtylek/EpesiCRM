{php}
	// Base_ActionBar::body() now sends $launcher pre-sorted ascending
	// (alphabetical by the trimmed label actually displayed) directly, for
	// the adminlte themes' flexbox layout (source order = visual order).
	// This theme instead floats each item right (.panel_div_right below,
	// see default.css), which re-reverses DOM order into visual order -
	// reversing back here keeps the display ascending too.
	$this->assign('launcher', array_reverse($this->get_template_vars('launcher')));
{/php}
<div id="Base_ActionBar" align="center">
	<div class="ActionBar">
		<div id="panel">
					{foreach item=i from=$icons}
					{$i.open}
						<div class="panel_div_left epesi_big_button" helpID="{$i.helpID}">
							{if isset($i.icon_url) && $i.icon_url}
								<img src="{$i.icon_url}" alt="" align="middle" border="0" width="32" height="32">
							{else}
								<div class="div_icon icon_{$i.icon}" style="margin-top: 3px;"></div>
							{/if}
							<span>{$i.label}</span>
						</div>
					{$i.close}
					{/foreach}
					{foreach item=i from=$launcher}
					{$i.open}
						<div class="panel_div_right epesi_big_button">
                            <img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32">
							<span>{$i.label}</span>
						</div>
					{$i.close}
					{/foreach}
				</div>
	</div>
</div>
