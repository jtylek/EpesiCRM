<dl class="dl-horizontal">
    <dt>{$f.label}{if $f.required}*{/if}{$f.advanced}</dt>
    <dd>
        {if $f.error}{$f.error}{/if}
        {if $f.help}
            <div class="help"><img src="{$f.help.icon}" alt="help" {$f.help.text}></div>
        {/if}
        {$f.html}
    </dd>
</dl>