<table id="shadow" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td class="td-5x5 top-left"></td>
        <td class="td-5x5 top-left-right"></td>
        <td class="top-center">&nbsp;</td>
        <td class="td-5x5 top-right-left"></td>
        <td class="td-5x5 top-right"></td>
    </tr>
    <tr>
        <td class="td-5x5 top-left-left"></td>
        <td colspan="3" rowspan="3" class="center-center">
            <!-- -->
            <table class="container" cellpadding="0" cellspacing="0" border="0">
                <tr>
            	<td class="header title {$handle_class}">&nbsp;{$caption}</td>
            	<td class="header controls">
            	{if isset($href)}{$__link.href.open}G{$__link.href.close} {/if}
		{if isset($toggle)}{$__link.toggle.open}<img src="{$theme_dir}/images/icons/small-icon-roll-up.png" onClick="var x='{$theme_dir}/images/icons/small-icon-roll-';if(this.src.indexOf(x+'down.png')>=0)this.src=x+'up.png';else this.src=x+'down.png';" width="14" height="14" alt="=" border="0">{$__link.toggle.close} {/if}
            	{if isset($configure)}{$__link.configure.open}<img src="{$theme_dir}/images/icons/small-icon-configure.png" width="14" height="14" alt="c" border="0">{$__link.configure.close} {/if}
            	{$__link.remove.open}<img src="{$theme_dir}/images/icons/small-icon-close.png" width="14" height="14" alt="x" border="0">{$__link.remove.close}&nbsp;
		</td>
                </tr>
                <tr>
                    <td colspan="2" class="content_td">{$content}</td>
                </tr>
            </table>
        </td>
        <td class="td-5x5 top-right-right"></td>
    </tr>
    <tr>
        <td class="center-left">&nbsp;</td>
        <td class="center-right">&nbsp;</td>
    </tr>
    <tr>
        <td class="td-5x5 bottom-left-right"></td>
        <td class="td-5x5 bottom-right-left"></td>
    </tr>
    <tr>
        <td class="td-5x5 bottom-left"></td>
        <td class="td-5x5 bottom-left-left"></td>
        <td class="bottom-center">&nbsp;</td>
        <td class="td-5x5 bottom-right-right"></td>
        <td class="td-5x5 bottom-right"></td>
    </tr>
</table>
