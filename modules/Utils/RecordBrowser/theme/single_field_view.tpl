<dl class="dl-horizontal" style="margin-bottom: 0">
    <dt>{$f.label}{if $f.required}*{/if}{$f.advanced}</dt>
    <dd>
        {if $f.error}{$f.error}{/if}
        {if $f.help}
            <div class="help"><img src="{$f.help.icon}" alt="help" {$f.help.text}></div>
        {/if}
        {$f.html}
    </dd>
</dl>