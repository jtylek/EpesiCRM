<br>
<br>
<table id="Base_Admin" cellspacing="5" cellpadding="0">
	<tr>
		<td colspan="4" class="header">{$header}</td>
	</tr>
	<tr>

	{assign var=x value=0}
	{foreach key=key item=link from=$links}
	{assign var=x value=$x+1}

		<td>

<!-- poprawic cien - jako funkcje -->
<table id="shadow" cellpadding="0" cellspacing="0" border="0">
    <tbody>
    <tr>
        <td class="td-5x5 p-top top-left">&nbsp;</td>
        <td class="td-h-5 p-top top-center">&nbsp;</td>
        <td class="td-5x5 p-top top-right">&nbsp;</td>
    </tr>
    <tr>
        <td class="td-w-5 p-left center-left">&nbsp;</td>
        <td class="center-center">
        <!-- -->

			{$__link.links.$key.open}
			<div class="button">
				<img src="{$theme_dir}/{$key}.png" border="0" width="32" height="32" align="middle">&nbsp;&nbsp;{$__link.links.$key.text}
			</div>
			{$__link.links.$key.close}

        <!-- -->
        </td>
        <td class="td-w-5 p-right center-right">&nbsp;</td>
    </tr>
    <tr>
        <td class="td-5x5 p-bottom bottom-left">&nbsp;</td>
        <td class="td-h-5 p-bottom bottom-center">&nbsp;</td>
        <td class="td-5x5 p-bottom bottom-right">&nbsp;</td>
    </tr>
    </tbody>
</table>
		</td>

	<!-- $key holds name of the module -->
	{if ($x%4)==0}
	</tr>
	<tr>
	{/if}
	{/foreach}
	</tr>
</table>
