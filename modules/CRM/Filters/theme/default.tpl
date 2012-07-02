<center>

<table id="CRM_Filters" cellspacing="0" cellpadding="0">
	<tr>
        <!-- MY -->
        <td>

	    {$__link.my.open}
		<div class="epesi_big_button">
            {if $display_icon}
            <img src="{$theme_dir}/CRM/Filters/my.png" alt="" align="middle" border="0" width="32" height="32">
            {/if}
            {if $display_text}
                <span>{$__link.my.text}</span>
            {/if}
        </div>
	    {$__link.my.close}

        </td>

        <!-- ALL -->
        <td>

	    {$__link.all.open}
		<div class="epesi_big_button">
            {if $display_icon}
            <img src="{$theme_dir}/CRM/Filters/all.png" alt="" align="middle" border="0" width="32" height="32">
            {/if}
            {if $display_text}
                <span>{$__link.all.text}</span>
            {/if}
        </div>
	    {$__link.all.close}

        </td>

        <!-- MANAGE FILTERS -->
        <td>

	    {$__link.manage.open}
		<div class="epesi_big_button">
            {if $display_icon}
            <img src="{$theme_dir}/CRM/Filters/manage.png" alt="" align="middle" border="0" width="32" height="32">
            {/if}
            {if $display_text}
                <span>{$__link.manage.text}</span>
            {/if}
        </div>
	    {$__link.manage.close}

        </td>
    </tr>
        <td colspan="3" style="text-align: center;">
			{$contacts_open}
				{$contacts_data.crm_filter_contact.label}&nbsp;<span class="filters-autoselect">{$contacts_data.crm_filter_contact.html}</span>&nbsp;<span class="child_button">{$contacts_data.submit.html}</span>
			{$contacts_close}
		</td>
    </tr>
</table>

<br>

{if !empty($filters)}
	<table id="CRM_Filters" cellspacing="0" cellpadding="0">
		<tr>
			<td colspan="4" class="epesi_label header">&nbsp;&nbsp;{$saved_filters}&nbsp;&nbsp;</td>
		</tr>
		<tr>

		{assign var=x value=0}
		{foreach item=p key=k from=$filters}
		{assign var=x value=$x+1}

			<td>

			{$p.open}
			<div class="epesi_big_button">
				<span class="text">{$p.title}</span>
			</div>
			{$p.close}

		</td>

		{if ($x%4)==0}
		</tr>
		<tr>
		{/if}

	{/foreach}

		</tr>

	</table>
{/if}

</center>
