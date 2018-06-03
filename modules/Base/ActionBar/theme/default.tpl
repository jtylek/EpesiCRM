<div id="Base_ActionBar">
<div class="pull-left">
    {foreach item=i from=$launcher_left}
        {$i.open}
        <div class="btn btn-default">
            {if $i.icon_url}
                <img src="{$i.icon_url}" style="height:2em">
            {else}
                <i class="fa fa-{$i.icon} fa-2x"></i>
            {/if}
            <div>{$i.label}</div>
        </div>
        {$i.close}
    {/foreach}

    {foreach item=i from=$icons}
        {$i.open}
        <div class="btn btn-default" helpID="{$i.helpID}">
            {if $i.icon_url}
                <img src="{$i.icon_url}" style="height:3em">
            {else}
                <i class="fa fa-{$i.icon} fa-3x"></i>
            {/if}
            <div>{$i.label}</div>
        </div>
        {$i.close}
    {/foreach}
</div>


</div>

