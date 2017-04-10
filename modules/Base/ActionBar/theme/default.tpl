<div id="Base_ActionBar">
    <div class="nav navbar-nav navbar-left">

        {foreach item=i from=$launcher_left}
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

        {foreach item=i from=$icons}
            {$i.open}
            <div class="btn toggle" helpID="{$i.helpID}">
                {if $i.icon_url}
                    <img src="{$i.icon_url}" style="height:2em">
                {else}
                    <i class="fa fa-{$i.icon} fa-2x"></i>
                {/if}
                {*<a href="javascript:;" class="dropdown-toggle info-number" data-toggle="dropdown" aria-expanded="false">*}
                {*<i class="fa fa-bell fa-2x"></i>*}
                {*<span class="badge bg-green">{$records_qty}</span>*}
                {*</a>*}
                <ul id="menu1" class="dropdown-menu list-unstyled msg_list" role="menu">
                    {foreach item=event from=$events}
                        <li>
                            <a {$event.view_href}>
                                <span class="image"><span
                                            class="fa fa-{if $event.icon}{$event.icon}{else}home{/if} fa-3x"></span></span>
    <span>
        <span>{$event.category}</span>
        <span class="time">{$event.time}</span>
    </span>
                            </a>
    <span class="message">
        {$event.title}
    </span>
                        </li>
                    {/foreach}
                    <li>
                        <div class="text-center">
                            <a {$href}>
                                <strong>{$status}</strong>
                                <i class="fa fa-angle-right"></i>
                            </a>
                        </div>
                    </li>
                </ul>

                <div>{$i.label}</div>
            </div>
            {$i.close}
        {/foreach}
    </div>

    <div class="nav navbar-nav navbar-right">

        {*<div style="overflow: scroll">*}
        {*{$launcher|@var_dump}*}
        {*</div>*}

        {foreach item=i from=$launcher_right}
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

