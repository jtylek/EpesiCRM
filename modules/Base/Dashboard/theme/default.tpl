	<div class="layer" style="padding: 10px 0px; width: 96%; min-height: 30px;">
		<div class="content_shadow_css3_dashboard {$color}_dashboard">
            <table class="container {$color}_dashboard" cellpadding="0" cellspacing="0" border="0">
                <tbody>
                <tr class="nonselectable">
                	<td width="3px" class="header actions {$color}_dashboard">
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
                    <td class="header title {$handle_class} {if $fixed}fixed {/if}{$color}">{$caption}</td>
					{assign var=actions_width value=8}
					{if isset($href)}
						{assign var=actions_width value=$actions_width+18}
					{/if}
					{if isset($toggle)}
						{assign var=actions_width value=$actions_width+18}
					{/if}
					{if isset($configure)}
						{assign var=actions_width value=$actions_width+18}
					{/if}
					{if isset($remove)}
						{assign var=actions_width value=$actions_width+18}
					{/if}
                    <td class="header controls {$color}" nowrap="1" width="{$actions_width}px">
						{if isset($href)}
							{$__link.href.open}
							{*<img src="{$theme_dir}/Base/Dashboard/resize.png" onMouseOver="this.src='{$theme_dir}/Base/Dashboard/resize-hover.png';" onMouseOut="this.src='{$theme_dir}/Base/Dashboard/resize.png';" width="14" height="14" alt="R" border="0">*}
							<i class="fa fa-arrows-alt fa-lg" style="vertical-align: 20%"></i>
							{$__link.href.close}
						{/if}
						{if isset($toggle)}
							{$__link.toggle.open}
							<i class="fa fa-caret-up fa-lg" style="vertical-align: 20%" onclick="jq(this).toggleClass('fa-caret-down'); jq(this).toggleClass('fa-caret-up');"></i>
							{$__link.toggle.close}
						{/if}
						{if isset($configure)}
							{$__link.configure.open}
							<i class="fa fa-cogs fa-lg" style="vertical-align: 20%"></i>
							{$__link.configure.close}
						{/if}
						{if isset($remove)}
							{$__link.remove.open}
							<i class="fa fa-times fa-lg" style="vertical-align: 20%"></i>
							{$__link.remove.close}
						{/if}
						&nbsp;
					</td>
                </tr>
                <tr>
                    <td colspan="4" class="content_td" onclick="">{$content}</td>
                </tr>
                </tbody>
            </table>
 		</div>
	</div>

