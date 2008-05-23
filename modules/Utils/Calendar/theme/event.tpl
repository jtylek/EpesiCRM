{php}
	load_js('modules/Utils/Calendar/theme/event.js');
{/php}

<span id="Utils_Calendar__event" class="event_{$color}" >

    <span class="event_menu" id="event_menu_{$event_id}" style="display: none; height: 40px; width: 120px;">
        <!-- SHADIW BEGIN -->
        <div class="layer" style="padding: 10px; width: 100px;">
        	<div class="content_shadow">
        <!-- -->

            <span class="event_menu_content" style="background-color: #e6ecf2; heigth: 20px; vertical-align: middle; display: block; padding-left: 5px; padding-right: 3px;">
                {if isset($view_href)}
                    <a {$view_href}><img border="0" src="{$theme_dir}/Utils_Calendar__view.png" style="padding: 3px;"></a>
                {/if}
                {if isset($edit_href)}
                    <a {$edit_href}><img border="0" src="{$theme_dir}/Utils_Calendar__edit.png" style="padding: 3px;"></a>
                {/if}
                {if isset($delete_href)}
                    <a {$delete_href}><img border="0" src="{$theme_dir}/Utils_Calendar__delete.png" style="padding: 3px;"></a>
                {/if}
                {if isset($move_href)}
                    <a {$move_href}><img border="0" src="{$theme_dir}/Utils_Calendar__move.png" style="padding: 3px;"></a>
                {/if}

                <a><img border="0" src="{$theme_dir}/Utils_Calendar__close.png" style="padding: 3px;"></a>
                <a><img border="0" src="{$theme_dir}/Utils_Calendar__calendar.png" style="padding: 3px;"></a>
                <a><img border="0" src="{$theme_dir}/Utils_Calendar__task.png" style="padding: 3px;"></a>
                <a><img border="0" src="{$theme_dir}/Utils_Calendar__phone.png" style="padding: 3px;"></a>
<!--
                {foreach from=$custom_actions item=action}
                    <a {$action.href}><img border="0" src="{$action.icon}" style="padding: 3px;"></a>
                {/foreach}
-->
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
        <div id="event_time">{if isset($view_href)}<a {$view_href}>{$start_time}</a>{else}{$start_time}{/if}</div>
    </div>
     <div class="row {if $draggable}{$handle_class}{/if}">
        <span id="event_title">{$title_s}</span>
    </div>
</span>
