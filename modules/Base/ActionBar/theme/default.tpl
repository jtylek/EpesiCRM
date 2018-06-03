
<div class="btn-toolbar" role="toolbar" aria-label="Toolbar with button groups">
    <div class="btn-group btn-group-sm mr-2" role="group" aria-label="First group">
        {foreach item=i from=$launcher_left}
            {$i.open}
            <button class="btn btn-sm btn-secondary">
                {if $i.icon}

                    <i class="fa fa-{$i.icon} fa-2x"></i>
                {/if}
                {$i.label}
            </button>
            {$i.close}
        {/foreach}
    </div>
    <div class="btn-group btn-group-sm mr-2" role="group" aria-label="Second group">
        {foreach item=i from=$icons}
            {$i.open}
            <button class="btn btn-sm btn-secondary" helpID="{$i.helpID}">
                {if $i.icon}
                    <i class="fa fa-{$i.icon} fa-3x"></i>
                {/if}
                {$i.label}
            </button>
            {$i.close}
        {/foreach}
    </div>
</div>