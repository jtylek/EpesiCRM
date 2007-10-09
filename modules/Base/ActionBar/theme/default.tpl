<div id="Base_ActionBar" align="center">
	<div class="main">
		<table class="icons">
        <tbody>
            <tr>
                <td valign="top">
                    <div id="panel">
						{foreach item=i from=$icons}
						{$i.open}
                        <div style="float: left;">
                            <div class="icon">
                                <a href="">
									{if $display_icon}
									<img src="{$theme_dir}/images/icons/icon-{$i.icon}.png" alt="" align="middle" border="0">
									{/if}
									{if $display_text}
									<span>{$i.label}</span>
									{/if}
								</a>
                            </div>
                        </div>
						{$i.close}
						{/foreach}
					</div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
