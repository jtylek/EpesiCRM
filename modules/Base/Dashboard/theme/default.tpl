<table id="shadow" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td class="top-left"></td>
        <td class="top-center"></td>
        <td class="top-right"></td>
    </tr>
    <tr>
        <td class="center-left"></td>
        <td class="center-center">
            <!-- -->
            <table class="container" cellpadding="0" cellspacing="0" border="0">
                <tr>
            	<td style="width: 80%; text-align: center;" class="header {$handle_class}">{$caption}</td>
            	<td style="width: 20%; text-align: center;" class="header controls">{$__link.toggle.open}={$__link.toggle.close} 
            	{if isset($configure)}{$__link.configure.open}c{$__link.configure.close} {/if}
            	{$__link.remove.open}x{$__link.remove.close}</td>
                </tr>
                <tr>
                    <td colspan="2" class="content_td">{$content}</td>
                </tr>
            </table>
            <!-- -->
        </td>
        <td class="center-right"></td>
    </tr>    
    <tr>
        <td class="bottom-left"></td>
        <td class="bottom-center"></td>
        <td class="bottom-right"></td>
    </tr>
</table>
