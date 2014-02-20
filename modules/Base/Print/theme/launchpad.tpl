<center>

    {if $form_html}{$form_html}{/if}

    {if $icons}
    <table id="Base_Print" cellspacing="0" cellpadding="0" style="margin: 10px;">
        <tr>

            {assign var=x value=0}
            {foreach item=i from=$icons}
            {assign var=x value=$x+1}

            <td>
                <a {$i.href}>
                    <div class="big-button">
                        {$i.label}
                    </div>
                </a>
            </td>

            {if ($x%3)==0}
        </tr>
        <tr>
            {/if}

            {/foreach}

        </tr>
    </table>
    {else}
        <h2>{"No template available"|t}</h2>
    {/if}

</center>
