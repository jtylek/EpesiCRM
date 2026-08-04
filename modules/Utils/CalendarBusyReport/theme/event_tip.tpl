{if isset($custom_tooltip)}
<div id="Utils_Calendar__event_tip">
    <div>{$custom_tooltip}</div>
    <div class="info">{$show_hide_info}</div>
</div>
{else}
<div id="Utils_Calendar__event_tip">
    <div class="title">{$title}</div>
    <div>{$description}</div>
    <div class="epesi-rv-row"><div class="label">Start</div><div class="data">{$start}</div></div>
    <div class="epesi-rv-row"><div class="label">End</div><div class="data">{$end}</div></div>
{if $duration}
    <div class="epesi-rv-row"><div class="label">Duration</div><div class="data">{$duration}</div></div>
{/if}
    <div>{$additional_info}</div>
    <div>{$additional_info2}</div>
    <div class="info">{$show_hide_info}</div>
</div>
{/if}
