<center>

<div id="Profiles_header">
    <div class="center"><div style="position: absolute; left: 45%;">{$header}</div></div>
	<div class="right">{$__link.close.open}<img src="{$theme_dir}/CRM_Profiles__close.png" width="14" height="14" alt="x" border="0">{$__link.close.close}</div>
</div>

<table id="CRM_Profiles" cellspacing="0" cellpadding="0">
	<tr>
        <!-- MY -->
        <td>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 80px;">
		<div class="content_shadow">
<!-- -->

	    {$__link.my.open}
		<div class="button">
            {if $display_icon}
            <img src="{$theme_dir}/CRM_Profiles__my.png" alt="" align="middle" border="0" width="32" height="32">
            {/if}
            {if $display_text}
                <div style="height: 5px;"></div>
                <span>{$__link.my.text}</span>
            {/if}
        </div>
	    {$__link.my.close}

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

        <!-- ALL -->
        <td>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 80px;">
		<div class="content_shadow">
<!-- -->

	    {$__link.all.open}
		<div class="button">
            {if $display_icon}
            <img src="{$theme_dir}/CRM_Profiles__all.png" alt="" align="middle" border="0" width="32" height="32">
            {/if}
            {if $display_text}
                <div style="height: 5px;"></div>
                <span>{$__link.all.text}</span>
            {/if}
        </div>
	    {$__link.all.close}

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
    </tr>
    <tr>
        <td colspan="2">{$contacts}</td>
    </tr>
</table>

<br>
    
<table id="CRM_Profiles" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="3" class="Profiles_header">&nbsp;&nbsp;Groups&nbsp;&nbsp;</td>
    </tr>
	<tr>

	{assign var=x value=0}
    {foreach item=p key=k from=$profiles}
	{assign var=x value=$x+1}

		<td>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 120px;">
		<div class="content_shadow">
<!-- -->

	    {$__link.profiles.$k.open}
		<div class="button">
            <span>{$__link.profiles.$k.text}</span>
        </div>
	    {$__link.profiles.$k.close}

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

	{if ($x%3)==0}
	</tr>
	<tr>
	{/if}

{/foreach}

	</tr>
</table>

</center>