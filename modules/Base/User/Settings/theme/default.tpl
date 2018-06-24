<div id="Base_User_Settings" class="card ">
    <div class="card-header">{$header}</div>
    <div class="card-body btn-toolbar">
        {foreach key=key item=button from=$buttons}
            <div class="btn btn-default col-xs-4 col-sm-3 col-md-2 col-lg-1">
                {$__link.buttons.$key.link.open}
                    {if isset($button.icon)}
                        <img src="{$button.icon}" border="0" width="32" height="32" align="middle">
                    {/if}
                    <div>
                        {$__link.buttons.$key.link.text}
                    </div>
                {$__link.buttons.$key.link.close}
            </div>
            <!-- $key holds name of the module -->
        {/foreach}
    </div>
</div>
