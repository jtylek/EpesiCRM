<center>

    {if $form_html}{$form_html}{/if}

    {if $icons}
    {* Was a <table> hand-wrapping every 3 icons into a new <tr> - flex-wrap
       replaces that, same recipe used app-wide for this pattern (see
       AI-shared/adminlte-theme.md). *}
    <div id="Base_Print" style="display: flex; flex-wrap: wrap; justify-content: center; margin: 10px;">

            {foreach item=i from=$icons}

            <a {$i.href}>
                <div class="big-button">
                    {$i.label}
                </div>
            </a>

            {/foreach}

    </div>
    {else}
        <h2>{"No template available"|t}</h2>
    {/if}

</center>
