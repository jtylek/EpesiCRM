{php}
	load_js('modules/Utils/Calendar/theme/event_.js');
{/php}

<span class="event_menu" id="event_menu_{$event_id}" style="display:none;z-index:999;position:absolute;">
	<!-- SHADIW BEGIN -->
	{assign var=x value=$custom_actions|@count}
	{assign var=x value=$x*20}
	{assign var=x value=$x+100}
	<div class="layer" style="padding: 10px; width: {$x}px;">
		<div class="content_shadow">
	<!-- -->

		<span class="event_menu_content" style="display: block;height: 20px;background-color: #e6ecf2;padding-left: 5px;padding-right: 3px;">
			<span id="Utils_Calendar__event_day_images">
				{if isset($view_href)}
					<a {$view_href}><img border="0" src="{$theme_dir}/Utils/Calendar/view.png"></a>
				{/if}
				{if isset($edit_href)}
					<a {$edit_href}><img border="0" src="{$theme_dir}/Utils/Calendar/edit.png"></a>
				{/if}
				{if isset($delete_href)}
					<a {$delete_href}><img border="0" src="{$theme_dir}/Utils/Calendar/delete.png"></a>
				{/if}
				{if isset($move_href)}
					<a {$move_href}><img border="0" src="{$theme_dir}/Utils/Calendar/move.png"></a>
				{/if}
				{foreach from=$custom_actions item=action}
					<a {$action.href}><img border="0" src="{$action.icon}"></a>
				{/foreach}
			</span>
		</span>

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
</span>

{if $with_div}
<div id="utils_calendar_event:{$event_id}" class="utils_calendar_event">
{/if}
	<span id="Utils_Calendar__event_day" class="event_{$color}">
		<div class="row">
			<span id="event_info"><img {$tip_tag_attrs} src="{$theme_dir}/Utils/Calendar/info.png" onClick="event_menu('{$event_id}')" width="11" height="11" border="0"></span>
			<span id="event_time">{if isset($view_href)}<a {$view_href}>{/if}{$start_time}{if $duration} - {$end_time} ({$duration}){/if}{if isset($view_href)}</a>{/if}</span>
		</div>
		 <div class="row {if $draggable}{$handle_class}{/if}">
			<span id="event_title">{$title}{if $description!=''} - {$description|truncate:100:"..."}{/if}</span>
		</div>
	</span>
{if $with_div}
</div>
{/if}