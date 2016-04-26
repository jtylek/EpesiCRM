<div id="Base_ActionBar" align="center">
	<table class="ActionBar">
        <tbody>
            <tr>
                <td valign="top">
                    <div id="panel">
						{foreach item=i from=$icons}
						{$i.open}
							<div class="btn btn-default" helpID="{$i.helpID}">
								{if $i.icon_url}
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
							<div class="btn btn-default pull-right">
                                <div class="div_icon"><img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32"></div>
								<span>{$i.label}</span>
							</div>
						{$i.close}
						{/foreach}
					</div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
