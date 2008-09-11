<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 310px; min-height: 30px;">
		<div class="content_shadow">
<!-- -->

            <table class="container" cellpadding="0" cellspacing="0" border="0">
                <tbody>
                <tr>
                	<td width="3px" class="header actions {$color}">
                	</td>
                	{if !empty($actions)}
						{assign var=actions_width value=$actions|@count}
						{assign var=actions_width value=$actions_width*16}
						{assign var=actions_width value=$actions_width+4}
	                	<td width="{$actions_width}px" class="header actions {$color}">
							{foreach item=action from=$actions}
		                		{$action}
		                	{/foreach}
	                	</td>
	                {else}
	                	<td width="3px" class="header actions {$color}">
	                	</td>
                	{/if}
                	{* 18px/icon + 2px base *}
                    <td class="header title {$handle_class} {$color}">{$caption}</td>
                    <td class="header controls {$color}" nowrap="1">{if isset($href)}{$__link.href.open}<img src="{$theme_dir}/Base/Dashboard/resize.png" onMouseOver="this.src='{$theme_dir}/Base/Dashboard/resize-hover.png';" onMouseOut="this.src='{$theme_dir}/Base/Dashboard/resize.png';" width="14" height="14" alt="R" border="0">&nbsp;{$__link.href.close}{/if}{if isset($toggle)}{$__link.toggle.open}<img src="{$theme_dir}/Base/Dashboard/roll-up.png" onClick="var x='{$theme_dir}/Base/Dashboard/roll-';if(this.src.indexOf(x+'down.png')>=0)this.src=x+'up.png';else this.src=x+'down.png';" width="14" height="14" alt="=" border="0">&nbsp;{$__link.toggle.close}{/if}{if isset($configure)}{$__link.configure.open}<img src="{$theme_dir}/Base/Dashboard/configure.png" onMouseOver="this.src='{$theme_dir}/Base/Dashboard/configure-hover.png';" onMouseOut="this.src='{$theme_dir}/Base/Dashboard/configure.png';" width="14" height="14" alt="c" border="0">&nbsp;{$__link.configure.close}{/if}{$__link.remove.open}<img src="{$theme_dir}/Base/Dashboard/close.png" onMouseOver="this.src='{$theme_dir}/Base/Dashboard/close-hover.png';" onMouseOut="this.src='{$theme_dir}/Base/Dashboard/close.png';" width="14" height="14" alt="x" border="0">&nbsp;{$__link.remove.close}</td>
                </tr>
                <tr>
                    <td colspan="4" class="content_td" onclick="">{$content}</td>
                </tr>
                </tbody>
            </table>

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
