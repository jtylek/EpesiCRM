<div class="pull-left">
    {foreach item=i from=$icons}
        {$i.open}
        <div class="btn btn-default" helpID="{$i.helpID}">
            <i class="fa fa-{$i.icon} fa-3x"></i>
            <div>{$i.label}</div>
        </div>
        {$i.close}
    {/foreach}
</div>

<div class="pull-right">
{foreach item=i from=$launcher}
    {$i.open}
    <div class="btn btn-default">
        <i class="fa fa-{$i.icon} fa-3x"></i>
        <div>{$i.label}</div>
    </div>
    {$i.close}
{/foreach}
</div>