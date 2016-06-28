<div id="Base_User_Settings" class="panel panel-default">
    <div class="panel-heading">{$header}</div>
    <div class="panel-body">
        {foreach key=key item=button from=$buttons}
            {$__link.buttons.$key.link.open}
            <button class="btn btn-default">
                {if isset($button.icon)}
                    <img src="{$button.icon}" border="0" width="32" height="32" align="middle">
                {/if}
                <div>
                    {$__link.buttons.$key.link.text}
                </div>
            </button>
            {$__link.buttons.$key.link.close}
            <!-- $key holds name of the module -->
        {/foreach}
    </div>
</div>
