<tr>
    <td class="label{if $f.type == 'long text'} long_label{/if}">{$f.label}{if $f.required}*{/if}{$f.advanced}</td>
    <td class="data{if $f.type == 'long text'} long_data{/if} {$f.style}" id="_{$f.element}__data">
        {if $f.error}{$f.error}{/if}
        {if $f.help}
            <div class="help"><img src="{$f.help.icon}" alt="help" {$f.help.text}></div>
        {/if}
        <div>
            {$f.html}{if $action == 'view'}&nbsp;{/if}
        </div>
    </td>
</tr>
