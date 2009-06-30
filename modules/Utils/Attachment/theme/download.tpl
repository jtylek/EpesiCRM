<center>

<br/>
<h3>{$filename}</h3>
{$file_size}
<br/>

<table id="Utils_Attachment__download" cellspacing="0" cellpadding="0">
	<tr>
        <!-- VIEW -->
        <td>
            <!-- SHADIW BEGIN -->
            <div class="layer" style="padding: 8px; width: 120px;">
            	<div class="content_shadow">
            <!-- -->
                    {$__link.view.open}
                    <div class="big-button">
                        <img src="{$theme_dir}/Utils/Attachment/view.png" alt="" align="middle" border="0" width="32" height="32">
                        <div style="height: 5px;"></div>
                        <span>{$__link.view.text}</span>
                    </div>
                    {$__link.view.close}
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
        <!-- DOWNLOAD -->
        <td>
            <!-- SHADIW BEGIN -->
            <div class="layer" style="padding: 8px; width: 120px;">
            	<div class="content_shadow">
            <!-- -->
                    {$__link.download.open}
                    <div class="big-button">
                        <img src="{$theme_dir}/Utils/Attachment/download.png" alt="" align="middle" border="0" width="32" height="32">
                        <div style="height: 5px;"></div>
                        <span>{$__link.download.text}</span>
                    </div>
                    {$__link.download.close}
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
        <!-- LINK -->
        <td>
            <!-- SHADIW BEGIN -->
            <div class="layer" style="padding: 8px; width: 120px;">
            	<div class="content_shadow">
            <!-- -->
                    {$__link.link.open}
                    <div class="big-button">
                        <img src="{$theme_dir}/Utils/Attachment/link.png" alt="" align="middle" border="0" width="32" height="32">
                        <div style="height: 5px;"></div>
                        <span>{$__link.link.text}</span>
                    </div>
                    {$__link.link.close}
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
</table>

<table id="Utils_Attachment__download" cellspacing="0" cellpadding="0">
	<tr>
        <!-- VIEW -->
	{assign var=x value=0}
    {foreach item=p key=k from=$custom_getters}
	{assign var=x value=$x+1}

		<td>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 120px;">
		<div class="content_shadow">
<!-- -->
                    {$p.open}
                    <div class="big-button">
                        <img src="{$theme_dir}/{$p.icon}" alt="" align="middle" border="0" width="32" height="32">
                        <div style="height: 5px;"></div>
                        <span>{$p.text}</span>
                    </div>
                    {$p.close}

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
