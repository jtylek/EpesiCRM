<center>

<table id="Base_ActionBar" cellspacing="0" cellpadding="0" style="margin: 10px;">
	<tr>

	{assign var=x value=0}
    {foreach item=i from=$icons}
	{assign var=x value=$x+1}

		<td>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 100px;">
		<div class="content_shadow">
<!-- -->

	    {$i.open}
		<div class="big-button">
            {if $display_icon}
            <img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32">
            {/if}
            {if $display_text}
                <div style="height: 5px;"></div>
                <span>{$i.label}</span>
            {/if}
        </div>
	    {$i.close}

<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->

		</td>

	{if ($x%5)==0}
	</tr>
	<tr>
	{/if}

{/foreach}

	</tr>
</table>

</center>
