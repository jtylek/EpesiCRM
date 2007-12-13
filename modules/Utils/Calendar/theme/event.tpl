{php}
	load_js('modules/Utils/Calendar/theme/event.js');
{/php}

{$open}

<span id="Utils_Calendar__event">
    <div class="row">
        <span class="{$handle_class} grab"><img {$tip_tag_attrs} border=0 src="{$theme_dir}/Utils_Calendar__grab.png"></span>
        <span class="time">time</span>
        <span class="info"><img {$tip_tag_attrs} src="{$theme_dir}/Utils_Calendar__info.png" onClick="event_menu()" width="14" height="14" border="0"></span>
    </div>
     <div class="row">
        <span id="event_menu"><img {$tip_tag_attrs} border=0 src="{$theme_dir}/Utils_Calendar__view.png"><img {$tip_tag_attrs} border=0 src="{$theme_dir}/Utils_Calendar__edit.png"><img {$tip_tag_attrs} border=0 src="{$theme_dir}/Utils_Calendar__delete.png"><img {$tip_tag_attrs} border=0 src="{$theme_dir}/Utils_Calendar__select.png"></span>
        <span id="event_title" {$view_action}>{$title}</span>
    </div>
</span>

{$close}
