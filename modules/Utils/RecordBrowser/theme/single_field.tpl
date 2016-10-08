<div class="clearfix" id="_{$f.element}__container">
    <span class="label label-default{if $f.type != 'long text'} pull-left{/if}">{$f.label}{if $f.required}*{/if}{$f.advanced}</span>
    <span class="data{if $f.type != 'long text'} pull-right{/if} {$f.style}" id="_{$f.element}__data">
        {if $f.error}{$f.error}{/if}
        {if $f.help}
            <div class="help"><img src="{$f.help.icon}" alt="help" {$f.help.text}></div>
        {/if}
        <div>
            {$f.html}{if $action == 'view'}&nbsp;{/if}
        </div>
    </span>
</div>
