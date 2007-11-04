<div id="Base_ActionBar" align="center">
	<table class="ActionBar">
        <tbody>
            <tr>
                <td valign="top">
                    <div id="panel">
						{foreach item=i from=$icons}
                        <div style="float: left;">
                            <div class="icon">
                                {$i.open}
									{if $display_icon}
									<img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32">
									{/if}
									{if $display_text}
                                    <span style="font-size: 3px;">&nbsp;</span>
									<span>{$i.label}</span>
									{/if}
								{$i.close}
                            </div>
                        </div>
						{/foreach}
						{foreach item=i from=$launcher}
                        <div style="float: right;">
                            <div class="icon">
                                {$i.open}
									{if $display_icon}
									<img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32">
									{/if}
									{if $display_text}
                                    <span style="font-size: 3px;">&nbsp;</span>
									<span>{$i.label}</span>
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
