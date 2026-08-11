	<div class="layer" style="padding: 10px 0px; width: 96%; min-height: 30px;">
		<div class="content_shadow_css3_dashboard {$color}_dashboard">
            <div class="container {$color}_dashboard">
                <div class="header-row nonselectable" style="display: flex; align-items: center;">
                	<div style="width:3px;" class="header actions {$color}_dashboard">
                	</div>
                	{if !empty($actions)}
						{assign var=actions_width value=$actions|@count}
						{assign var=actions_width value=$actions_width*16}
						{assign var=actions_width value=$actions_width+4}
	                	<div style="width:{$actions_width}px;" class="header actions {$color}">
							{foreach item=action from=$actions}
		                		{$action}
		                	{/foreach}
	                	</div>
	                {else}
	                	<div style="width:3px;" class="header actions {$color}">
	                	</div>
                	{/if}
                	{* 18px/icon + 2px base *}
                    <div class="header title {$handle_class} {if $fixed}fixed {/if}{$color}" style="flex: 1 1 auto;">{$caption}</div>
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
                    <div class="header controls {$color}" style="white-space: nowrap; width:{$actions_width}px;">
						{if isset($href)}
							{$__link.href.open}
							<img src="{$theme_dir}/Base/Dashboard/resize.png" onMouseOver="this.src='{$theme_dir}/Base/Dashboard/resize-hover.png';" onMouseOut="this.src='{$theme_dir}/Base/Dashboard/resize.png';" width="14" height="14" alt="R" border="0">
							{$__link.href.close}
						{/if}
						{if isset($toggle)}
							{$__link.toggle.open}
							<img src="{$theme_dir}/Base/Dashboard/roll-up.png" onMouseOver="var x='{$theme_dir}/Base/Dashboard/roll-';if(this.src.indexOf('roll-down')>=0)this.src=x+'down-hover.png';else this.src=x+'up-hover.png';" onMouseOut="var x='{$theme_dir}/Base/Dashboard/roll-';if(this.src.indexOf('roll-down')>=0)this.src=x+'down.png';else this.src=x+'up.png';" onClick="var x='{$theme_dir}/Base/Dashboard/roll-';if(this.src.indexOf('roll-down')>=0)this.src=x+'up-hover.png';else this.src=x+'down-hover.png';" width="14" height="14" alt="=" border="0">
							{$__link.toggle.close}
						{/if}
						{if isset($configure)}
							{$__link.configure.open}
							<img src="{$theme_dir}/Base/Dashboard/configure.png" onMouseOver="this.src='{$theme_dir}/Base/Dashboard/configure-hover.png';" onMouseOut="this.src='{$theme_dir}/Base/Dashboard/configure.png';" width="14" height="14" alt="c" border="0">
							{$__link.configure.close}
						{/if}
						{if isset($remove)}
							{$__link.remove.open}
							<img src="{$theme_dir}/Base/Dashboard/close.png" onMouseOver="this.src='{$theme_dir}/Base/Dashboard/close-hover.png';" onMouseOut="this.src='{$theme_dir}/Base/Dashboard/close.png';" width="14" height="14" alt="x" border="0">
							{$__link.remove.close}
						{/if}
						&nbsp;
					</div>
                </div>
                <div class="content_td" onclick="">{$content}</div>
            </div>
 		</div>
	</div>

