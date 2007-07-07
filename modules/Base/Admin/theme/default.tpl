<br>
<br>
<table id="Base_Admin" cellspacing="0" cellpadding="0">
	<tr>
		<td colspan="4" class="header">{$header}</td>
	</tr>
	<tr>
	
	{assign var=x value=0}
	{foreach key=key item=link from=$links}
	{assign var=x value=$x+1}
		
		<td class="button">
			{$__link.links.$key.open}
			<div style="display: block; height: 57px; padding-top: 23px; cursor: pointer; cursor: hand;">
				<img src="{$theme_dir}/images/icons/{$key}.png" border="0" width="32" height="32" align="middle">&nbsp;&nbsp;{$__link.links.$key.text}
			</div>
			{$__link.links.$key.close}
		</td>
			<!-- $key holds name of the module -->
	{if ($x%4)==0}
	</tr>
	<tr>
	{/if}	
	{/foreach}
	</tr>
</table>
