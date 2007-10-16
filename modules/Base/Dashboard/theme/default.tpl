<!-- poprawic cien - jako funkcje -->
<table id="shadow" cellpadding="0" cellspacing="0" border="0">
    <tbody>
    <tr>
        <td class="td-5x5 p-top top-left">&nbsp;</td>
        <td class="td-h-5 p-top top-center">&nbsp;</td>
        <td class="td-5x5 p-top top-right">&nbsp;</td>
    </tr>
    <tr>
        <td class="td-w-5 p-left center-left">&nbsp;</td>
        <td class="center-center">
        <!-- -->

            <table class="container" cellpadding="0" cellspacing="0" border="0">
                <tbody>
                <tr>
                    <td class="header title {$handle_class}">&nbsp;{$caption}</td>
                    <td class="header controls">{if isset($href)}{$__link.href.open}<img src="{$theme_dir}/images/icons/small-icon-resize.png" width="14" height="14" alt="G" border="0">&nbsp;{$__link.href.close}{/if}{if isset($toggle)}{$__link.toggle.open}<img src="{$theme_dir}/images/icons/small-icon-roll-up.png" onClick="var x='{$theme_dir}/images/icons/small-icon-roll-';if(this.src.indexOf(x+'down.png')>=0)this.src=x+'up.png';else this.src=x+'down.png';" width="14" height="14" alt="=" border="0">&nbsp;{$__link.toggle.close}{/if}{if isset($configure)}{$__link.configure.open}<img src="{$theme_dir}/images/icons/small-icon-configure.png" width="14" height="14" alt="c" border="0">&nbsp;{$__link.configure.close}{/if}{$__link.remove.open}<img src="{$theme_dir}/images/icons/small-icon-close.png" width="14" height="14" alt="x" border="0">&nbsp;{$__link.remove.close}</td>
                </tr>
                <tr>
                    <td colspan="2" class="content_td">{$content}</td>
                </tr>
                </tbody>
            </table>

        <!-- -->
        </td>
        <td class="td-w-5 p-right center-right">&nbsp;</td>
    </tr>
    <tr>
        <td class="td-5x5 p-bottom bottom-left">&nbsp;</td>
        <td class="td-h-5 p-bottom bottom-center">&nbsp;</td>
        <td class="td-5x5 p-bottom bottom-right">&nbsp;</td>
    </tr>
    </tbody>
</table>
