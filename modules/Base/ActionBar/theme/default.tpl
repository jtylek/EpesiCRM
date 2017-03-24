<div id="Base_ActionBar">
<div class="nav navbar-nav navbar-left">
    {foreach item=i from=$icons}
        {$i.open}
        <div class="btn toggle" helpID="{$i.helpID}">
            {if $i.icon_url}
                <img src="{$i.icon_url}" style="height:2em">
            {else}
                <i class="fa fa-{$i.icon} fa-2x"></i>
            {/if}
            <div>{$i.label}</div>
        </div>
        {$i.close}
    {/foreach}
</div>

<div class="nav navbar-nav navbar-right">
{foreach item=i from=$launcher}
    {$i.open}
    <div class="btn toggle">
      {if $i.icon_url}
          <img src="{$i.icon_url}" style="height:2em">
      {else}
          <i class="fa fa-{$i.icon} fa-2x"></i>
      {/if}
      <div>{$i.label}</div>
    </div>
    {$i.close}
{/foreach}
</div>
</div>
