<div id="Base_ActionBar" align="center">
	<table class="icons">
        <tbody>
            <tr>
                <td valign="top">
                    <div id="panel">
						{foreach item=i from=$icons}
                        <div style="float: left;">
                            <div class="icon">
                                {$i.open}
									{if $display_icon}
									<img src="{$theme_dir}/images/icons/icon-{$i.icon}.png" alt="" align="middle" border="0" width="32" height="32">
									{/if}
									{if $display_text}
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
