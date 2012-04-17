<center>
<br>
<table cellspacing="0" cellpadding="0">
	<tr>
{foreach key=k item=cd from=$custom_defaults}
	        <td>
				{$cd.open}
				<div class="epesi_big_button">
					<img src="{$cd.icon}">
					<span>{$cd.label}</span>
				</div>
				{$cd.close}
	        </td>
{/foreach}
    </tr>
</table>

</center>


