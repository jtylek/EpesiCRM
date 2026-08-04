<center>

<div id="CRM_Filters" style="display: flex; flex-wrap: wrap; justify-content: center;">
        <!-- MY -->
	    {$__link.my.open}
		<div class="epesi_big_button">
            <img src="{$theme_dir}/CRM/Filters/my.png" alt="" align="middle" border="0" width="32" height="32">
			<span>{$__link.my.text}</span>
        </div>
	    {$__link.my.close}

		{if isset($all)}
			<!-- ALL -->
			{$__link.all.open}
			<div class="epesi_big_button">
				<img src="{$theme_dir}/CRM/Filters/all.png" alt="" align="middle" border="0" width="32" height="32">
				<span>{$__link.all.text}</span>
			</div>
			{$__link.all.close}
		{/if}

        <!-- MANAGE FILTERS -->
	    {$__link.manage.open}
		<div class="epesi_big_button">
            <img src="{$theme_dir}/CRM/Filters/manage.png" alt="" align="middle" border="0" width="32" height="32">
			<span>{$__link.manage.text}</span>
        </div>
	    {$__link.manage.close}
</div>
<div id="CRM_Filters" style="text-align: center;">
			{$contacts_open}
				{$contacts_data.crm_filter_contact.label}&nbsp;<span class="filters-autoselect">{$contacts_data.crm_filter_contact.html}</span>&nbsp;<span class="child_button">{$contacts_data.submit.html}</span>
			{$contacts_close}
</div>

<br>

{if !empty($filters)}
	{* Was a <table> hand-wrapping every 4 saved filters into a new <tr> -
	   flex-wrap replaces that, same recipe used app-wide for this pattern
	   (see AI-shared/adminlte-theme.md). *}
	<div id="CRM_Filters">
		<div class="epesi_label header">&nbsp;&nbsp;{$saved_filters}&nbsp;&nbsp;</div>
		<div style="display: flex; flex-wrap: wrap;">
		{foreach item=p key=k from=$filters}
			{$p.open}
			<div class="epesi_big_button">
				<span class="text">{$p.title}</span>
			</div>
			{$p.close}
		{/foreach}
		</div>
	</div>
{/if}

</center>
