<div id="Base_ActionBar" align="center">
	<table class="ActionBar">
        <tbody>
            <tr>
                <td valign="top">
                    <div id="panel">
						{foreach item=i from=$icons}
                        <div class="panel_div" style="float: left; margin-left:5px;">
                            <div class="icon">
                                {$i.open}
									{if $display_icon}
									<!-- <img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32"> -->
									<div class="div_icon icon_{$i.icon}"></div>
									{/if}
									{if $display_text}
									<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$i.label}</td></tr></table>
									{/if}
								{$i.close}
                            </div>
                        </div>
						{/foreach}
						{foreach item=i from=$launcher}
                        <div class="panel_div" style="float: right;">
                            <div class="icon">
                                {$i.open}
									{if $display_icon}
									<img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32">
									{/if}
									{if $display_text}
									<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$i.label}</td></tr></table>
									{/if}
								{$i.close}
                            </div>
                        </div>
						{/foreach}
					</div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
