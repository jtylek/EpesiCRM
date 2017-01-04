<div class="form-group clearfix" id="_{$f.element}__container">
    <label class="control-label{if $f.type != 'long text'} col-sm-2{/if}">{$f.label}{if $f.required}*{/if}{$f.advanced}</label>
    <span class="data {if $f.type != 'long text'} col-sm-10{/if}" style="{$f.style}" id="_{$f.element}__data">
        {if $f.error}{$f.error}{/if}
        {if $f.help}
            <div class="help"><img src="{$f.help.icon}" alt="help" {$f.help.text}></div>
        {/if}
        <div>
            {$f.html}{if $action == 'view'}&nbsp;{/if}
        </div>
    </span>
</div>
