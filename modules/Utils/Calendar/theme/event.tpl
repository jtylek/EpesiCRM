{php}
	load_js('modules/Utils/Calendar/theme/event.js');
{/php}

<span id="Utils_Calendar__event" class="event_{$color}">

    <span class="event_menu" id="event_menu_{$event_id}" style="display: none;">
        <!-- SHADIW BEGIN -->
        <div class="layer" style="padding: 10px; width: 86px;">
        	<div class="content_shadow">
        <!-- -->

        <span class="event_menu_content">
		<a {$view_href}><img border=0 src="{$theme_dir}/Utils_Calendar__view.png"></a>
		<a {$edit_href}><img border=0 src="{$theme_dir}/Utils_Calendar__edit.png"></a>
		<a {$delete_href}><img border=0 src="{$theme_dir}/Utils_Calendar__delete.png"></a>
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
        <span id="event_grab" class="{$handle_class}"><img border=0 src="{$theme_dir}/Utils_Calendar__grab.png"></span>
        <span id="event_time" {$tip2_tag_attrs}>time {$event_id}</span>
        <span id="event_info"><img {$tip_tag_attrs} src="{$theme_dir}/Utils_Calendar__info.png" onClick="event_menu('{$event_id}')" width="14" height="14" border="0"></span>
    </div>
     <div class="row">
        <span id="event_title"><a {$view_href}>{$title}</a></span>
    </div>
</span>
