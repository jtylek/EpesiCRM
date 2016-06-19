<div id="Base_ActionBar" align="center">
	<table class="ActionBar">
        <tbody>
            <tr>
                <td valign="top">
                    <div id="panel">
						{foreach item=i from=$icons}
						{$i.open}
							<div class="btn btn-default" helpID="{$i.helpID}">
                                <i class="fa fa-{$i.icon} fa-3x"></i>
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
