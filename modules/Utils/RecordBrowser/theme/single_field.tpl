<div class="form-group clearfix" id="_{$f.element}__container">
    <div class="row">
    <label class="control-label{if $f.type != 'long text'} col-md-4 col-sm-3{/if} col-xs-12">{$f.label}{if $f.required}*{/if}{$f.advanced}</label>
    <span class="data {if $f.type != 'long text'} col-md-8 col-sm-9{/if} col-xs-12" style="{$f.style}" id="_{$f.element}__data">
        {if $f.error}{$f.error}{/if}
        {if $f.help}
            <div class="field-help"><img src="{$f.help.icon}" alt="help" {$f.help.text}></div>
        {/if}
        <div>
            {$f.html}{if $action == 'view'}&nbsp;{/if}
        </div>
    </span>
    </div>
</div>
