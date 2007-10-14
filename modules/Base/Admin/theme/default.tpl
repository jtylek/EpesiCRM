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
             <td class="td-5x5 top-left"></td>
             <td class="top-center">&nbsp;</td>
                <td class="td-5x5 top-right"></td>
            </tr>
            <tr>
                <td class="center-left">&nbsp;</td>
                <td class="center-center button">
                <!-- -->

			{$__link.links.$key.open}
			<div style="display: block; height: 57px; padding-top: 23px; cursor: pointer; cursor: hand;">
				<img src="{$theme_dir}/{$key}.png" border="0" width="32" height="32" align="middle">&nbsp;&nbsp;{$__link.links.$key.text}
			</div>
			{$__link.links.$key.close}

                <!-- -->
                </td>
                <td class="center-right">&nbsp;</td>
            </tr>
            <tr>
                <td class="td-5x5 bottom-left"></td>
                <td class="bottom-center">&nbsp;</td>
                <td class="td-5x5 bottom-right"></td>
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
