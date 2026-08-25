{* $f.help.icon (RecordBrowser_0.php::get_field_display_options(), a legacy sprite <img> path
   used by the default theme's own single_field.tpl) is deliberately unused here - this theme
   renders a Bootstrap icon instead, per Utils_TooltipCommon::icon()'s own AdminLTE branch. *}
<div class="epesi-rv-row{if $f.type == 'multiselect'} multiselect_row{/if}{if $f.type == 'long text'} long_row{/if}">
    <div class="label{if $f.type == 'long text'} long_label{/if}{if $f.type == 'multiselect'} multiselect_label{/if}">{$f.label}{if $f.required}*{/if}{$f.advanced}</div>
    <div class="data{if $f.type == 'long text'} long_data{/if} {$f.style}" id="_{$f.element}__data">
        {if $f.error}{$f.error}{/if}
        {if $f.help}
            <div class="help"><i class="bi bi-info-circle-fill text-primary" {$f.help.text}></i></div>
        {/if}
        <div>
            {$f.html}{if $action == 'view'}&nbsp;{/if}
        </div>
    </div>
</div>
