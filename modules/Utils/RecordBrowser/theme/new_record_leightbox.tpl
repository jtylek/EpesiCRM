<center>
<br>
<table cellspacing="0" cellpadding="0">
	<tr>
{foreach key=k item=cd from=$custom_defaults}
	        <td>
				{$cd.open}
				<div class="epesi_big_button">
                    {if $cd.icon}
					<img src="{$cd.icon}">
                    {/if}
					<span>{$cd.label}</span>
				</div>
				{$cd.close}
	        </td>
{/foreach}
    </tr>
</table>

</center>


