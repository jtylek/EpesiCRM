<center>

<table id="CRM_Filters" cellspacing="0" cellpadding="0">
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
            <img src="{$theme_dir}/CRM_Filters__my.png" alt="" align="middle" border="0" width="32" height="32">
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
            <img src="{$theme_dir}/CRM_Filters__all.png" alt="" align="middle" border="0" width="32" height="32">
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
        
        <!-- MANAGE FILTERS -->
        <td>
<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 100px;">
		<div class="content_shadow">
<!-- -->



	    {$__link.manage.open}
		<div class="button">
            {if $display_icon}
            <img src="{$theme_dir}/CRM_Filters__manage.png" alt="" align="middle" border="0" width="32" height="32">
            {/if}
            {if $display_text}
                <div style="height: 5px;"></div>
                <span>{$__link.manage.text}</span>
            {/if}
        </div>
	    {$__link.manage.close}


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
        <td colspan="3" style="text-align: center;">{$contacts}</td>
    </tr>
</table>

<br>
    
<table id="CRM_Filters" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="4" class="Filters_header">&nbsp;&nbsp;Saved Filters&nbsp;&nbsp;</td>
    </tr>
	<tr>

	{assign var=x value=0}
    {foreach item=p key=k from=$filters}
	{assign var=x value=$x+1}

		<td>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 120px;">
		<div class="content_shadow">
<!-- -->

	    {$__link.filters.$k.open}
		<div class="button">
            <span class="text">{$__link.filters.$k.text}</span>
            <span class="desc">This is description of the group</span>
        </div>
	    {$__link.filters.$k.close}

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

	{if ($x%4)==0}
	</tr>
	<tr>
	{/if}

{/foreach}

	</tr>

</table>

</center>