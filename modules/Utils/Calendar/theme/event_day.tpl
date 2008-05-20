{php}
	load_js('modules/Utils/Calendar/theme/event.js');
{/php}

<span id="Utils_Calendar__event_day" class="event_{$color}">

    <span class="event_menu" id="event_menu_{$event_id}" style="display: none;">
        <!-- SHADIW BEGIN -->
        <div class="layer" style="padding: 10px; width: 100px;">
        	<div class="content_shadow">
        <!-- -->

            <span class="event_menu_content">
	    	{if isset($view_href)}
                <a {$view_href}><img border="0" src="{$theme_dir}/Utils_Calendar__view.png"></a>
		{/if}
	    	{if isset($edit_href)}
                <a {$edit_href}><img border="0" src="{$theme_dir}/Utils_Calendar__edit.png"></a>
		{/if}
	    	{if isset($delete_href)}
                <a {$delete_href}><img border="0" src="{$theme_dir}/Utils_Calendar__delete.png"></a>
		{/if}
	    	{if isset($move_href)}
                <a {$move_href}><img border="0" src="{$theme_dir}/Utils_Calendar__move.png"></a>
		{/if}
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

    <div class="row">
        <span id="event_info"><img {$tip_tag_attrs} src="{$theme_dir}/Utils_Calendar__info.png" onClick="event_menu('{$event_id}')" width="11" height="11" border="0"></span>
        <span id="event_time">{if isset($view_href)}<a {$view_href}>{/if}{$start_time} - {$end_time} ({$duration}){if isset($view_href)}</a>{/if}</span>
    </div>
     <div class="row {if $draggable}{$handle_class}{/if}">
        <span id="event_title">{$title}{if $description!=''} - {$description|truncate:100:"..."}{/if}</span>
    </div>
</span>
